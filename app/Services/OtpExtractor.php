<?php

namespace App\Services;

class OtpExtractor
{
    public function extract(string $text): ?string
    {
        // Context is mandatory: avoid interpreting arbitrary invoice numbers or dates as OTPs.
        $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/(?:verification\s*code|security\s*code|sign[ -]?in\s*code|one[ -]?time\s*(?:password|code)|login\s*code|your\s*(?:access\s*)?code|رمز\s*(?:التحقق|الدخول|التأكيد)|كود\s*(?:التحقق|الدخول))[^0-9]{0,60}(?<![0-9])([0-9]{4,8})(?![0-9])/iu', $text, $match)) {
            return $match[1];
        }
        if (preg_match('/(?<![0-9])([0-9]{4,8})(?![0-9])\s+(?:is your|هو رمز)/iu', $text, $match)) {
            return $match[1];
        }

        return null;
    }
}
