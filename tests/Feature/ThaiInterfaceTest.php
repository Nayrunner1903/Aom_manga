<?php

use App\Models\User;
use Livewire\Livewire;

test('public pages display Thai headings and declare the Thai language', function (string $route, string $heading) {
    $this->get(route($route))->assertOk()->assertSee('lang="th"', false)->assertSee($heading);
})->with([
    ['home', 'เรื่องเงิน'],
    ['login', 'กลับมาเปิดสมุดกัน'],
    ['register', 'สมุดเล่มใหม่ของคุณ'],
    ['password.request', 'ลืมรหัสผ่าน'],
]);

test('account settings display Thai navigation and translated page titles', function (string $route, string $heading) {
    $this->actingAs(User::factory()->create());

    $this->get(route($route))->assertOk()->assertSee('lang="th"', false)
        ->assertSee('ตั้งค่าบัญชี')->assertSee($heading);
})->with([
    ['profile.edit', 'ข้อมูลส่วนตัว'],
    ['security.edit', 'เปลี่ยนรหัสผ่าน'],
    ['appearance.edit', 'กระดาษขาว'],
]);

test('invalid logins return a Thai error message', function () {
    $this->post(route('login.store'), ['email' => 'unknown@example.com', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง']);

    $this->assertGuest();
});

test('missing pages show a Thai error page', function () {
    $this->get('/missing-page')->assertNotFound()->assertSee('หน้านี้หายไปจากสมุด')->assertSee('กลับหน้าแรก');
});

test('registration validation returns Thai field names and messages', function () {
    $this->post(route('register.store'), ['name' => '', 'email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors([
            'name' => 'กรุณากรอกชื่อ',
            'email' => 'กรุณากรอกอีเมลให้ถูกต้อง',
            'password' => 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร',
        ]);

    $this->assertDatabaseCount('users', 0);
});

test('password settings return a Thai error without changing the password', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::settings.security')->set('current_password', 'wrong-password')
        ->set('password', 'new-password')->set('password_confirmation', 'new-password')->call('updatePassword')
        ->assertHasErrors(['current_password'])->assertSee('รหัสผ่านปัจจุบันไม่ถูกต้อง');

    expect($user->fresh()->password)->toBe($user->password);
});
