<?php

return [
    'required' => 'กรุณากรอก:attribute',
    'string' => ':attributeต้องเป็นข้อความ',
    'email' => 'กรุณากรอกอีเมลให้ถูกต้อง',
    'confirmed' => 'การยืนยัน:attributeไม่ตรงกัน',
    'current_password' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง',
    'unique' => ':attributeนี้ถูกใช้แล้ว',
    'max' => [
        'string' => ':attributeต้องยาวไม่เกิน :max ตัวอักษร',
        'numeric' => ':attributeต้องไม่เกิน :max',
        'array' => ':attributeต้องมีไม่เกิน :max รายการ',
        'file' => ':attributeต้องมีขนาดไม่เกิน :max กิโลไบต์',
    ],
    'min' => [
        'string' => ':attributeต้องยาวอย่างน้อย :min ตัวอักษร',
        'numeric' => ':attributeต้องไม่น้อยกว่า :min',
        'array' => ':attributeต้องมีอย่างน้อย :min รายการ',
        'file' => ':attributeต้องมีขนาดอย่างน้อย :min กิโลไบต์',
    ],
    'password' => [
        'letters' => ':attributeต้องมีตัวอักษรอย่างน้อยหนึ่งตัว',
        'mixed' => ':attributeต้องมีทั้งตัวพิมพ์ใหญ่และตัวพิมพ์เล็ก',
        'numbers' => ':attributeต้องมีตัวเลขอย่างน้อยหนึ่งตัว',
        'symbols' => ':attributeต้องมีสัญลักษณ์อย่างน้อยหนึ่งตัว',
        'uncompromised' => ':attributeนี้เคยปรากฏในข้อมูลที่รั่วไหล กรุณาเลือกรหัสผ่านอื่น',
    ],
    'attributes' => [
        'name' => 'ชื่อ',
        'email' => 'อีเมล',
        'password' => 'รหัสผ่าน',
        'password_confirmation' => 'รหัสผ่านยืนยัน',
        'current_password' => 'รหัสผ่านปัจจุบัน',
    ],
];
