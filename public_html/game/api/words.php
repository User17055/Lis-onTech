<?php
declare(strict_types=1);

// Banco de palavras do Jogo da Forca, por tema. Cada tema tem uma categoria
// da Wikipédia em português usada como fonte primária (pickWord tenta buscar
// lá primeiro) e uma lista local usada como reserva quando a busca falha.
const THEMES = [
    'animais' => [
        'label' => 'Animais',
        'wikiCategory' => 'Categoria:Mamíferos',
        'words' => [
            'LEAO', 'TIGRE', 'ELEFANTE', 'GIRAFA', 'MACACO', 'CACHORRO', 'GATO',
            'COELHO', 'CAVALO', 'ZEBRA', 'PINGUIM', 'GOLFINHO', 'TARTARUGA',
            'CROCODILO', 'CANGURU', 'PANDA', 'COALA', 'RINOCERONTE',
            'HIPOPOTAMO', 'ONCA', 'URSO', 'RAPOSA', 'LOBO', 'AGUIA',
        ],
    ],
    'frutas' => [
        'label' => 'Frutas',
        'wikiCategory' => 'Categoria:Frutos',
        'words' => [
            'BANANA', 'MACA', 'LARANJA', 'ABACAXI', 'MORANGO', 'UVA', 'MELANCIA',
            'MAMAO', 'GOIABA', 'MANGA', 'PERA', 'KIWI', 'LIMAO', 'COCO',
            'ACEROLA', 'JABUTICABA', 'CAJU', 'PESSEGO', 'AMEIXA', 'GRAVIOLA',
        ],
    ],
    'paises' => [
        'label' => 'Países',
        'wikiCategory' => 'Categoria:Países',
        'words' => [
            'BRASIL', 'ARGENTINA', 'PORTUGAL', 'FRANCA', 'ALEMANHA', 'ITALIA',
            'JAPAO', 'CANADA', 'MEXICO', 'ESPANHA', 'CHINA', 'EGITO',
            'AUSTRALIA', 'INDIA', 'RUSSIA', 'CHILE', 'PERU', 'COLOMBIA',
            'GRECIA', 'SUECIA',
        ],
    ],
    'profissoes' => [
        'label' => 'Profissões',
        'wikiCategory' => 'Categoria:Profissões',
        'words' => [
            'MEDICO', 'PROFESSOR', 'ENGENHEIRO', 'ADVOGADO', 'DENTISTA',
            'BOMBEIRO', 'POLICIAL', 'COZINHEIRO', 'PILOTO', 'ENFERMEIRO',
            'VETERINARIO', 'ARQUITETO', 'JORNALISTA', 'ELETRICISTA',
            'PROGRAMADOR', 'ATOR', 'CANTOR', 'PINTOR', 'MOTORISTA', 'AGRICULTOR',
        ],
    ],
    'filmes' => [
        'label' => 'Filmes',
        'wikiCategory' => 'Categoria:Filmes premiados com o Oscar de melhor filme',
        'words' => [
            'TITANIC', 'AVATAR', 'MATRIX', 'SHREK', 'FROZEN', 'MOANA', 'UP',
            'VINGADORES', 'BATMAN', 'SUPERMAN', 'ALADDIN', 'MULAN',
            'RATATOUILLE', 'GRAVIDADE', 'COCO', 'TROVOADA', 'TUBARAO',
        ],
    ],
    'objetos' => [
        'label' => 'Objetos',
        'wikiCategory' => 'Categoria:Utensílios domésticos',
        'words' => [
            'CADEIRA', 'MESA', 'COMPUTADOR', 'CELULAR', 'RELOGIO', 'JANELA',
            'PORTA', 'LIVRO', 'CANETA', 'GARRAFA', 'MOCHILA', 'ESPELHO',
            'LAMPADA', 'TESOURA', 'SOMBRINHA', 'TELEVISAO', 'GELADEIRA',
            'FOGAO', 'SOFA', 'TAPETE',
        ],
    ],
    'esportes' => [
        'label' => 'Esportes',
        'wikiCategory' => 'Categoria:Esportes de combate',
        'words' => [
            'FUTEBOL', 'BASQUETE', 'VOLEIBOL', 'NATACAO', 'TENIS', 'SURFE',
            'SKATE', 'CICLISMO', 'ATLETISMO', 'JUDO', 'KARATE', 'BOXE',
            'GINASTICA', 'HANDEBOL', 'RUGBY',
        ],
    ],
    'cores' => [
        'label' => 'Cores',
        'wikiCategory' => 'Categoria:Cores',
        'words' => [
            'VERMELHO', 'AZUL', 'AMARELO', 'VERDE', 'LARANJA', 'ROXO', 'ROSA',
            'PRETO', 'BRANCO', 'CINZA', 'MARROM', 'DOURADO', 'PRATEADO',
            'TURQUESA', 'BEGE',
        ],
    ],
];

function normalizeWord(string $word): string {
    $map = [
        'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N', 'Ý' => 'Y',
    ];
    $word = mb_strtoupper(trim($word), 'UTF-8');
    return strtr($word, $map);
}

function isUsableWord(string $word): bool {
    return preg_match('/^[A-Z]+(-[A-Z]+)*$/', $word) === 1
        && strlen($word) >= 3
        && strlen($word) <= 18;
}

// Busca um título de página aleatório dentro da categoria da Wikipédia
// associada ao tema. Retorna null se a busca falhar ou não render nenhum
// título utilizável (sem espaços, sem pontuação estranha) — quem chama deve
// cair para a lista local nesse caso.
function fetchWikipediaWord(string $themeKey): ?string {
    $theme = THEMES[$themeKey] ?? null;
    if (!$theme) {
        return null;
    }

    $url = 'https://pt.wikipedia.org/w/api.php?' . http_build_query([
        'action' => 'query',
        'list' => 'categorymembers',
        'cmtitle' => $theme['wikiCategory'],
        'cmlimit' => 50,
        'cmtype' => 'page',
        'format' => 'json',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 4,
            'header' => "User-Agent: LisonTechGame/1.0 (jogo entre amigos)\r\n",
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    $members = $data['query']['categorymembers'] ?? null;
    if (!is_array($members) || !$members) {
        return null;
    }

    $candidates = [];
    foreach ($members as $member) {
        $title = (string)($member['title'] ?? '');
        if ($title === '' || str_contains($title, ':')) {
            continue;
        }
        $parenPos = strpos($title, '(');
        if ($parenPos !== false) {
            $title = trim(substr($title, 0, $parenPos));
        }
        if (str_contains($title, ' ')) {
            continue;
        }
        $normalized = normalizeWord($title);
        if (isUsableWord($normalized)) {
            $candidates[] = $normalized;
        }
    }

    if (!$candidates) {
        return null;
    }

    return $candidates[array_rand($candidates)];
}

function pickWord(string $themeKey): array {
    $key = $themeKey === 'aleatorio' ? array_rand(THEMES) : $themeKey;
    $theme = THEMES[$key] ?? null;
    if (!$theme) {
        $key = array_rand(THEMES);
        $theme = THEMES[$key];
    }

    $word = fetchWikipediaWord($key);
    $source = 'wikipedia';
    if ($word === null) {
        $word = normalizeWord($theme['words'][array_rand($theme['words'])]);
        $source = 'local';
    }

    return [
        'themeKey' => $key,
        'themeLabel' => $theme['label'],
        'word' => $word,
        'source' => $source,
    ];
}
