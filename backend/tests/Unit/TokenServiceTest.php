<?php

namespace Tests\Unit;

use App\Exceptions\TokenException;
use App\Services\TokenService;
use App\Support\Env;
use App\Support\Hmac;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TokenServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Inject HMAC_SECRET directly into Env's static store so these tests
        // don't depend on a real .env file being loaded on disk.
        $ref = new ReflectionClass(Env::class);
        $prop = $ref->getProperty('values');
        $prop->setAccessible(true);
        $prop->setValue(null, ['HMAC_SECRET' => 'test-secret']);
    }

    public function testIssueAndVerifyRoundTrip(): void
    {
        $service = new TokenService();
        $token = $service->issue(42);

        $payload = $service->verify($token);

        $this->assertSame(42, $payload['user_id']);
    }

    public function testVerifyRejectsExpiredToken(): void
    {
        $service = new TokenService();

        // Build an already-expired token directly rather than sleeping past
        // the real 30-second TTL.
        $encodedPayload = base64_encode(json_encode([
            'user_id' => 42,
            'exp' => time() - 5,
        ]));
        $signature = Hmac::sign($encodedPayload, 'test-secret');
        $token = $encodedPayload . '.' . $signature;

        $this->expectException(TokenException::class);
        $service->verify($token);
    }

    public function testVerifyRejectsTamperedToken(): void
    {
        $service = new TokenService();
        $token = $service->issue(42);

        $this->expectException(TokenException::class);
        $service->verify($token . 'tampered');
    }

    public function testVerifyRejectsTruncatedToken(): void
    {
        $service = new TokenService();

        $this->expectException(TokenException::class);
        $service->verify('not-a-valid-token');
    }
}
