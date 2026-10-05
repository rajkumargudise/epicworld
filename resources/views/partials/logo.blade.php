{{-- The original EPIC World logo. Dark theme shows the white-lettered version, light theme the black one.
     Fixed width/height attributes (aspect 918:586) so it never shifts the layout. Expects optional $height. --}}
@php
    $h = $height ?? 40;
    $w = (int) round($h * 918 / 586);
@endphp
<picture class="logo-dark">
    <source srcset="/brand/logo-white.webp" type="image/webp">
    <img src="/brand/logo-white.png" alt="EPIC World" width="{{ $w }}" height="{{ $h }}" decoding="async" class="block w-auto" style="height: {{ $h }}px">
</picture>
<picture class="logo-light">
    <source srcset="/brand/logo-black.webp" type="image/webp">
    <img src="/brand/logo-black.png" alt="EPIC World" width="{{ $w }}" height="{{ $h }}" decoding="async" class="block w-auto" style="height: {{ $h }}px">
</picture>
