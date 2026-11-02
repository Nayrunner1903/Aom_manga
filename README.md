# สมุดเงิน

แอปจดรายรับรายจ่ายส่วนตัว หน้าตามังงะขาวดำ ใช้ภาษาไทย และเก็บข้อมูลใน SQLite บนเครื่องของเรา

ตอนนี้สมัครสมาชิก จดรายรับรายจ่าย จัดหมวดหมู่ และดูสรุปบน Dashboard ได้แล้ว หน้าจอรองรับมือถือด้วย

มี JWT API เพิ่มสำหรับโปรแกรมอื่นที่ต้องการอ่านหรือจัดการข้อมูลด้วย ส่วนหน้าเว็บ Livewire ยังเข้าสู่ระบบด้วย session ตามเดิม

## เปิดแอปยังไง

เปิด Terminal ใน VS Code จากโฟลเดอร์โปรเจกต์ แล้วรัน:

```powershell
php artisan serve
```

เปิดเว็บตามที่ Terminal แสดง และเปิด Terminal นี้ค้างไว้ขณะใช้งาน กด `Ctrl+C` เมื่อต้องการปิด server

ถ้าเจอ `ERR_CONNECTION_REFUSED` ให้เช็กก่อนว่า server ยังทำงานอยู่ ตอนนี้ build หน้าจอไว้แล้ว จึงไม่ต้องเปิด `npm run dev` เพื่อใช้งานตามปกติ

<details>
<summary>ถ้าเครื่องไม่รู้จักคำสั่ง php</summary>

ใช้ PHP ของ Herd โดยระบุตำแหน่งตรง ๆ:

```powershell
& 'C:\Users\Windows\.config\herd\bin\php85\php.exe' artisan serve
```

</details>

## ลองใช้ครั้งแรก

1. สมัครสมาชิก จะได้หมวดหมู่เริ่มต้น เช่น อาหาร การเดินทาง และเงินเดือน
2. เปิด **ประวัติรายการ → เพิ่มรายการ** แล้วลองจดรายรับกับรายจ่าย
3. กลับไปดู **ภาพรวม** จะเห็นยอดรวมและกราฟ เปลี่ยนเดือนเพื่อดูสรุปรายเดือนได้
4. ถ้าอยากเพิ่มหมวดเอง ให้ไปที่ **จัดการหมวดหมู่**
5. เปลี่ยนธีมได้ที่ **ตั้งค่าบัญชี → การแสดงผล** มีทั้งกระดาษขาวและหมึกดำ

ยอดรวมบน Dashboard นับทุกรายการที่บันทึกไว้ รวมวันที่ล่วงหน้าด้วย ส่วนยอดรายเดือนและกราฟจะเปลี่ยนตามเดือนที่เลือก

หมวดหมู่ที่มีรายการอยู่แล้วเปลี่ยนชื่อได้ แต่ยังลบหรือเปลี่ยนประเภทไม่ได้ ต้องย้ายหรือลบรายการในหมวดนั้นก่อน

## อยากแก้ส่วนไหน เปิดไฟล์อะไร

- **เพิ่มหรือแก้ไขรายการ:** [ฟอร์มธุรกรรม](resources/views/pages/transactions/⚡form.blade.php)
- **ค้นหา กรอง และลบรายการ:** [หน้าประวัติ](resources/views/pages/transactions/⚡index.blade.php)
- **เพิ่มหรือแก้หมวดหมู่:** [หน้าหมวดหมู่](resources/views/pages/categories/⚡index.blade.php)
- **ยอดรวมและกราฟ:** [Dashboard](resources/views/pages/⚡dashboard.blade.php)
- **สี ฟอนต์ กรอบ และลายมังงะ:** [app.css](resources/css/app.css)
- **ข้อความแปลของหน้าล็อกอินและตั้งค่า:** [th.json](resources/lang/th.json)
- **เมนูด้านข้าง:** [sidebar.blade.php](resources/views/layouts/app/sidebar.blade.php)

ไฟล์ที่มี `⚡` รวมโค้ด PHP และหน้าจอไว้ด้วยกัน ด้านบนจัดการข้อมูล ด้านล่างแสดงหน้าจอ

ถ้าจะเริ่มเรียนโค้ด ลองเปิดฟอร์มธุรกรรมแล้วหา `save()` เมธอดนี้ตรวจข้อมูลก่อนบันทึกลง `Transaction` จากนั้นพากลับหน้าประวัติ ส่วน [routes/web.php](routes/web.php) บอกว่าแต่ละ URL เปิดไฟล์ไหน

