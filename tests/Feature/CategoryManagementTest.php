<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('users can view their own categories without seeing other users categories', function () {
    $category = Category::factory()->create(['name' => 'หมวดของฉัน']);
    $other = Category::factory()->create(['name' => 'หมวดลับ']);
    $this->actingAs($category->user);

    $this->get(route('categories.index'))->assertOk()->assertSee($category->name)->assertDontSee($other->name);
});

test('users can create their own categories', function (string $type) {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::categories.index')->set('name', '  หมวดใหม่  ')->set('type', $type)
        ->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'name' => 'หมวดใหม่', 'type' => $type]);
})->with(['income', 'expense']);

test('category validation rejects empty names long names and invalid types', function (string $field, string $value) {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::categories.index')->set('name', 'หมวดใหม่')->set($field, $value)
        ->call('save')->assertHasErrors([$field]);

    $this->assertDatabaseCount('categories', 0);
})->with([
    'blank name' => ['name', '   '],
    'long name' => ['name', str_repeat('ก', 101)],
    'unknown type' => ['type', 'transfer'],
]);

test('duplicate category names are rejected only for the same user and type', function () {
    $category = Category::factory()->create(['name' => 'อาหาร']);
    Category::factory()->create(['name' => 'หมวดส่วนตัว']);
    $this->actingAs($category->user);

    Livewire::test('pages::categories.index')->set('name', 'อาหาร')->call('save')
        ->assertHasErrors(['name'])->assertSee('คุณมีหมวดหมู่ชื่อนี้ในประเภทเดียวกันแล้ว')
        ->set('type', 'income')->call('save')->assertHasNoErrors()
        ->set('name', 'หมวดส่วนตัว')->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas('categories', ['user_id' => $category->user_id, 'name' => 'อาหาร', 'type' => 'income']);
    $this->assertDatabaseHas('categories', ['user_id' => $category->user_id, 'name' => 'หมวดส่วนตัว']);
});

test('owners can rename used categories without changing their transactions', function () {
    $transaction = Transaction::factory()->create();
    $this->actingAs($transaction->user);

    Livewire::test('pages::categories.index')->call('editCategory', $transaction->category_id)
        ->set('name', 'ชื่อใหม่')->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas('categories', ['id' => $transaction->category_id, 'name' => 'ชื่อใหม่']);
    $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'category_id' => $transaction->category_id]);
});

test('used categories cannot change type or be deleted', function () {
    $transaction = Transaction::factory()->create();
    $this->actingAs($transaction->user);

    Livewire::test('pages::categories.index')->call('editCategory', $transaction->category_id)
        ->set('type', 'income')->call('save')->assertHasErrors(['type'])
        ->assertSee('เปลี่ยนประเภทไม่ได้ เพราะหมวดหมู่นี้มีรายการอยู่แล้ว')
        ->call('deleteCategory', $transaction->category_id)->assertHasErrors(['category'])
        ->assertSee('ลบไม่ได้ เพราะหมวดหมู่นี้มีรายการอยู่ กรุณาย้ายหรือลบรายการก่อน');

    $this->assertDatabaseHas('categories', ['id' => $transaction->category_id, 'type' => 'expense']);
    $this->assertModelExists($transaction);
});

test('owners can change an empty category type and delete it', function () {
    $category = Category::factory()->create();
    $this->actingAs($category->user);

    Livewire::test('pages::categories.index')->call('editCategory', $category->id)
        ->set('type', 'income')->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas('categories', ['id' => $category->id, 'type' => 'income']);

    Livewire::test('pages::categories.index')->call('editCategory', $category->id)
        ->call('deleteCategory', $category->id)->assertHasNoErrors()->assertSet('editingId', null);

    $this->assertModelMissing($category);
});

test('users cannot edit or delete another users category', function (string $action) {
    $category = Category::factory()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::categories.index')->call($action, $category->id)->assertNotFound();

    $this->assertModelExists($category);
})->with(['editCategory', 'deleteCategory']);

test('saving a category rechecks ownership', function () {
    $category = Category::factory()->create(['name' => 'ต้นฉบับ']);
    $this->actingAs($category->user);
    $component = Livewire::test('pages::categories.index')->call('editCategory', $category->id);
    $this->actingAs(User::factory()->create());

    $component->set('name', 'ชื่อใหม่')->call('save')->assertNotFound();

    $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'ต้นฉบับ']);
});

test('category identities cannot be changed from the browser', function () {
    $category = Category::factory()->create();
    $this->actingAs($category->user);

    expect(fn () => Livewire::test('pages::categories.index')->call('editCategory', $category->id)
        ->set('editingId', $category->id + 1))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('category output escapes names', function () {
    $category = Category::factory()->create(['name' => '<script>alert("xss")</script>']);
    $this->actingAs($category->user);

    Livewire::test('pages::categories.index')->assertSee($category->name)->assertDontSee($category->name, false);
});
