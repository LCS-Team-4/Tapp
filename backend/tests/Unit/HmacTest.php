<?php

namespace Tests\Unit;

use App\Support\Hmac;
use PHPUnit\Framework\TestCase;

class HmacTest extends TestCase
{
    public function testSignAndVerifyRoundTrip(): void
    {
        $signature = Hmac::sign('payload', 'secret');

        $this->assertTrue(Hmac::verify('payload', $signature, 'secret'));
    }

    public function testVerifyFailsForTamperedPayload(): void
    {
        $signature = Hmac::sign('payload', 'secret');

        $this->assertFalse(Hmac::verify('tampered-payload', $signature, 'secret'));
    }

    public function testVerifyFailsForTamperedSignature(): void
    {
        $signature = Hmac::sign('payload', 'secret');

        $this->assertFalse(Hmac::verify('payload', $signature . 'ff', 'secret'));
    }

    public function testVerifyFailsForDifferentSecret(): void
    {
        $signature = Hmac::sign('payload', 'secret-a');

        $this->assertFalse(Hmac::verify('payload', $signature, 'secret-b'));
    }
}