## ข้อมูลเชื่อมกันยังไง

ผู้ใช้หนึ่งคนมีหลายหมวดหมู่และหลายรายการ แต่ละรายการผูกกับหมวดหมู่หนึ่งหมวด โค้ดจะดึงข้อมูลผ่านผู้ใช้ที่เข้าสู่ระบบ เพื่อให้แต่ละคนเห็นเฉพาะข้อมูลของตัวเอง

โมเดลอยู่ใน `app/Models/`: [User](app/Models/User.php) คือผู้ใช้, [Category](app/Models/Category.php) คือหมวดหมู่ และ [Transaction](app/Models/Transaction.php) คือรายรับหรือรายจ่ายหนึ่งรายการ

เงินเก็บเป็น **จำนวนเต็มสตางค์** เช่น 125.50 บาท เก็บเป็น `12550` เพื่อคำนวณได้แม่นยำ แล้วค่อยแสดงเป็นบาทบนหน้าจอ

<details>
<summary>ดูรายชื่อไฟล์ที่เพิ่ม และหน้าที่ของแต่ละไฟล์</summary>

### ส่วนข้อมูล

- [app/Models/Category.php](app/Models/Category.php) — หมวดหมู่ของผู้ใช้ เชื่อมกับ `User` และ `Transaction`
- [app/Models/Transaction.php](app/Models/Transaction.php) — รายการเงิน เชื่อมเจ้าของกับหมวดหมู่ และจัดรูปแบบวันที่ก่อนเก็บ
- [migration หมวดหมู่](database/migrations/2026_10_31_174107_create_categories_table.php) — สร้างตาราง `categories` และห้ามชื่อซ้ำในประเภทเดียวกันของผู้ใช้คนเดียวกัน
- [migration ธุรกรรม](database/migrations/2026_10_31_174108_create_transactions_table.php) — สร้างตาราง `transactions` บังคับยอดสตางค์เป็นจำนวนเต็มบวก และตรวจว่าหมวดหมู่ตรงเจ้าของกับประเภท
- [database/seeders/CategorySeeder.php](database/seeders/CategorySeeder.php) — เติมหมวดเริ่มต้นให้บัญชีเดิม โดยเรียก `User::createDefaultCategories()` เรียกซ้ำได้โดยไม่เพิ่มชื่อเดิมซ้ำ

Migrations ทั้งสองไฟล์อยู่ใน `database/migrations/` ส่วนข้อมูลจริงอยู่ที่ `database/database.sqlite`

### หน้าจอและชิ้นส่วนที่ใช้ร่วมกัน

- [pages/transactions/⚡form.blade.php](resources/views/pages/transactions/⚡form.blade.php) — เพิ่ม/แก้รายการ ตรวจเจ้าของและข้อมูล แล้วบันทึกผ่าน `User` ไปยัง `Transaction`
- [pages/transactions/⚡index.blade.php](resources/views/pages/transactions/⚡index.blade.php) — อ่านรายการพร้อมหมวดหมู่ ค้นหา กรอง เรียง แบ่งหน้า และลบ
- [pages/categories/⚡index.blade.php](resources/views/pages/categories/⚡index.blade.php) — จัดการ `Category` และตรวจรายการที่ใช้หมวดนั้นก่อนลบหรือเปลี่ยนประเภท
- [pages/⚡dashboard.blade.php](resources/views/pages/⚡dashboard.blade.php) — รวมยอดของผู้ใช้ เตรียมกราฟ 6 เดือน สัดส่วนรายจ่าย และรายการล่าสุด 5 รายการ
- [components/money.blade.php](resources/views/components/money.blade.php) — แสดงสตางค์เป็นบาท รวมยอดติดลบ ใช้ใน Dashboard และประวัติ
- [components/finance-pagination.blade.php](resources/views/components/finance-pagination.blade.php) — ปุ่มแบ่งหน้าภาษาไทย ใช้กับประวัติและหมวดหมู่
- [errors/minimal.blade.php](resources/views/errors/minimal.blade.php) — หน้าข้อผิดพลาดภาษาไทย เช่น ไม่พบหน้า หรือหน้าหมดเวลา

ไฟล์หน้าจอกลุ่มนี้อยู่ใต้ `resources/views/` กดชื่อไฟล์เพื่อเปิดได้เลย

