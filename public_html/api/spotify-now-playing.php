<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$client_id = '01467ce5aa8c4cd39da57e0ed8f00edc';
$client_secret = 'f9ce3f71063f4d0296714b8bd06cdbeb';

// Lê o token salvo
$token_file = __DIR__ . '/../token.json';
if (!file_exists($token_file)) {
    die('token.json não encontrado');
}

$token_data = json_decode(file_get_contents($token_file), true);

if (!$token_data || !isset($token_data['access_token'])) {
    die('Token inválido ou ausente.');
}

// Renova o token se estiver expirado
if (time() > ($token_data['expires_at'] ?? 0)) {
    $refresh_token = $token_data['refresh_token'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret),
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'refresh_token',
        'refresh_token' => $refresh_token,
    ]));

    $response = curl_exec($ch);
    if ($response === false) {
        die('Erro ao renovar token: ' . curl_error($ch));
    }
    curl_close($ch);

    $new_token = json_decode($response, true);
    if (!isset($new_token['access_token'])) {
        die('Erro ao obter novo access_token: ' . $response);
    }

    $token_data['access_token'] = $new_token['access_token'];
    $token_data['expires_at'] = time() + $new_token['expires_in'];
    file_put_contents($token_file, json_encode($token_data, JSON_PRETTY_PRINT));
}

$access_token = $token_data['access_token'];

// Requisição à API do Spotify para pegar a música atual
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.spotify.com/v1/me/player/currently-playing');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);

$response = curl_exec($ch);
if ($response === false) {
    die('Erro ao acessar a API do Spotify: ' . curl_error($ch));
}
curl_close($ch);

// Envia o JSON para o frontend
header('Content-Type: application/json');
echo $response;
