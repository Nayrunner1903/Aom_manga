<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('user relationships return only their own financial records', function () {
    $transaction = Transaction::factory()->create();
    $other = Transaction::factory()->create();

    expect($transaction->user->transactions->modelKeys())->toBe([$transaction->id]);
    expect($transaction->user->categories->modelKeys())->toBe([$transaction->category_id]);
    expect($transaction->category->transactions->modelKeys())->toBe([$transaction->id]);
    expect($other->user->is($transaction->user))->toBeFalse();
});

test('satang amounts retain exact values when persisted and summed', function () {
    $category = Category::factory()->income()->create();
    Transaction::factory()->for($category)->create(['amount_satang' => 10]);
    $transaction = Transaction::factory()->for($category)->create([
        'amount_satang' => 20,
        'transaction_date' => '2026-11-01',
        'description' => null,
    ]);

    expect($transaction->fresh()->amount_satang)->toBe(20);
    expect($category->transactions()->sum('amount_satang'))->toBe(30);
    expect($transaction->fresh()->transaction_date->toDateString())->toBe('2026-11-01');
    expect($transaction->type)->toBe('income');
});

test('database rejects nonpositive or fractional satang amounts', function (int|float $amount) {
    $transaction = Transaction::factory()->create();

    expect(fn () => DB::table('transactions')->where('id', $transaction->id)->update([
        'amount_satang' => $amount,
    ]))->toThrow(QueryException::class);

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount_satang' => $transaction->amount_satang]);
})->with(['zero' => 0, 'negative' => -1, 'fractional' => 1.5]);

test('database rejects a category belonging to another user', function () {
    $transaction = Transaction::factory()->create();
    $originalCategoryId = $transaction->category_id;
    $otherCategory = Category::factory()->create();

    expect(fn () => $transaction->update(['category_id' => $otherCategory->id]))->toThrow(QueryException::class);

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'category_id' => $originalCategoryId]);
});

test('database rejects a transaction whose type does not match its category', function () {
    $transaction = Transaction::factory()->create();

    expect(fn () => $transaction->update(['type' => 'income']))->toThrow(QueryException::class);

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'type' => 'expense']);
});

test('categories containing transactions cannot be deleted or have their type changed', function () {
    $transaction = Transaction::factory()->create();
    $category = $transaction->category;

    expect(fn () => $category->delete())->toThrow(QueryException::class);
    expect(fn () => $category->update(['type' => 'income']))->toThrow(QueryException::class);

    $this->assertModelExists($category);
    $this->assertModelExists($transaction);
});

test('empty categories can be deleted', function () {
    $category = Category::factory()->create();

    $category->delete();

    $this->assertModelMissing($category);
});

test('deleting an account removes its financial records without affecting other users', function () {
    $transaction = Transaction::factory()->create();
    $category = $transaction->category;
    $otherTransaction = Transaction::factory()->create();

    $transaction->user->delete();

    $this->assertModelMissing($transaction);
    $this->assertModelMissing($category);
    $this->assertModelExists($otherTransaction);
    $this->assertModelExists($otherTransaction->category);
});

test('default categories are private to each user and seeding is repeatable', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $seeder = new CategorySeeder;

    $seeder->seedFor($user);
    $seeder->seedFor($otherUser);
    $seeder->seedFor($user);

    expect($user->categories()->count())->toBe(11);
    expect($otherUser->categories()->count())->toBe(11);
    expect($user->categories()->where('type', 'income')->count())->toBe(4);
    expect($user->categories()->where('type', 'expense')->count())->toBe(7);
    expect($user->categories()->where('name', 'อื่น ๆ')->count())->toBe(2);
    expect($user->categories->modelKeys())->not->toContain(...$otherUser->categories->modelKeys());
});

test('category names are unique per user and type', function () {
    $category = Category::factory()->create(['name' => 'อาหาร']);

    expect(fn () => $category->user->categories()->create(['name' => 'อาหาร', 'type' => 'expense']))
        ->toThrow(QueryException::class);
});

test('ownership cannot be reassigned through mass assignment', function () {
    $transaction = Transaction::factory()->create();
    $originalUserId = $transaction->user_id;
    $otherUser = User::factory()->create();

    $transaction->update(['user_id' => $otherUser->id, 'description' => 'ทดสอบ']);
    $transaction->category->update(['user_id' => $otherUser->id, 'name' => 'อาหาร']);

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'user_id' => $originalUserId]);
    $this->assertDatabaseHas('categories', ['id' => $transaction->category_id, 'user_id' => $originalUserId]);
});
