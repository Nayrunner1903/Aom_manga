<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 00:30:00', 'Asia/Bangkok'));
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('an empty dashboard shows zero totals and six empty months', function () {
    $this->actingAs(User::factory()->create());

    $dashboard = Livewire::test('pages::dashboard')->assertSet('month', '2026-11')
        ->assertSee('พฤศจิกายน 2569')->assertSee('0.00 บาท')
        ->assertSee('เดือนนี้ยังไม่มีรายจ่าย')->assertSee('ยังไม่มีรายการ');

    expect($dashboard->get('summary'))->toBe([
        'income' => 0, 'expense' => 0, 'balance' => 0,
        'monthlyIncome' => 0, 'monthlyExpense' => 0, 'monthlyBalance' => 0,
    ]);
    expect(array_column($dashboard->get('trends'), 'month'))->toBe(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11']);
    expect(array_column($dashboard->get('trends'), 'income'))->toBe([0, 0, 0, 0, 0, 0]);
    expect($dashboard->get('expenseCategories'))->toBe([]);
});

test('financial summaries and charts use exact amounts from the current user only', function () {
    $income = Category::factory()->income()->create();
    $expense = Category::factory()->for($income->user)->create(['name' => 'อาหาร']);
    Transaction::factory()->for($income)->create(['amount_satang' => 10001, 'transaction_date' => '2026-11-01']);
    Transaction::factory()->for($income)->create(['amount_satang' => 20, 'transaction_date' => '2026-11-30']);
    Transaction::factory()->for($income)->create(['amount_satang' => 700, 'transaction_date' => '2026-12-01']);
    Transaction::factory()->for($expense)->create(['amount_satang' => 5025, 'transaction_date' => '2026-11-30']);
    Transaction::factory()->for($expense)->create(['amount_satang' => 100, 'transaction_date' => '2026-10-31']);
    $otherCategory = Category::factory()->create(['name' => 'หมวดลับเฉพาะบัญชีอื่น']);
    $other = Transaction::factory()->for($otherCategory)->create(['amount_satang' => 99999, 'description' => 'รายการลับของคนอื่น', 'transaction_date' => '2026-11-15']);
    $this->actingAs($income->user);

    $dashboard = Livewire::test('pages::dashboard')->assertSee('107.21 บาท')->assertDontSee($other->description)->assertDontSee($other->category->name);

    expect($dashboard->get('summary'))->toBe([
        'income' => 10721, 'expense' => 5125, 'balance' => 5596,
        'monthlyIncome' => 10021, 'monthlyExpense' => 5025, 'monthlyBalance' => 4996,
    ]);
    expect($dashboard->get('expenseCategories'))->toBe([['id' => $expense->id, 'name' => 'อาหาร', 'satang' => 5025]]);
    expect(array_column($dashboard->get('trends'), 'income'))->toBe([0, 0, 0, 0, 0, 10021]);
    expect(array_column($dashboard->get('trends'), 'expense'))->toBe([0, 0, 0, 0, 100, 5025]);
    expect($dashboard->get('recentTransactions')->pluck('user_id')->unique()->all())->toBe([$income->user_id]);
});

test('negative balances keep their sign including amounts smaller than one baht', function (int $expense, string $formatted) {
    $category = Category::factory()->create();
    Transaction::factory()->for($category)->create(['amount_satang' => $expense, 'transaction_date' => '2026-11-01']);
    $this->actingAs($category->user);

    $dashboard = Livewire::test('pages::dashboard')->assertSee($formatted);

    expect($dashboard->get('summary')['balance'])->toBe(-$expense);
    expect($dashboard->get('summary')['monthlyBalance'])->toBe(-$expense);
})->with(['one satang' => [1, '-0.01 บาท'], 'ten baht fifty' => [1050, '-10.50 บาท']]);

test('selecting a month updates monthly totals categories and trends but not lifetime totals', function () {
    $category = Category::factory()->create(['name' => 'อาหาร']);
    Transaction::factory()->for($category)->create(['amount_satang' => 12550, 'transaction_date' => '2024-02-29']);
    Transaction::factory()->for($category)->create(['amount_satang' => 100, 'transaction_date' => '2024-03-01']);
    Transaction::factory()->for($category)->create(['amount_satang' => 200, 'transaction_date' => '2023-09-01']);
    Transaction::factory()->for($category)->create(['amount_satang' => 300, 'transaction_date' => '2023-08-31']);
    $this->actingAs($category->user);

    $dashboard = Livewire::test('pages::dashboard')->set('monthInput', '2024-02')->call('changeMonth')
        ->assertHasNoErrors()->assertSee('กุมภาพันธ์ 2567');

    expect($dashboard->get('summary')['expense'])->toBe(13150);
    expect($dashboard->get('summary')['monthlyExpense'])->toBe(12550);
    expect($dashboard->get('expenseCategories')[0]['satang'])->toBe(12550);
    expect(array_column($dashboard->get('trends'), 'month'))->toBe(['2023-09', '2023-10', '2023-11', '2023-12', '2024-01', '2024-02']);
    expect(array_column($dashboard->get('trends'), 'expense'))->toBe([200, 0, 0, 0, 0, 12550]);
});

test('invalid month selections show an error and retain the last valid report', function (string $month) {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::dashboard')->set('monthInput', $month)->call('changeMonth')
        ->assertHasErrors(['monthInput'])->assertSet('month', '2026-11')
        ->assertSee('กรุณาเลือกเดือนให้ถูกต้อง ระหว่างปี ค.ศ. 1900–9999');
})->with(['', '2026-13', '2026-1', '1899-12', '10000-01', "2026-11' OR 1=1"]);

test('expense breakdown groups by category and excludes income and unused categories', function () {
    $category = Category::factory()->create(['name' => 'อาหาร']);
    $transport = Category::factory()->for($category->user)->create(['name' => 'เดินทาง']);
    Category::factory()->for($category->user)->create(['name' => 'ไม่ได้ใช้']);
    $income = Category::factory()->income()->for($category->user)->create();
    Transaction::factory()->for($category)->count(2)->create(['amount_satang' => 100, 'transaction_date' => '2026-11-10']);
    Transaction::factory()->for($transport)->create(['amount_satang' => 300, 'transaction_date' => '2026-11-10']);
    Transaction::factory()->for($income)->create(['amount_satang' => 99999, 'transaction_date' => '2026-11-10']);
    $this->actingAs($category->user);

    $dashboard = Livewire::test('pages::dashboard')->assertSee('60%')->assertSee('40%');

    expect($dashboard->get('expenseCategories'))->toBe([
        ['id' => $transport->id, 'name' => 'เดินทาง', 'satang' => 300],
        ['id' => $category->id, 'name' => 'อาหาร', 'satang' => 200],
    ]);
});

test('recent transactions show only the latest five ordered by date and then id', function () {
    $category = Category::factory()->create();
    $transactions = Transaction::factory()->for($category)->count(6)->sequence(
        ['transaction_date' => '2026-10-01'],
        ['transaction_date' => '2026-10-03'],
        ['transaction_date' => '2026-10-02'],
        ['transaction_date' => '2026-10-04'],
        ['transaction_date' => '2026-10-05'],
        ['transaction_date' => '2026-10-05'],
    )->create();
    $this->actingAs($category->user);

    $dashboard = Livewire::test('pages::dashboard');

    expect($dashboard->get('recentTransactions')->modelKeys())->toBe([
        $transactions[5]->id, $transactions[4]->id, $transactions[3]->id, $transactions[1]->id, $transactions[2]->id,
    ]);
    expect($dashboard->get('recentTransactions')->every(fn (Transaction $transaction): bool => $transaction->relationLoaded('category')))->toBeTrue();
});

test('dashboard escapes category names and descriptions', function () {
    $category = Category::factory()->create(['name' => '<script>alert("category")</script>']);
    $transaction = Transaction::factory()->for($category)->create(['description' => '<script>alert("description")</script>', 'transaction_date' => '2026-11-01']);
    $this->actingAs($category->user);

    Livewire::test('pages::dashboard')->assertSee($category->name)->assertDontSee($category->name, false)
        ->assertSee($transaction->description)->assertDontSee($transaction->description, false);
});
