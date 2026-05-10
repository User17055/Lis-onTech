<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dados da API da Meta
|--------------------------------------------------------------------------
| META_TOKEN = token permanente ou token válido da sua API
| META_PHONE_NUMBER_ID = phone number id do WhatsApp
| META_VERIFY_TOKEN = token que você vai usar na configuração do webhook
|--------------------------------------------------------------------------
*/

define('META_API_VERSION', 'v23.0');
define('META_TOKEN', 'COLE_SEU_TOKEN_AQUI');
define('META_PHONE_NUMBER_ID', 'COLE_SEU_PHONE_NUMBER_ID_AQUI');
define('META_VERIFY_TOKEN', 'COLE_UM_TOKEN_SEGURO_AQUI');
define('META_GRAPH_URL', 'https://graph.facebook.com/' . META_API_VERSION);