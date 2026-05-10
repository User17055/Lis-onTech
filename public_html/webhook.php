<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$accessToken = cfg($cfg, 'META_ACCESS_TOKEN');
$phoneId = cfg($cfg, 'META_PHONE_NUMBER_ID');
$verifyToken = cfg($cfg, 'META_VERIFY_TOKEN');
$autoReplyText = cfg(
    $cfg,
    'AUTO_REPLY_TEXT',
    "Este canal e apenas para notificacoes.\n\nPara atendimento, use o WhatsApp oficial."
);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hubMode = $_GET['hub_mode'] ?? '';
    $hubChallenge = $_GET['hub_challenge'] ?? '';
    $hubVerifyToken = $_GET['hub_verify_token'] ?? '';

    if ($hubMode === 'subscribe' && $verifyToken !== '' && $hubVerifyToken === $verifyToken) {
        echo $hubChallenge;
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode((string) $input, true);

if (
    is_array($data)
    && isset($data['entry'][0]['changes'][0]['value']['messages'][0])
    && $accessToken !== ''
    && $phoneId !== ''
) {
    $message = $data['entry'][0]['changes'][0]['value']['messages'][0];
    $customerNumber = (string) ($message['from'] ?? '');

    if ($customerNumber !== '') {
        $reply = [
            'messaging_product' => 'whatsapp',
            'to' => $customerNumber,
            'type' => 'text',
            'text' => [
                'body' => $autoReplyText,
            ],
        ];

        $ch = curl_init("https://graph.facebook.com/v22.0/{$phoneId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($reply, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

http_response_code(200);
