<?php

namespace App\Support;

use InvalidArgumentException;

class Totp
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->encodeBase32(random_bytes($bytes));
    }

    public function codeAt(string $secret, int $timestamp): string
    {
        return $this->codeAtCounter($secret, intdiv($timestamp, 30));
    }

    public function verify(
        string $secret,
        string $code,
        int $window = 1,
        ?int $timestamp = null,
        ?int $lastUsedCounter = null,
    ): ?int {
        if (! preg_match('/\A\d{6}\z/', $code)) {
            return null;
        }

        $currentCounter = intdiv($timestamp ?? time(), 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $counter = $currentCounter + $offset;

            if ($counter < 0 || ($lastUsedCounter !== null && $counter <= $lastUsedCounter)) {
                continue;
            }

            if (hash_equals($this->codeAtCounter($secret, $counter), $code)) {
                return $counter;
            }
        }

        return null;
    }

    private function codeAtCounter(string $secret, int $counter): string
    {
        $message = pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000);
        $hash = hash_hmac('sha1', $message, $this->decodeBase32($secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function encodeBase32(string $value): string
    {
        $encoded = '';
        $buffer = 0;
        $bits = 0;

        foreach (str_split($value) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::BASE32_ALPHABET[($buffer >> $bits) & 31];
            }
        }

        if ($bits > 0) {
            $encoded .= self::BASE32_ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    private function decodeBase32(string $value): string
    {
        $value = strtoupper(rtrim(str_replace(' ', '', $value), '='));
        $decoded = '';
        $buffer = 0;
        $bits = 0;

        foreach (str_split($value) as $character) {
            $digit = strpos(self::BASE32_ALPHABET, $character);

            if ($digit === false) {
                throw new InvalidArgumentException('Invalid Base32 secret.');
            }

            $buffer = ($buffer << 5) | $digit;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 0xFF);
                $buffer &= (1 << $bits) - 1;
            }
        }

        if ($decoded === '') {
            throw new InvalidArgumentException('Invalid Base32 secret.');
        }

        return $decoded;
    }
}
