<?php

namespace Tests\Unit;

use App\Services\OtpExtractor;
use PHPUnit\Framework\TestCase;

class OtpExtractorTest extends TestCase
{
    public function test_context_and_leading_zeros_are_preserved(): void
    {
        $extractor = new OtpExtractor;
        $this->assertSame('001234', $extractor->extract('Your verification code is 001234'));
        $this->assertSame('1234', $extractor->extract('رمز التحقق: 1234'));
        $this->assertNull($extractor->extract('Invoice 2026 total 123456'));
    }
}
