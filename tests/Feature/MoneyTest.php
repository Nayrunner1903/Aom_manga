<?php

use Illuminate\Support\Facades\Blade;

test('money display preserves every digit of large integer amounts', function (int $satang, string $expected) {
    $html = Blade::render('<x-money :satang="$satang" />', ['satang' => $satang]);

    expect($html)->toContain($expected);
})->with([
    'maximum integer' => [PHP_INT_MAX, '92,233,720,368,547,758.07 บาท'],
    'minimum integer' => [PHP_INT_MIN, '-92,233,720,368,547,758.08 บาท'],
]);
