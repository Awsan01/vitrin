<?php

namespace Tests\Unit;

use App\Support\PaddleSignature;
use PHPUnit\Framework\TestCase;

class PaddleSignatureTest extends TestCase
{
    protected const SECRET = 'pdl_ntfset_secret';

    protected const NOW = 1_800_000_000;

    protected function header(string $body, string $secret = self::SECRET, int $timestamp = self::NOW): string
    {
        return "ts={$timestamp};h1=".hash_hmac('sha256', "{$timestamp}:{$body}", $secret);
    }

    public function test_a_signature_made_with_the_secret_is_accepted(): void
    {
        $this->assertTrue(PaddleSignature::verify($this->header('{"a":1}'), '{"a":1}', self::SECRET, 300, self::NOW));
    }

    public function test_a_changed_body_is_rejected(): void
    {
        $this->assertFalse(PaddleSignature::verify($this->header('{"a":1}'), '{"a":2}', self::SECRET, 300, self::NOW));
    }

    public function test_a_signature_made_with_another_secret_is_rejected(): void
    {
        $this->assertFalse(PaddleSignature::verify($this->header('{}', 'other'), '{}', self::SECRET, 300, self::NOW));
    }

    public function test_old_signatures_are_rejected(): void
    {
        $header = $this->header('{}', timestamp: self::NOW - 301);

        $this->assertFalse(PaddleSignature::verify($header, '{}', self::SECRET, 300, self::NOW));
        $this->assertTrue(PaddleSignature::verify($header, '{}', self::SECRET, 301, self::NOW));
    }

    public function test_any_signature_matches_while_a_secret_is_rotated(): void
    {
        $header = 'ts='.self::NOW.';h1='.str_repeat('0', 64).';h1='.hash_hmac('sha256', self::NOW.':{}', self::SECRET);

        $this->assertTrue(PaddleSignature::verify($header, '{}', self::SECRET, 300, self::NOW));
    }

    public function test_malformed_headers_are_rejected(): void
    {
        foreach ([null, '', 'garbage', 'ts=abc;h1=00', 'ts='.self::NOW, 'h1=00', 'ts='.self::NOW.';h1='] as $header) {
            $this->assertFalse(PaddleSignature::verify($header, '{}', self::SECRET, 300, self::NOW), (string) $header);
        }
    }

    public function test_nothing_is_accepted_without_a_secret(): void
    {
        $this->assertFalse(PaddleSignature::verify($this->header('{}', ''), '{}', '', 300, self::NOW));
    }
}
