@php
    $code = trim($__env->yieldContent('code', '500'));
    $messages = [
        '401' => ['กรุณาเข้าสู่ระบบ', 'เปิดสมุดของคุณด้วยการเข้าสู่ระบบก่อน'],
        '403' => ['เปิดหน้านี้ไม่ได้', 'บัญชีของคุณไม่มีสิทธิ์เปิดหน้านี้'],
        '404' => ['หน้านี้หายไปจากสมุด', 'ไม่พบหน้าหรือรายการที่คุณกำลังหา ลองกลับไปเริ่มจากหน้าแรก'],
        '419' => ['หน้านี้หมดเวลาแล้ว', 'กรุณากลับไปเปิดหน้าเดิมใหม่ แล้วลองทำรายการอีกครั้ง'],
        '429' => ['พักสักครู่แล้วลองใหม่', 'มีคำขอมากเกินไปในช่วงนี้ กรุณารอสักครู่'],
        '500' => ['สมุดสะดุดนิดหน่อย', 'เกิดข้อผิดพลาดในการทำงาน กรุณาลองอีกครั้งภายหลัง'],
        '503' => ['ขอพักสมุดสักครู่', 'ระบบยังไม่พร้อมใช้งาน กรุณากลับมาใหม่ภายหลัง'],
    ];
    [$heading, $description] = $messages[$code] ?? $messages['500'];
@endphp
<!DOCTYPE html>
<html lang="th">
    <head>@include('partials.head', ['title' => $heading])</head>
    <body class="manga-app min-h-screen">
        <main class="mx-auto flex min-h-svh max-w-xl items-center p-6">
            <section class="manga-auth-panel w-full space-y-6">
                <span class="manga-kicker">หน้า {{ $code }}</span>
                <h1 class="text-3xl font-black">{{ $heading }}</h1>
                <p>{{ $description }}</p>
                <flux:button :href="route('home')" variant="primary">กลับหน้าแรก</flux:button>
            </section>
        </main>
        @fluxScripts
    </body>
</html>