### คำแปล

- [resources/lang/th.json](resources/lang/th.json) — ข้อความของหน้าเข้าสู่ระบบและตั้งค่าบัญชี
- [resources/lang/th/auth.php](resources/lang/th/auth.php) — ข้อความเมื่อเข้าสู่ระบบไม่ได้
- [resources/lang/th/passwords.php](resources/lang/th/passwords.php) — ข้อความในขั้นตอนรีเซ็ตรหัสผ่าน
- [resources/lang/th/validation.php](resources/lang/th/validation.php) — ข้อความเมื่อกรอกข้อมูลไม่ถูกต้อง

### Tests และข้อมูลจำลอง

ไฟล์ทดสอบอยู่ใน `tests/Feature/`:

- [FinanceDataTest.php](tests/Feature/FinanceDataTest.php) — ความสัมพันธ์ ยอดเงิน และข้อจำกัดฐานข้อมูล
- [TransactionManagementTest.php](tests/Feature/TransactionManagementTest.php) — จัดการรายการ ค้นหา กรอง แบ่งหน้า และป้องกันการเข้าถึงข้อมูลคนอื่น
- [CategoryManagementTest.php](tests/Feature/CategoryManagementTest.php) — จัดการหมวดหมู่ ชื่อซ้ำ และหมวดที่มีรายการอยู่แล้ว
- [ThaiInterfaceTest.php](tests/Feature/ThaiInterfaceTest.php) — หน้าจอและข้อความผิดพลาดภาษาไทย
- [FinanceWorkflowTest.php](tests/Feature/FinanceWorkflowTest.php) — สมัครสมาชิกแล้วเพิ่ม/แก้/ลบรายการ ตรวจยอด และลบบัญชีที่มีข้อมูล
- [MoneyTest.php](tests/Feature/MoneyTest.php) — แสดงจำนวนเงินขนาดใหญ่โดยไม่เสียหลัก

ข้อมูลจำลองมาจาก [CategoryFactory.php](database/factories/CategoryFactory.php) และ [TransactionFactory.php](database/factories/TransactionFactory.php) ใน `database/factories/` โดย factory ของรายการเลือกเจ้าของและประเภทให้ตรงกับหมวดหมู่

</details>

<details>
<summary>ดูไฟล์เดิมที่แก้ไขจาก Starter Kit</summary>

- [app/Models/User.php](app/Models/User.php) — เพิ่มความสัมพันธ์กับรายการ/หมวดหมู่ และเมธอดสร้างหมวดเริ่มต้น
- [app/Actions/Fortify/CreateNewUser.php](app/Actions/Fortify/CreateNewUser.php) — สร้างบัญชีพร้อมหมวดรายรับ 4 หมวดและรายจ่าย 7 หมวด ถ้าบันทึกไม่สำเร็จจะย้อนกลับทั้งชุด
- [routes/web.php](routes/web.php) — เชื่อม URL กับหน้า Livewire และบังคับเข้าสู่ระบบก่อนเปิดหน้าการเงิน
- [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) — กำหนดให้ Laravel อ่านคำแปลใน `resources/lang/`
- [config/app.php](config/app.php) — กำหนดภาษาแอปเป็นไทย
- [resources/css/app.css](resources/css/app.css) — ธีมขาวดำ กรอบ เงา ลายจุด ฟอนต์ไทย และการจัดหน้าจอมือถือ
- [resources/views/welcome.blade.php](resources/views/welcome.blade.php) — หน้าแรกพร้อมภาพสมุดที่วาดด้วย SVG
- [layouts/app.blade.php](resources/views/layouts/app.blade.php) และ [layouts/app/sidebar.blade.php](resources/views/layouts/app/sidebar.blade.php) — กรอบหน้าหลักและเมนู
- [layouts/auth/simple.blade.php](resources/views/layouts/auth/simple.blade.php) — หน้าตาร่วมของล็อกอิน สมัครสมาชิก และรีเซ็ตรหัสผ่าน
- [app-logo.blade.php](resources/views/components/app-logo.blade.php), [app-logo-icon.blade.php](resources/views/components/app-logo-icon.blade.php) และ [public/favicon.svg](public/favicon.svg) — ชื่อและโลโก้สมุดเงิน
- [partials/head.blade.php](resources/views/partials/head.blade.php) — ชื่อแท็บ คำอธิบายแอป และไฟล์ CSS/JS ที่หน้าเว็บเรียกใช้
- [partials/settings-heading.blade.php](resources/views/partials/settings-heading.blade.php) — หัวข้อหน้าตั้งค่าบัญชี
- [components/auth-session-status.blade.php](resources/views/components/auth-session-status.blade.php) — กล่องข้อความแจ้งผลในหน้าเข้าสู่ระบบ
- [DashboardTest.php](tests/Feature/DashboardTest.php) — เพิ่มการตรวจยอดเงิน กราฟ ช่วงเดือน และการแยกข้อมูลผู้ใช้
- [RegistrationTest.php](tests/Feature/Auth/RegistrationTest.php) — ตรวจหมวดเริ่มต้นและการ hash รหัสผ่าน
- [SecurityTest.php](tests/Feature/Settings/SecurityTest.php) — ปรับให้ตรวจข้อความที่แปลเป็นไทย

