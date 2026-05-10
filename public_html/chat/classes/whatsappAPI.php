<?php
declare(strict_types=1);

class WhatsAppAPI
{
    private string $url;

    public function __construct()
    {
        $this->url = META_GRAPH_URL . '/' . META_PHONE_NUMBER_ID . '/messages';
    }

    private function request(array $payload): array
    {
        $ch = curl_init($this->url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . META_TOKEN,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error' => $curlError ?: 'Falha desconhecida no cURL',
                'response' => null,
            ];
        }

        $json = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'ok' => true,
                'http_code' => $httpCode,
                'response' => $json,
                'error' => null,
            ];
        }

        return [
            'ok' => false,
            'http_code' => $httpCode,
            'response' => $json,
            'error' => $json['error']['message'] ?? 'Erro ao enviar mensagem',
        ];
    }

    public function enviarTexto(string $numero, string $texto): array
    {
        $numero = preg_replace('/\D+/', '', $numero) ?? '';

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $numero,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $texto,
            ],
        ];

        return $this->request($payload);
    }

    public function enviarTemplate(string $numero, string $templateNome, string $idioma = 'pt_BR', array $bodyParams = []): array
    {
        $numero = preg_replace('/\D+/', '', $numero) ?? '';

        $template = [
            'name' => $templateNome,
            'language' => [
                'code' => $idioma,
            ],
        ];

        if (!empty($bodyParams)) {
            $template['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(
                        fn($valor) => [
                            'type' => 'text',
                            'text' => (string) $valor,
                        ],
                        $bodyParams
                    ),
                ],
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $numero,
            'type' => 'template',
            'template' => $template,
        ];

        return $this->request($payload);
    }
}