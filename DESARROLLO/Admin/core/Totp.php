<?php
declare(strict_types=1);

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public static function secret(int $bytes = 20): string
    {
        $data = random_bytes($bytes); $bits = '';
        foreach (str_split($data) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $part) { if (strlen($part) < 5) $part = str_pad($part, 5, '0'); $out .= self::ALPHABET[bindec($part)]; }
        return $out;
    }
    private static function decode(string $value): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '')) as $char) { $pos = strpos(self::ALPHABET, $char); if ($pos === false) return ''; $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT); }
        $out = ''; foreach (str_split($bits, 8) as $part) if (strlen($part) === 8) $out .= chr(bindec($part)); return $out;
    }
    public static function code(string $secret, ?int $time = null): string
    {
        $counter = intdiv($time ?? time(), 30); $binary = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binary, self::decode($secret), true); $offset = ord($hash[19]) & 15;
        $value = ((ord($hash[$offset]) & 127) << 24) | ((ord($hash[$offset+1]) & 255) << 16) | ((ord($hash[$offset+2]) & 255) << 8) | (ord($hash[$offset+3]) & 255);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
    public static function verify(string $secret, string $code): bool
    {
        if (!preg_match('/^\d{6}$/D', $code)) return false;
        for ($window=-1; $window<=1; $window++) if (hash_equals(self::code($secret, time()+$window*30), $code)) return true;
        return false;
    }
    public static function uri(string $secret, string $user): string
    {
        return 'otpauth://totp/' . rawurlencode('RPV Construcciones:' . $user) . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode('RPV Construcciones') . '&algorithm=SHA1&digits=6&period=30';
    }
}
