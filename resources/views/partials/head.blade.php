<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? __($title).' · สมุดเงิน' : 'สมุดเงิน · บันทึกรายรับรายจ่าย' }}
</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<meta name="theme-color" content="#111111">
<meta name="description" content="สมุดรายรับรายจ่ายส่วนตัวสไตล์มังงะขาวดำ จดง่าย เห็นภาพรวมชัดเจน">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
