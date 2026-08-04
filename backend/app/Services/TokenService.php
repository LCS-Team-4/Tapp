<?php

namespace App\Services;

use App\Exceptions\TokenException;
use App\Support\Env;
use App\Support\Hmac;

// Mints and verifies the 30-second signed clock-in token from docs/spec.md §4.
// Single-use/replay tracking is deliberately NOT implemented here — there is
// no persistence for it yet (database/migrations/004_create_tokens.sql is
// still empty, blocked on the open NFC/QR transport decision in spec.md §5).
class TokenService
{
    private const TTL_SECONDS = 30;

    public function issue(int $userId): string
    {
        $payload = json_encode([
            'user_id' => $userId,
            'exp' => time() + self::TTL_SECONDS,
        ]);

        $encodedPayload = base64_encode($payload);
        $signature = Hmac::sign($encodedPayload, $this->secret());

        return $encodedPayload . '.' . $signature;
    }

    public function verify(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            throw new TokenException('Malformed token');
        }

        [$encodedPayload, $signature] = $parts;

        if ($encodedPayload === '' || $signature === '') {
            throw new TokenException('Malformed token');
        }

        if (!Hmac::verify($encodedPayload, $signature, $this->secret())) {
            throw new TokenException('Invalid token signature');
        }

        $decoded = base64_decode($encodedPayload, true);
        if ($decoded === false) {
            throw new TokenException('Malformed token');
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['user_id'], $payload['exp'])) {
            throw new TokenException('Malformed token');
        }

        if (time() > (int) $payload['exp']) {
            throw new TokenException('Token expired');
        }

        return $payload;
    }

    // Not implemented — blocked on 004_create_tokens.sql, see spec.md §5.
    // There is nowhere to persist single-use state yet, so this throws
    // instead of silently returning a value that would be a lie.
    public function isConsumed(string $token): bool
    {
        throw new TokenException(
            'Single-use token tracking not implemented — blocked on 004_create_tokens.sql, see docs/spec.md §5'
        );
    }

    // Not implemented — blocked on 004_create_tokens.sql, see spec.md §5.
    public function markConsumed(string $token): void
    {
        throw new TokenException(
            'Single-use token tracking not implemented — blocked on 004_create_tokens.sql, see docs/spec.md §5'
        );
    }

    private function secret(): string
    {
        return (string) Env::get('HMAC_SECRET');
    }
}
