<?php
if (file_exists(__DIR__ . '/../vendor/autoload.php')) require_once __DIR__ . '/../vendor/autoload.php';

function qrSrc(string $data): string {
    if (class_exists('\chillerlan\QRCode\QRCode')) {
        try { return (new \chillerlan\QRCode\QRCode)->render($data); } catch (Throwable $t) {}
    }
    if (class_exists('\Endroid\QrCode\Builder\Builder')) {
        try { return \Endroid\QrCode\Builder\Builder::create()->data($data)->size(240)->margin(8)->build()->getDataUri(); } catch (Throwable $t) {}
        try { return (new \Endroid\QrCode\Builder\Builder(data: $data, size: 240, margin: 8))->build()->getDataUri(); } catch (Throwable $t) {}
    }
    return 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=8&data=' . rawurlencode($data);
}