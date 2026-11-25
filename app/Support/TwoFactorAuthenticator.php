<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TwoFactorAuthenticator
{
    public function __construct(private readonly Totp $totp) {}

    /**
     * Confirm a pending secret and return its one-time recovery codes.
     *
     * @return list<string>|null
     */
    public function confirmSetup(User $user, string $code): ?array
    {
        return DB::transaction(function () use ($user, $code): ?array {
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($user->two_factor_secret === null || $user->two_factor_confirmed_at !== null) {
                return null;
            }

            $counter = $this->totp->verify($user->two_factor_secret, $code);

            if ($counter === null) {
                return null;
            }

            $recoveryCodes = [];

            for ($index = 0; $index < 8; $index++) {
                $recoveryCode = strtoupper(bin2hex(random_bytes(5)));
                $recoveryCodes[] = substr($recoveryCode, 0, 5).'-'.substr($recoveryCode, 5);
            }

            $user->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_last_used_step' => $counter,
                'two_factor_recovery_codes' => array_map(
                    fn (string $recoveryCode): string => Hash::make($this->normalizeRecoveryCode($recoveryCode)),
                    $recoveryCodes,
                ),
            ])->save();

            return $recoveryCodes;
        });
    }

    public function verifyAndConsume(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($user->two_factor_confirmed_at === null || $user->two_factor_secret === null) {
                return false;
            }

            $counter = $this->totp->verify(
                $user->two_factor_secret,
                $code,
                lastUsedCounter: $user->two_factor_last_used_step,
            );

            if ($counter !== null) {
                $user->forceFill(['two_factor_last_used_step' => $counter])->save();

                return true;
            }

            $recoveryCodes = $user->two_factor_recovery_codes ?? [];
            $normalizedCode = $this->normalizeRecoveryCode($code);

            foreach ($recoveryCodes as $index => $recoveryCodeHash) {
                if (Hash::check($normalizedCode, $recoveryCodeHash)) {
                    unset($recoveryCodes[$index]);
                    $user->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();

                    return true;
                }
            }

            return false;
        });
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(str_replace(['-', ' '], '', $code));
    }
}
