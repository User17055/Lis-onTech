<?php
declare(strict_types=1);

$query = $_GET;
$query['pagina'] = 'detalhes';

header('Location: /painel/index.php?' . http_build_query($query), true, 302);
exit;
