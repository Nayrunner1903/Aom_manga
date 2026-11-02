<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config(['jwt.secret' => bin2hex(random_bytes(32))]);
    $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00', 'Asia/Bangkok'));
});

function jwtForUser(User $user): string
{
    $token = Auth::guard('api')->login($user);
    Auth::guard('api')->forgetUser();

    return $token;
}

test('API registration returns a JWT and private default categories', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'ผู้ใช้ API', 'email' => 'API@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertCreated()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('expires_in', 900);
    $token = $response->json('access_token');
    expect(explode('.', $token))->toHaveCount(3);
    $user = User::query()->sole();
    expect($user->categories()->count())->toBe(11);
    expect(Hash::check('password', $user->password))->toBeTrue();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 'api@example.com')
        ->assertJsonMissingPath('data.password');

    $this->postJson('/api/v1/auth/login', ['email' => 'API@example.com', 'password' => 'password'])->assertOk();
});

test('API login validation safely rejects array credentials', function () {
    $this->postJson('/api/v1/auth/login', ['email' => ['bad'], 'password' => 'password'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('API login accepts correct credentials and refuses incorrect ones', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('protected API routes require a bearer token', function (string $path) {
    $this->getJson('/api/v1/'.$path)->assertUnauthorized();
})->with(['auth/me', 'transactions', 'categories', 'summary']);

test('web sessions and tokens in URLs cannot authenticate the API', function () {
    $user = User::factory()->create();
    $token = jwtForUser($user);
    $this->actingAs($user, 'web');

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->getJson('/api/v1/auth/me?token='.$token)->assertUnauthorized();
});

test('invalid signatures malformed and expired tokens are refused', function (string $variant) {
    $user = User::factory()->create();
    $token = jwtForUser($user);

    if ($variant === 'malformed') {
        $token = 'not-a-jwt';
    } elseif ($variant === 'signature') {
        $parts = explode('.', $token);
        $parts[2][0] = $parts[2][0] === 'a' ? 'b' : 'a';
        $token = implode('.', $parts);
    } else {
        $this->travel(16)->minutes();
    }

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
})->with(['malformed', 'signature', 'expired']);

test('refresh rotates the token and immediately refuses the old token', function () {
    $oldToken = jwtForUser(User::factory()->create());
    $response = $this->withToken($oldToken)->postJson('/api/v1/auth/refresh')->assertOk();
    $newToken = $response->json('access_token');

    expect($newToken)->not->toBe($oldToken);
    $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($newToken)->getJson('/api/v1/auth/me')->assertOk();
});

test('logout invalidates its token across subsequent requests', function () {
    $token = jwtForUser(User::factory()->create());

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($token)->postJson('/api/v1/auth/refresh')->assertUnauthorized();
});

test('password changes invalidate previously issued JWTs', function () {
    $user = User::factory()->create();
    $token = jwtForUser($user);
    $user->update(['password' => 'changed-password']);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($token)->postJson('/api/v1/auth/refresh')->assertUnauthorized();
});

test('Fortify password resets invalidate previously issued JWTs', function () {
    $user = User::factory()->create();
    $token = jwtForUser($user);
    app(ResetUserPassword::class)->reset($user, ['password' => 'reset-password', 'password_confirmation' => 'reset-password']);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('tokens for deleted accounts cannot authenticate or refresh', function () {
    $user = User::factory()->create();
    $token = jwtForUser($user);
    $user->delete();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($token)->postJson('/api/v1/auth/refresh')->assertUnauthorized();
});

test('refresh cannot extend a login beyond seven days', function () {
    $user = User::factory()->create();
    Auth::guard('api')->setTTL(60 * 24 * 8);
    $token = jwtForUser($user);
    $this->travel(6)->days();
    $newToken = $this->withToken($token)->postJson('/api/v1/auth/refresh')->assertOk()->json('access_token');
    $this->travel(1)->days();
    $this->travel(1)->seconds();

    $this->withToken($newToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->withToken($newToken)->postJson('/api/v1/auth/refresh')->assertUnauthorized();
});

test('owners can create read update and delete transactions with exact satang amounts', function () {
    $category = Category::factory()->create();
    $other = User::factory()->create();
    $token = jwtForUser($category->user);

    $id = $this->withToken($token)->postJson('/api/v1/transactions', [
        'type' => 'expense', 'amount_satang' => 12550, 'category_id' => $category->id,
        'transaction_date' => '2026-11-01', 'description' => 'อาหาร', 'user_id' => $other->id,
    ])->assertCreated()->assertJsonPath('data.amount', '125.50')->json('data.id');
    $this->assertDatabaseHas('transactions', ['id' => $id, 'user_id' => $category->user_id]);

    $this->getJson('/api/v1/transactions/'.$id)->assertOk()->assertJsonPath('data.category.id', $category->id);
    $this->patchJson('/api/v1/transactions/'.$id, ['amount_satang' => 1])->assertOk()->assertJsonPath('data.amount', '0.01');
    $this->deleteJson('/api/v1/transactions/'.$id)->assertNoContent();
    $this->assertDatabaseMissing('transactions', ['id' => $id]);
});

test('transactions reject invalid amounts dates types and categories', function (array $changes, string $field) {
    $category = Category::factory()->create();
    $token = jwtForUser($category->user);

    $this->withToken($token)->postJson('/api/v1/transactions', array_replace([
        'type' => 'expense', 'amount_satang' => 100, 'category_id' => $category->id, 'transaction_date' => '2026-11-01',
    ], $changes))->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('transactions', 0);
})->with([
    'zero' => [['amount_satang' => 0], 'amount_satang'],
    'negative' => [['amount_satang' => -1], 'amount_satang'],
    'fractional' => [['amount_satang' => 1.5], 'amount_satang'],
    'excessive' => [['amount_satang' => 100000000000000], 'amount_satang'],
    'invalid date' => [['transaction_date' => '2026-02-30'], 'transaction_date'],
    'invalid type' => [['type' => 'transfer'], 'type'],
    'long description' => [['description' => str_repeat('ก', 1001)], 'description'],
]);

test('API transactions cannot use foreign or incompatible categories', function (bool $foreign) {
    $user = User::factory()->create();
    $category = $foreign ? Category::factory()->create() : Category::factory()->income()->for($user)->create();

    $this->withToken(jwtForUser($user))->postJson('/api/v1/transactions', [
        'type' => 'expense', 'amount_satang' => 100, 'category_id' => $category->id, 'transaction_date' => '2026-11-01',
    ])->assertUnprocessable()->assertJsonValidationErrors('category_id');
    $this->assertDatabaseCount('transactions', 0);
})->with(['foreign' => true, 'incompatible' => false]);

test('changing transaction type requires a matching category', function () {
    $transaction = Transaction::factory()->create();
    $income = Category::factory()->income()->for($transaction->user)->create();
    $this->withToken(jwtForUser($transaction->user));

    $this->patchJson('/api/v1/transactions/'.$transaction->id, ['type' => 'income'])
        ->assertUnprocessable()->assertJsonValidationErrors('category_id');
    $this->patchJson('/api/v1/transactions/'.$transaction->id, ['category_id' => $income->id])
        ->assertUnprocessable()->assertJsonValidationErrors('category_id');
    $this->patchJson('/api/v1/transactions/'.$transaction->id, ['type' => 'income', 'category_id' => $income->id])
        ->assertOk()->assertJsonPath('data.type', 'income')->assertJsonPath('data.category_id', $income->id);
});

test('category patches cannot introduce duplicates by changing only the type', function () {
    $category = Category::factory()->create(['name' => 'หมวดซ้ำ']);
    Category::factory()->income()->for($category->user)->create(['name' => 'หมวดซ้ำ']);

    $this->withToken(jwtForUser($category->user))->patchJson('/api/v1/categories/'.$category->id, ['type' => 'income'])
        ->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'type' => 'expense']);
});

test('category validation safely rejects array types', function () {
    $this->withToken(jwtForUser(User::factory()->create()))->postJson('/api/v1/categories', ['name' => 'หมวดใหม่', 'type' => ['income']])
        ->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->assertDatabaseCount('categories', 0);
});

test('users cannot access another users transaction through any API action', function (string $method) {
    $record = Transaction::factory()->create();
    $this->withToken(jwtForUser(User::factory()->create()))->json($method, '/api/v1/transactions/'.$record->id)
        ->assertNotFound();
    $this->assertModelExists($record);
})->with(['GET', 'PATCH', 'DELETE']);

test('users cannot access another users category through any API action', function (string $method) {
    $record = Category::factory()->create();
    $this->withToken(jwtForUser(User::factory()->create()))->json($method, '/api/v1/categories/'.$record->id)
        ->assertNotFound();
    $this->assertModelExists($record);
})->with(['GET', 'PATCH', 'DELETE']);

test('category CRUD is private and prevents unsafe changes to populated categories', function () {
    $user = User::factory()->create();
    $this->withToken(jwtForUser($user));
    $id = $this->postJson('/api/v1/categories', ['name' => ' หมวดใหม่ ', 'type' => 'expense'])
        ->assertCreated()->assertJsonPath('data.name', 'หมวดใหม่')->json('data.id');

    $this->getJson('/api/v1/categories/'.$id)->assertOk();
    $this->patchJson('/api/v1/categories/'.$id, ['name' => 'ชื่อใหม่'])->assertOk();
    $this->postJson('/api/v1/categories', ['name' => 'ชื่อใหม่', 'type' => 'expense'])->assertUnprocessable();
    $category = $user->categories()->findOrFail($id);
    Transaction::factory()->for($category)->create();
    $this->patchJson('/api/v1/categories/'.$id, ['type' => 'income'])->assertUnprocessable();
    $this->deleteJson('/api/v1/categories/'.$id)->assertUnprocessable();
    $this->assertModelExists($category);
});

test('empty categories can change type and be deleted', function () {
    $category = Category::factory()->create();
    $this->withToken(jwtForUser($category->user));

    $this->patchJson('/api/v1/categories/'.$category->id, ['type' => 'income'])->assertOk()->assertJsonPath('data.type', 'income');
    $this->deleteJson('/api/v1/categories/'.$category->id)->assertNoContent();
    $this->assertModelMissing($category);
});

test('transaction filtering pagination and summaries remain scoped to the token owner', function () {
    $category = Category::factory()->create(['name' => 'อาหาร']);
    Transaction::factory()->for($category)->count(2)->create(['amount_satang' => 10, 'transaction_date' => '2026-11-30', 'description' => 'มื้อกลางวัน']);
    Transaction::factory()->for($category)->create(['amount_satang' => 30, 'transaction_date' => '2026-10-31']);
    $income = Category::factory()->income()->for($category->user)->create();
    Transaction::factory()->for($income)->create(['amount_satang' => 100, 'transaction_date' => '2026-11-01']);
    $other = Transaction::factory()->create(['amount_satang' => 99999, 'transaction_date' => '2026-11-01']);

    $this->withToken(jwtForUser($category->user))->getJson('/api/v1/transactions?search=มื้อกลางวัน&type=expense&date_from=2026-11-01&date_to=2026-11-30&sort=asc&per_page=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/summary?month=2026-11')->assertOk()
        ->assertJsonPath('data.income_satang', 100)->assertJsonPath('data.expense_satang', 50)
        ->assertJsonPath('data.balance_satang', 50)->assertJsonPath('data.monthly_expense_satang', 20);
    $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/transactions?sort=desc;DROP%20TABLE%20users')->assertUnprocessable();
    $this->getJson('/api/v1/summary?month=2026-13')->assertUnprocessable();
});

test('API logins are rate limited', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertUnauthorized();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertTooManyRequests();
});

test('API routes do not replace the web route names', function () {
    expect(route('transactions.index', absolute: false))->toBe('/transactions');
    expect(route('api.v1.transactions.index', absolute: false))->toBe('/api/v1/transactions');
});
