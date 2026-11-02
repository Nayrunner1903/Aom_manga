<?php

use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

test('a registered user can record edit and delete transactions and see updated balances', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00', 'Asia/Bangkok'));

    $this->post(route('register.store'), [
        'name' => 'ผู้ทดสอบ', 'email' => 'workflow@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
    $user = User::query()->sole();
    $income = $user->categories()->where('type', 'income')->where('name', 'เงินเดือน')->sole();
    $expense = $user->categories()->where('type', 'expense')->where('name', 'อาหาร')->sole();

    Livewire::test('pages::transactions.form')->set('type', 'income')
        ->set('categoryId', (string) $income->id)->set('amount', '1000.10')
        ->call('save')->assertHasNoErrors()->assertRedirect(route('transactions.index'));
    Livewire::test('pages::transactions.form')->set('categoryId', (string) $expense->id)
        ->set('amount', '125.55')->call('save')->assertHasNoErrors();
    expect(Livewire::test('pages::dashboard')->get('summary')['balance'])->toBe(87455);

    $transaction = $user->transactions()->where('type', 'expense')->sole();
    Livewire::test('pages::transactions.form', ['transaction' => $transaction->id])
        ->set('amount', '20.05')->call('save')->assertHasNoErrors();
    expect(Livewire::test('pages::dashboard')->get('summary')['monthlyBalance'])->toBe(98005);

    Livewire::test('pages::transactions.index')->call('deleteTransaction', $transaction->id)->assertHasNoErrors();
    expect(Livewire::test('pages::dashboard')->get('summary')['balance'])->toBe(100010);
    $this->assertModelMissing($transaction);

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('deleting a populated account removes only that accounts financial data', function () {
    $transaction = Transaction::factory()->create();
    $category = $transaction->category;
    $user = $transaction->user;
    $other = Transaction::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.delete-user-modal')->set('password', 'password')
        ->call('deleteUser')->assertHasNoErrors()->assertRedirect('/');

    $this->assertGuest();
    $this->assertModelMissing($user);
    $this->assertModelMissing($category);
    $this->assertModelMissing($transaction);
    $this->assertModelExists($other);
    $this->assertModelExists($other->category);
    $this->assertModelExists($other->user);
});
