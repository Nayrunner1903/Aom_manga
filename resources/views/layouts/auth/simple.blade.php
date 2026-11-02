<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="manga-app min-h-screen antialiased">
        <main class="manga-auth-shell">
            <aside class="manga-auth-intro" aria-label="สมุดเงิน">
                <span class="manga-kicker">เรื่องเงินของเรา เริ่มที่หน้านี้</span>
                <h1>ทุกบาท<br>มีเรื่องเล่า<span aria-hidden="true">!</span></h1>
                <p>จดรายรับ เก็บรายจ่าย<br>แล้วค่อย ๆ เขียนเรื่องการเงินในแบบของคุณ</p>
                <div class="manga-stamp" aria-hidden="true">จดให้ชัด<br>ใช้ให้รู้</div>
                <a href="{{ route('home') }}" class="underline underline-offset-4" wire:navigate>กลับหน้าแรก</a>
            </aside>
            <div class="manga-auth-panel flex w-full flex-col gap-6">
                <a href="{{ route('home') }}" class="flex items-center gap-3 text-xl font-bold" wire:navigate>
                    <span class="flex size-10 items-center justify-center border-2 border-current">
                        <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                    </span>
                    <span>สมุดเงิน</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