Path ของ layouts, components และ partials ด้านบนเริ่มจาก `resources/views/`

ระบบเข้าสู่ระบบใช้ Fortify ของเดิมต่อ [FortifyServiceProvider.php](app/Providers/FortifyServiceProvider.php) เชื่อมขั้นตอนสมัครสมาชิกกับ `CreateNewUser` และขั้นตอนตั้งรหัสผ่านใหม่กับ [ResetUserPassword.php](app/Actions/Fortify/ResetUserPassword.php) ซึ่งยังไม่ได้แก้

</details>

## ใช้ JWT API

API อยู่ใต้ `/api/v1` เข้าสู่ระบบที่ `POST /api/v1/auth/login` แล้วส่ง token ที่ได้ใน header `Authorization: Bearer <token>` ทุกครั้งที่เรียกข้อมูลส่วนตัว ทุก API ที่อ่านหรือแก้ข้อมูลการเงินต้องใช้ JWT และตรวจเจ้าของข้อมูลด้วย

Token มีอายุ 15 นาที ต่ออายุได้ก่อนหมดอายุ โดย token เดิมจะถูกยกเลิกทันที ต้องเข้าสู่ระบบใหม่อย่างน้อยทุก 7 วัน หาก token หมดอายุแล้วให้เข้าสู่ระบบใหม่ การเปลี่ยนหรือรีเซ็ตรหัสผ่านทำให้ token เดิมทั้งหมดใช้ไม่ได้ด้วย

JWT เป็นวิธีส่งหลักฐานการเข้าสู่ระบบ ความปลอดภัยยังขึ้นกับการตรวจลายเซ็น อายุ token และสิทธิ์เจ้าของข้อมูล ระบบนี้จึงมีทั้งสามส่วน ส่วนหน้าเว็บเดิมใช้ session และ CSRF ต่อ ไม่ได้เก็บ JWT ลง localStorage

<details>
<summary>ดู endpoints วิธีเรียก และไฟล์ที่เกี่ยวข้องกับ JWT</summary>

### เรียกอะไรได้บ้าง

- `POST /api/v1/auth/register` — สมัครสมาชิกพร้อมหมวดเริ่มต้นและรับ JWT
- `POST /api/v1/auth/login` — รับ JWT ด้วยอีเมลกับรหัสผ่าน
- `GET /api/v1/auth/me` — ดูบัญชีของ token นี้
- `POST /api/v1/auth/refresh` — รับ token ใหม่และยกเลิกตัวเดิม ใช้ได้ก่อน token หมดอายุ
- `POST /api/v1/auth/logout` — ยกเลิก token ที่ส่งมา การออกจากระบบเว็บกับ API เป็นคนละส่วนกัน
- `GET/POST /api/v1/transactions` — ดูรายการหรือเพิ่มรายการ
- `GET/PUT/PATCH/DELETE /api/v1/transactions/{id}` — ดู แก้ไข หรือลบรายการของตนเอง
- `GET/POST /api/v1/categories` — ดูหรือเพิ่มหมวดหมู่
- `GET/PUT/PATCH/DELETE /api/v1/categories/{id}` — ดู แก้ไข หรือลบหมวดหมู่ของตนเอง
- `GET /api/v1/summary?month=2026-11` — ยอดรวมและยอดรายเดือนของตนเอง หน่วยสตางค์

เฉพาะ register กับ login ที่ไม่ต้องมี token การ login จำกัด 5 ครั้งต่อนาทีต่ออีเมลและ IP ส่วนสมัครสมาชิกจำกัด 5 ครั้งต่อนาทีต่อ IP และ API ส่วนตัวจำกัด 60 ครั้งต่อนาทีต่อผู้ใช้

