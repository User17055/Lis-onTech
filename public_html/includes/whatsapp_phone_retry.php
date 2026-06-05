<?php
declare(strict_types=1);

if (!function_exists('waRetryNormalizeBrPhone')) {
    function waRetryNormalizeBrPhone(?string $raw): string
    {
        $digits = preg_replace('/\D+/', '', (string)$raw) ?? '';
        if ($digits !== '' && strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }

        return preg_match('/^55\d{10,11}$/', $digits) ? $digits : '';
    }
}

if (!function_exists('waRetryLooksLikePhoneFailure')) {
    function waRetryLooksLikePhoneFailure(array $response, ?string $curlError = null): bool
    {
        if ($curlError) {
            return false;
        }

        $error = $response['error'] ?? [];
        $code = is_array($error) ? (string)($error['code'] ?? '') : '';
        $sub = is_array($error) ? (string)($error['error_subcode'] ?? '') : '';
        $message = is_array($error) ? (string)($error['message'] ?? '') : '';
        $details = is_array($error) ? (string)($error['error_data']['details'] ?? '') : '';
        $hay = strtolower($code . ' ' . $sub . ' ' . $message . ' ' . $details . ' ' . json_encode($response, JSON_UNESCAPED_UNICODE));

        if (in_array($code, ['131026'], true)) {
            return true;
        }

        if ($code === '100' && preg_match('/phone|telefone|recipient|destinat|wa_id|to parameter|numero|n[uú]mero|number|invalid|inv[aá]lid/', $hay)) {
            return true;
        }

        return preg_match('/phone|telefone|recipient|destinat|wa_id|to parameter|numero|n[uú]mero|number|invalid phone|not a valid|inv[aá]lid/', $hay) === 1;
    }
}
