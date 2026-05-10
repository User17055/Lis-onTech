<?php

$accessToken = 'EAAOWDI0RUQoBQPUxdfFE77GdkZBKiisZCcebBq6fZCyA3e1ZCnrQ0fokgWdZAj7yYBBtWhyGsT18iWhMZAeCNkM9ku6D5tNnzaYvo5ya9m0pw97KuU2gh5VbCx7XbpN29Kupxof8unLuFtLGDJw6ZB8BHOZCe9mZAjc74spMchyAjg9ZBsdyTjYtBQinZCi7uW3HCmZBmgZDZD';
$phoneId = '363145423556335';
$verifyToken = 'joaocooldefadha';


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hubMode = $_GET['hub_mode'] ?? '';
    $hubChallenge = $_GET['hub_challenge'] ?? '';
    $hubVerifyToken = $_GET['hub_verify_token'] ?? '';

    if ($hubMode === 'subscribe' && $hubVerifyToken === $verifyToken) {
        echo $hubChallenge;
        exit;
    }
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {

    $message = $data['entry'][0]['changes'][0]['value']['messages'][0];
    $numeroCliente = $message['from'];


    $resposta = [
        'messaging_product' => 'whatsapp',
        'to' => $numeroCliente,
        'type' => 'text',
        'text' => [
            'body' => '*Este canal é apenas para notificações.*
Para qualquer dúvida ou atendimento, entre em contato pelo nosso canal oficial de WhatsApp:' . "\n\n" . ' (18) 99664-2021.' . "\n" . '
Estamos à disposição para ajudar! 💙
'
        ]
    ];

    $ch = curl_init("https://graph.facebook.com/v22.0/$phoneId/messages");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($resposta));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
}

http_response_code(200);
?>