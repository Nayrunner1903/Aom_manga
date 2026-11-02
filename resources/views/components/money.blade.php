@props(['satang'])
<span {{ $attributes }}>{{ $satang < 0 ? '-' : '' }}{{ number_format(abs(intdiv($satang, 100)), 0, '.', ',') }}.{{ str_pad((string) abs($satang % 100), 2, '0', STR_PAD_LEFT) }} บาท</span>
