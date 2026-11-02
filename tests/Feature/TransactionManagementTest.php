<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('guests must log in before viewing financial pages', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['transactions.index', 'transactions.create', 'categories.index']);

test('authenticated users can view transaction pages', function () {
    $transaction = Transaction::factory()->create();
    $this->actingAs($transaction->user);

    $this->get(route('transactions.index'))->assertOk();
    $this->get(route('transactions.create'))->assertOk();
    $this->get(route('transactions.edit', $transaction))->assertOk();
});

test('guests cannot open the transaction editor', function () {
    $this->get(route('transactions.edit', 999))->assertRedirect(route('login'));
});

test('users can create income and expense transactions with exact amounts', function (string $type, string $amount, int $satang) {
    $category = Category::factory()->create(['type' => $type]);
    $this->actingAs($category->user);

    Livewire::test('pages::transactions.form')
        ->set('type', $type)->set('categoryId', (string) $category->id)
        ->set('amount', $amount)->set('transactionDate', '2026-11-01')
        ->set('description', 'รายการทดสอบ')->call('save')
        ->assertHasNoErrors()->assertRedirect(route('transactions.index'));

    $this->assertDatabaseHas('transactions', [
        'user_id' => $category->user_id, 'category_id' => $category->id,
        'type' => $type, 'amount_satang' => $satang,
        'transaction_date' => '2026-11-01', 'description' => 'รายการทดสอบ',
    ]);
})->with([
    'expense' => ['expense', '125.50', 12550],
    'income' => ['income', '1000', 100000],
    'one satang' => ['expense', '0.01', 1],
    'one decimal place' => ['income', '1.2', 120],
    'maximum supported amount' => ['expense', '999999999999.99', 99999999999999],
]);

test('invalid amounts are rejected without saving transactions', function (string $amount, string $message) {
    $category = Category::factory()->create();
    $this->actingAs($category->user);

    Livewire::test('pages::transactions.form')
        ->set('categoryId', (string) $category->id)->set('amount', $amount)
        ->call('save')->assertHasErrors(['amount'])->assertSee($message);

    $this->assertDatabaseCount('transactions', 0);
})->with([
    'zero' => ['0.00', 'จำนวนเงินต้องมากกว่า 0 บาท'],
    'negative' => ['-1', 'กรุณากรอกจำนวนเงินไม่เกิน 12 หลัก และทศนิยมไม่เกิน 2 ตำแหน่ง'],
    'fractional satang' => ['1.001', 'กรุณากรอกจำนวนเงินไม่เกิน 12 หลัก และทศนิยมไม่เกิน 2 ตำแหน่ง'],
    'scientific notation' => ['1e3', 'กรุณากรอกจำนวนเงินไม่เกิน 12 หลัก และทศนิยมไม่เกิน 2 ตำแหน่ง'],
    'too large' => ['1000000000000', 'กรุณากรอกจำนวนเงินไม่เกิน 12 หลัก และทศนิยมไม่เกิน 2 ตำแหน่ง'],
    'missing' => ['', 'กรุณากรอกจำนวนเงินไม่เกิน 12 หลัก และทศนิยมไม่เกิน 2 ตำแหน่ง'],
]);

test('invalid transaction fields are rejected', function (string $field, string $value) {
    $category = Category::factory()->create();
    $this->actingAs($category->user);

    Livewire::test('pages::transactions.form')
        ->set('amount', '10')->set('categoryId', (string) $category->id)
        ->set($field, $value)->call('save')->assertHasErrors([$field]);

    $this->assertDatabaseCount('transactions', 0);
})->with([
    'unknown type' => ['type', 'transfer'],
    'missing category' => ['categoryId', ''],
    'invalid date' => ['transactionDate', '2026-02-30'],
    'missing date' => ['transactionDate', ''],
    'date before supported range' => ['transactionDate', '1899-12-31'],
    'long description' => ['description', str_repeat('ก', 1001)],
]);

test('transactions cannot use another users category or a category of the wrong type', function (bool $otherUser) {
    $user = User::factory()->create();
    $category = $otherUser
        ? Category::factory()->create(['name' => 'หมวดที่ห้ามเลือก'])
        : Category::factory()->income()->for($user)->create(['name' => 'หมวดที่ห้ามเลือก']);
    $this->actingAs($user);

    Livewire::test('pages::transactions.form')
        ->assertDontSee($category->name)->set('amount', '10')
        ->set('categoryId', (string) $category->id)->call('save')
        ->assertHasErrors(['categoryId'])->assertSee('กรุณาเลือกหมวดหมู่ของคุณให้ตรงกับประเภทรายการ');

    $this->assertDatabaseCount('transactions', 0);
})->with(['another user' => true, 'wrong type' => false]);