รายการรองรับตัวกรอง `search`, `type`, `category_id`, `date_from`, `date_to`, `sort=asc|desc` และ `per_page` ไม่เกิน 100 ทั้งรายการและหมวดหมู่มีข้อมูลแบ่งหน้าใน `meta` กับ `links`

### ลองจาก PowerShell

เปิด server ตามขั้นตอนด้านบนก่อน แล้วใช้อีเมลและรหัสผ่านของบัญชีที่สมัครไว้:

```powershell
$api = 'http://localhost:8000/api/v1'
$credentials = @{ email = 'you@example.com'; password = 'your-password' } | ConvertTo-Json
$login = Invoke-RestMethod -Uri "$api/auth/login" -Method Post -ContentType 'application/json' -Body $credentials
$headers = @{ Authorization = "Bearer $($login.access_token)" }
Invoke-RestMethod -Uri "$api/categories" -Headers $headers
Invoke-RestMethod -Uri "$api/summary" -Headers $headers
```

ตัวอย่างเพิ่มรายจ่าย โดยเปลี่ยน `category_id` เป็นหมวดรายจ่ายของบัญชีตัวเอง:

```powershell
$body = @{
    type = 'expense'
    amount_satang = 12550
    category_id = 1
    transaction_date = '2026-11-01'
    description = 'อาหารกลางวัน'
} | ConvertTo-Json
Invoke-RestMethod -Uri "$api/transactions" -Method Post -Headers $headers -ContentType 'application/json; charset=utf-8' -Body ([System.Text.Encoding]::UTF8.GetBytes($body))
```

API รับเงินเป็น `amount_satang` จำนวนเต็มบวก เช่น `12550` คือ 125.50 บาท ไม่รับ `user_id` จากผู้เรียก และไม่รับ token ผ่าน URL หรือ cookie ต้องส่ง Bearer header เท่านั้น `PATCH` ส่งเฉพาะช่องที่แก้ได้ แต่ถ้าเปลี่ยนประเภทต้องส่งหมวดหมู่ที่ตรงประเภทมาด้วย `PUT` ส่งข้อมูลที่จำเป็นทั้งหมดเหมือนตอนเพิ่ม

ผลตอบกลับใช้ `401` เมื่อ token ใช้ไม่ได้, `404` เมื่อไม่พบข้อมูลของตนเอง, `422` เมื่อข้อมูลไม่ถูกต้อง และ `429` เมื่อเรียกถี่เกินไป การลบผ่าน API จะทำงานทันที ฝั่งโปรแกรมที่เรียกควรแสดงกล่องยืนยันเอง

### ไฟล์ที่เพิ่ม

- [routes/api.php](routes/api.php) — routes เวอร์ชัน v1 ใช้ชื่อขึ้นต้น `api.v1.` เพื่อไม่ชนกับ routes ของเว็บ
- [app/Http/Middleware/AuthenticateJwt.php](app/Http/Middleware/AuthenticateJwt.php) — รับ Bearer token ตรวจลายเซ็น วันหมดอายุ ผู้ใช้ และการเปลี่ยนรหัสผ่าน ก่อนส่งต่อให้ controller
- [ApiController.php](app/Http/Controllers/Api/ApiController.php) และ [AuthController.php](app/Http/Controllers/Api/AuthController.php) — ส่วนร่วมของ controllers และขั้นตอนสมัคร/เข้าสู่ระบบ ต่ออายุ ออกจากระบบ และดูบัญชี
- [TransactionController.php](app/Http/Controllers/Api/TransactionController.php) และ [CategoryController.php](app/Http/Controllers/Api/CategoryController.php) — จัดการข้อมูลผ่านความสัมพันธ์ของผู้ใช้ที่ได้จาก JWT
- [SummaryController.php](app/Http/Controllers/Api/SummaryController.php) — รวมยอดเงินของผู้ใช้ โดยไม่ใช้ข้อมูลจากบัญชีอื่น
- [TransactionRequest.php](app/Http/Requests/Api/TransactionRequest.php) และ [CategoryRequest.php](app/Http/Requests/Api/CategoryRequest.php) — ตรวจข้อมูลและเจ้าของรายการก่อนบันทึก
- [TransactionResource.php](app/Http/Resources/TransactionResource.php) และ [CategoryResource.php](app/Http/Resources/CategoryResource.php) — กำหนดช่องข้อมูลที่ API ส่งกลับ
- [config/jwt.php](config/jwt.php) — ตั้งลายเซ็น HS256 อายุ 15 นาที เพดาน 7 วัน และการยกเลิก token
- [tests/Feature/JwtApiTest.php](tests/Feature/JwtApiTest.php) — ทดสอบการเข้าใช้ API, token ปลอม/หมดอายุ/ถูกยกเลิก, สิทธิ์ข้อมูล, validation และการจำกัดจำนวนคำขอ

