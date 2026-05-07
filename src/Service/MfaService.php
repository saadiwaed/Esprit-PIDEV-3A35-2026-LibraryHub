<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use ParagonIE\ConstantTime\Base32;

class MfaService
{
    public function generateSecret(): string
    {
        return Base32::encodeUpperUnpadded(random_bytes(20));
    }

    public function generateQrCode(string $email, string $secret): string
    {
        $uri = "otpauth://totp/LibraryHub:{$email}?secret={$secret}&issuer=LibraryHub";

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($uri)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        // Return as base64 data URI so it can be rendered in an <img> tag
        return '<img src="data:image/png;base64,' . base64_encode($result->getString()) . '" alt="QR Code MFA"/>';
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $code = str_replace(' ', '', $code);
        if (strlen($code) !== 6 || !is_numeric($code)) {
            return false;
        }

        $timestamp = time();
        for ($i = -1; $i <= 1; $i++) {
            $calculated = $this->generateTotpCode($secret, $timestamp + ($i * 30));
            if (hash_equals($calculated, $code)) {
                return true;
            }
        }
        return false;
    }

    private function generateTotpCode(string $secret, int $timestamp): string
    {
        $timeSlice = floor($timestamp / 30);
        $secretKey = Base32::decodeUpper($secret);

        $data = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $data, $secretKey, true);

        $offset = ord($hmac[19]) & 0x0F;
        $code = (
            ((ord($hmac[$offset]) & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
            (ord($hmac[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
            $codes[] = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
        }
        return $codes;
    }
}