test('owners can edit transaction fields including type and category', function () {
    $transaction = Transaction::factory()->create(['amount_satang' => 12550]);
    $category = Category::factory()->income()->for($transaction->user)->create();
    $this->actingAs($transaction->user);

    Livewire::test('pages::transactions.form', ['transaction' => $transaction->id])
        ->assertSet('amount', '125.50')->set('type', 'income')
        ->assertSet('categoryId', '')->set('categoryId', (string) $category->id)
        ->set('amount', '0.10')->set('description', '')
        ->set('transactionDate', '2026-10-01')->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id, 'user_id' => $transaction->user_id,
        'type' => 'income', 'category_id' => $category->id, 'amount_satang' => 10,
        'description' => null, 'transaction_date' => '2026-10-01',
    ]);
});

test('users cannot open another users transaction editor', function () {
    $transaction = Transaction::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('transactions.edit', $transaction))->assertNotFound();

    $this->assertModelExists($transaction);
});

test('transaction identities cannot be changed from the browser', function () {
    $transaction = Transaction::factory()->create();
    $this->actingAs($transaction->user);

    expect(fn () => Livewire::test('pages::transactions.form', ['transaction' => $transaction->id])
        ->set('transactionId', $transaction->id + 1))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('saving rechecks the current transaction owner', function () {
    $transaction = Transaction::factory()->create(['description' => 'ต้นฉบับ']);
    $this->actingAs($transaction->user);
    $component = Livewire::test('pages::transactions.form', ['transaction' => $transaction->id]);
    $this->actingAs(User::factory()->create());

    $component->set('description', 'เปลี่ยนแล้ว')->call('save')->assertNotFound();

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'description' => 'ต้นฉบับ']);
});

test('owners can delete transactions', function () {
    $transaction = Transaction::factory()->create();
    $this->actingAs($transaction->user);

    Livewire::test('pages::transactions.index')->assertSee('ยืนยันการลบรายการนี้?', false)
        ->call('deleteTransaction', $transaction->id)->assertHasNoErrors();

    $this->assertModelMissing($transaction);
});

test('users cannot delete another users transactions', function () {
    $transaction = Transaction::factory()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::transactions.index')->call('deleteTransaction', $transaction->id)->assertNotFound();

    $this->assertModelExists($transaction);
});

test('transaction search remains scoped to the current user', function () {
    $transaction = Transaction::factory()->create(['description' => 'รายการของฉัน']);
    $other = Transaction::factory()->create(['description' => 'ข้อมูลลับของคนอื่น']);
    $this->actingAs($transaction->user);

    Livewire::test('pages::transactions.index')->assertSee('รายการของฉัน')->assertDontSee($other->description)
        ->set('search', $other->category->name)->call('applyFilters')->assertDontSee($other->description)
        ->set('search', "%' OR 1=1 --")->call('applyFilters')->assertDontSee($other->description);
});

test('filters include matching dates and exclude other types categories and dates', function () {
    $category = Category::factory()->create(['name' => 'อาหาร']);
    Transaction::factory()->for($category)->create(['description' => 'ขอบแรก', 'transaction_date' => '2026-11-01']);
    Transaction::factory()->for($category)->create(['description' => 'ขอบท้าย', 'transaction_date' => '2026-11-30']);
    Transaction::factory()->for($category)->create(['description' => 'นอกช่วง', 'transaction_date' => '2026-10-31']);
    Transaction::factory()->for($category)->create(['description' => 'หลังช่วง', 'transaction_date' => '2026-12-01']);
    $incomeCategory = Category::factory()->income()->for($category->user)->create();
    Transaction::factory()->for($incomeCategory)->create(['description' => 'รายรับอื่น', 'transaction_date' => '2026-11-10']);
    $otherCategory = Category::factory()->for($category->user)->create();
    Transaction::factory()->for($otherCategory)->create(['description' => 'หมวดอื่น', 'transaction_date' => '2026-11-10']);
    $this->actingAs($category->user);

    Livewire::test('pages::transactions.index')->set('type', 'expense')
        ->set('categoryId', (string) $category->id)->set('dateFrom', '2026-11-01')->set('dateTo', '2026-11-30')
        ->call('applyFilters')->assertHasNoErrors()->assertSee('ขอบแรก')->assertSee('ขอบท้าย')
        ->assertDontSee('นอกช่วง')->assertDontSee('หลังช่วง')->assertDontSee('รายรับอื่น')->assertDontSee('หมวดอื่น');
});