### ไฟล์เดิมที่แก้

- [composer.json](composer.json) และ [composer.lock](composer.lock) — เพิ่ม `php-open-source-saver/jwt-auth` รุ่น 2.9.3 พร้อม dependencies ของแพ็กเกจ
- [config/auth.php](config/auth.php) — เพิ่ม guard `api` สำหรับ JWT โดยเก็บ guard `web` แบบ session ไว้
- [bootstrap/app.php](bootstrap/app.php) — เปิด API routes และจัดลำดับให้ตรวจ JWT ก่อน rate limiter ของ API ส่วนตัว
- [app/Models/User.php](app/Models/User.php) — เพิ่ม JWT subject และข้อมูลตรวจว่ารหัสผ่านเปลี่ยนไปหลังออก token หรือไม่
- [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) — เพิ่ม rate limit สำหรับ API
- [.env.example](.env.example) — เพิ่มช่อง `JWT_SECRET` ว่างไว้เป็นตัวอย่าง ส่วน `.env` ของเครื่องนี้สร้าง signing secret แล้ว โดยไม่แสดงค่าใน README

ใช้แพ็กเกจ JWT สำหรับเซ็นและตรวจ token การ logout/refresh ใช้ blacklist ใน cache ของแอป จึงต้องใช้ cache ที่เก็บข้อมูลข้าม request ได้ และอย่าล้าง blacklist ขณะที่ยังมี token ใช้งาน หากทำสำเนาโปรเจกต์ไปเครื่องใหม่ ให้สร้าง secret ของเครื่องนั้นด้วย `php artisan jwt:secret` และเก็บ secret เป็นความลับ หากเปิด API นอกเครื่องให้ใช้ HTTPS

</details>

## ทดสอบแล้วแค่ไหน

ผลตรวจล่าสุดเมื่อจบระยะที่ 6: **119 tests ผ่าน / 436 assertions**, Larastan และ build ผ่าน ลองใช้งานผ่าน Edge จริงแล้วทั้ง desktop 1440px และขนาดมือถือ 390px โดยใช้ฐานข้อมูลแยกจากข้อมูลจริง

หลังเพิ่ม JWT API รัน tests ใหม่ทั้งชุดผ่าน **160 tests / 588 assertions** รวม tests API 41 รายการ และ Larastan ผ่าน ตรวจรูปแบบ PHP ด้วย Pint แล้ว การเปลี่ยนแปลงรอบนี้เป็นฝั่ง PHP จึงไม่ได้ build frontend ใหม่ ผลตรวจเบราว์เซอร์ด้านบนเป็นผลจากระยะที่ 6

ลองขั้นตอนหลักตั้งแต่สมัครสมาชิกจนถึงบันทึกรายการ ตรวจยอด ค้นหา ลบ และสลับธีมแล้ว ยังไม่ได้ลองบนโทรศัพท์จริงหรือ Safari ส่วนอีเมลตั้งให้เขียนลง log จึงยังไม่ได้ทดสอบการส่งอีเมลจริง

ถ้าจะรัน tests เอง:

```powershell
php artisan test --compact
```

Tests ใช้ SQLite ในหน่วยความจำ ตาม [phpunit.xml](phpunit.xml) และ [tests/Pest.php](tests/Pest.php) จึงแยกจากข้อมูลใช้งานจริง ภาพหน้าจอและไฟล์ประกอบการลองเบราว์เซอร์อยู่ใน `storage/framework/testing/phase6-*`

---

README นี้จะอัปเดตพร้อมโค้ดทุกครั้งที่เพิ่มหรือแก้ไฟล์ ตามกติกาใน [AGENTS.md](AGENTS.md) ส่วน `public/build/` เป็นไฟล์ที่สร้างจากการ build ไม่ต้องแก้ด้วยมือ
