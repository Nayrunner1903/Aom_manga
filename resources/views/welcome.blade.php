<!DOCTYPE html>
<html lang="th">
    <head>@include('partials.head', ['title' => 'เรื่องเงินของเรา'])</head>
    <body class="manga-app min-h-screen">
        <div class="manga-cover">
            <nav class="manga-cover-nav" aria-label="เมนูหลัก">
                <a href="{{ route('home') }}" class="flex items-center gap-3 text-2xl font-black"><x-app-logo-icon class="size-10" />สมุดเงิน</a>
                <div class="flex gap-3">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" wire:navigate>เปิดสมุดของฉัน</flux:button>
                    @else
                        <flux:button :href="route('login')" wire:navigate>เข้าสู่ระบบ</flux:button>
                        <flux:button :href="route('register')" variant="primary" wire:navigate>สมัครสมาชิก</flux:button>
                    @endauth
                </div>
            </nav>
            <main>
                <section class="manga-cover-hero" aria-labelledby="cover-title">
                    <div>
                        <span class="manga-kicker">สมุดบันทึกการเงินฉบับของคุณ</span>
                        <h1 id="cover-title">เรื่องเงิน<br>เขียนให้ชัด!</h1>
                        <p class="mb-6 max-w-md text-lg">รายรับมาจากไหน รายจ่ายไปกับอะไร จดไว้ทีละรายการ แล้วมองภาพรวมชีวิตการเงินได้ง่ายขึ้น</p>
                        <flux:button :href="auth()->check() ? route('dashboard') : route('register')" variant="primary" wire:navigate>เริ่มจดเรื่องของเรา</flux:button>
                        <p class="mt-5 text-sm">เรียบง่าย เป็นส่วนตัว เก็บข้อมูลไว้ในเครื่อง</p>
                    </div>
                    <div class="manga-cover-art" aria-hidden="true">
                        <svg viewBox="0 0 300 320" fill="none" stroke="currentColor" stroke-width="3">
                            <path d="m25 25 10 10m220-10-10 10M15 150h22m230 0h18M40 290l15-15m195 15-15-15" />
                            <path d="M60 45h165l18 20v215H60z" fill="var(--paper)" stroke-width="5" />
                            <path d="M85 45v235M225 45v25h18M51 85h20M51 135h20M51 185h20M51 235h20" stroke-width="5" />
                            <path d="M108 95h90M108 117h70M108 160h90M108 182h90M108 204h90M108 245h35" />
                            <circle cx="217" cy="233" r="45" fill="var(--paper)" stroke-width="5" />
                            <path d="m195 233 16 16 28-32" stroke-width="6" />
                        </svg>
                        <p class="text-center text-lg font-black">ทีละบาท ทีละหน้า</p>
                    </div>
                </section>
                <div class="manga-cover-features">
                    <article><span class="manga-kicker">บทที่ 01</span><h2>จดให้ครบ</h2><p>เพิ่มรายรับและรายจ่าย พร้อมหมวดหมู่ วันที่ และรายละเอียดที่อยากจำ</p></article>
                    <article><span class="manga-kicker">บทที่ 02</span><h2>เห็นภาพชัด</h2><p>ดูยอดรวม กราฟรายเดือน และหมวดหมู่ที่ใช้เงินไปมากที่สุด</p></article>
                    <article><span class="manga-kicker">บทที่ 03</span><h2>ย้อนดูได้เสมอ</h2><p>ค้นหาย้อนหลัง กรองตามช่วงวันที่ และแก้ไขเมื่อจดผิด</p></article>
                </div>
            </main>
            <footer class="mt-10 border-t-2 border-current pt-5 text-sm">สมุดเงิน · เรื่องราวของทุกบาท ในลายเส้นขาวดำ</footer>
        </div>
        @fluxScripts
    </body>
</html>