test('the type filter works without selecting a category', function () {
    $category = Category::factory()->create();
    Transaction::factory()->for($category)->create(['description' => 'ค่าอาหาร']);
    $incomeCategory = Category::factory()->income()->for($category->user)->create();
    Transaction::factory()->for($incomeCategory)->create(['description' => 'เงินเดือนเข้า']);
    $this->actingAs($category->user);

    Livewire::test('pages::transactions.index')->set('type', 'income')->call('applyFilters')
        ->assertSee('เงินเดือนเข้า')->assertDontSee('ค่าอาหาร');
});

test('the category filter works without selecting a type', function () {
    $category = Category::factory()->create();
    Transaction::factory()->for($category)->create(['description' => 'หมวดที่เลือก']);
    $otherCategory = Category::factory()->for($category->user)->create();
    Transaction::factory()->for($otherCategory)->create(['description' => 'หมวดไม่เลือก']);
    $this->actingAs($category->user);

    Livewire::test('pages::transactions.index')->set('categoryId', (string) $category->id)->call('applyFilters')
        ->assertSee('หมวดที่เลือก')->assertDontSee('หมวดไม่เลือก');
});

test('users can search by description or category name', function (string $search) {
    $category = Category::factory()->create(['name' => 'อาหาร']);
    Transaction::factory()->for($category)->create(['description' => 'มื้อกลางวัน']);
    $otherCategory = Category::factory()->for($category->user)->create(['name' => 'การเดินทาง']);
    Transaction::factory()->for($otherCategory)->create(['description' => 'รถไฟ']);
    $this->actingAs($category->user);

    Livewire::test('pages::transactions.index')->set('search', $search)->call('applyFilters')
        ->assertSee('มื้อกลางวัน')->assertDontSee('รถไฟ');
})->with(['มื้อกลางวัน', 'อาหาร']);

test('transactions paginate sort and reset the page when filters change', function () {
    $category = Category::factory()->create();
    Transaction::factory()->for($category)->count(16)->sequence(fn ($sequence) => [
        'description' => 'รายการลำดับ'.str_pad((string) ($sequence->index + 1), 2, '0', STR_PAD_LEFT),
        'transaction_date' => sprintf('2026-11-%02d', $sequence->index + 1),
    ])->create();
    $this->actingAs($category->user);

    Livewire::test('pages::transactions.index')->assertSeeHtmlInOrder(['รายการลำดับ16', 'รายการลำดับ15'])
        ->assertDontSee('รายการลำดับ01')->call('nextPage')->assertSee('รายการลำดับ01')
        ->set('sort', 'asc')->call('applyFilters')->assertSeeHtmlInOrder(['รายการลำดับ01', 'รายการลำดับ02'])
        ->set('search', 'รายการลำดับ16')->call('applyFilters')->assertSee('รายการลำดับ16')
        ->call('clearFilters')->assertSet('search', '')->assertSet('sort', 'desc');
});

test('filter validation rejects reversed dates and untrusted sort values', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::transactions.index')->set('dateFrom', '2026-11-30')->set('dateTo', '2026-11-01')
        ->set('sort', 'desc; DROP TABLE users')->call('applyFilters')
        ->assertHasErrors(['dateTo', 'sort'])->assertSee('วันที่สิ้นสุดต้องถูกต้องและไม่น้อยกว่าวันที่เริ่มต้น');

    $this->assertDatabaseCount('users', 1);
});

test('transaction output escapes descriptions and category names', function () {
    $category = Category::factory()->create(['name' => '<script>alert("category")</script>']);
    $transaction = Transaction::factory()->for($category)->create(['description' => '<script>alert("description")</script>', 'amount_satang' => 12550]);
    $this->actingAs($transaction->user);

    Livewire::test('pages::transactions.index')->assertSee($transaction->description)
        ->assertDontSee($transaction->description, false)->assertSee($category->name)->assertDontSee($category->name, false)
        ->assertSee('125.50 บาท');
});
