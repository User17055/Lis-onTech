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

// Pistas factuais para os animais locais. Nenhuma delas entrega letras da resposta.
const ANIMAL_HINTS = [
    'LEAO' => ['É carnívoro e costuma viver em grupos.', 'É encontrado principalmente nas savanas africanas.'],
    'TIGRE' => ['É um grande felino carnívoro e solitário.', 'Suas listras ajudam na camuflagem.'],
    'ELEFANTE' => ['É herbívoro e passa boa parte do dia comendo plantas.', 'É o maior animal terrestre vivo.'],
    'GIRAFA' => ['É herbívora e alcança folhas no alto das árvores.', 'Possui o pescoço muito comprido.'],
    'MACACO' => ['Pode comer frutas, folhas, sementes e pequenos animais.', 'É um primata conhecido pela agilidade.'],
    'CACHORRO' => ['É domesticado e possui olfato muito apurado.', 'Costuma viver como animal de companhia dos humanos.'],
    'GATO' => ['É um felino carnívoro domesticado.', 'Enxerga bem com pouca luz e costuma ronronar.'],
    'COELHO' => ['É herbívoro e se alimenta de folhas e capim.', 'Tem orelhas longas e patas traseiras fortes.'],
    'CAVALO' => ['É herbívoro e se alimenta principalmente de capim.', 'Foi domesticado para transporte, trabalho e esporte.'],
    'ZEBRA' => ['É herbívora e vive nas savanas africanas.', 'Sua pelagem tem listras pretas e brancas.'],
    'PINGUIM' => ['Alimenta-se de peixes e outros animais marinhos.', 'É uma ave que não voa, mas nada muito bem.'],
    'GOLFINHO' => ['Alimenta-se principalmente de peixes e lulas.', 'É um mamífero marinho muito sociável.'],
    'TARTARUGA' => ['A alimentação varia entre plantas e pequenos animais.', 'Possui um casco que protege o corpo.'],
    'CROCODILO' => ['É carnívoro e costuma caçar perto da água.', 'É um grande réptil de mandíbulas poderosas.'],
    'CANGURU' => ['É herbívoro e se alimenta de gramíneas.', 'Desloca-se aos saltos e carrega o filhote em uma bolsa.'],
    'PANDA' => ['Sua alimentação é baseada principalmente em bambu.', 'É um mamífero de pelagem preta e branca.'],
    'COALA' => ['Alimenta-se quase exclusivamente de folhas de eucalipto.', 'Vive em árvores e é nativo da Austrália.'],
    'RINOCERONTE' => ['É herbívoro e come capim, folhas e brotos.', 'Tem pele grossa e um ou dois chifres no focinho.'],
    'HIPOPOTAMO' => ['É principalmente herbívoro e costuma pastar à noite.', 'Passa grande parte do dia dentro da água.'],
    'ONCA' => ['É um felino carnívoro e excelente nadador.', 'Possui manchas em forma de rosetas na pelagem.'],
    'URSO' => ['Muitas espécies são onívoras.', 'Pode hibernar durante os meses mais frios.'],
    'RAPOSA' => ['É onívora e tem hábitos geralmente noturnos.', 'É conhecida pela cauda espessa e grande audição.'],
    'LOBO' => ['É carnívoro e costuma caçar em grupo.', 'Vive em grupos sociais chamados alcateias.'],
    'AGUIA' => ['É uma ave de rapina carnívora.', 'Possui visão muito aguçada e garras fortes.'],
];

function buildWordHints(string $word, string $themeKey, string $themeLabel): array {
    if ($themeKey === 'animais' && isset(ANIMAL_HINTS[$word])) {
        return ANIMAL_HINTS[$word];
    }

    $letterCount = preg_match_all('/[A-Z]/', $word);
    $vowelCount = preg_match_all('/[AEIOU]/', $word);
    $consonantCount = $letterCount - $vowelCount;
    $wordCount = substr_count($word, ' ') + 1;
    return [
        "A resposta tem {$vowelCount} vogais e {$consonantCount} consoantes.",
        "Ela é formada por {$wordCount} " . ($wordCount === 1 ? 'palavra.' : 'palavras.'),
    ];
}

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
    $word = strtr($word, $map);
    return (string)preg_replace('/\s+/', ' ', $word);
}

function isUsableWord(string $word): bool {
    return preg_match('/^[A-Z]+(?:[ -][A-Z]+)*$/', $word) === 1
        && strlen($word) >= 3
        && strlen($word) <= 18;
}

// Busca um título de página aleatório dentro da categoria da Wikipédia
// associada ao tema. Retorna null se a busca falhar ou não render nenhum
// título utilizável (palavra ou expressão curta, sem pontuação estranha) — quem chama deve
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

    // Os animais usam a lista curada para sempre terem duas pistas factuais.
    $word = $key === 'animais' ? null : fetchWikipediaWord($key);
    $source = $key === 'animais' ? 'local' : 'wikipedia';
    if ($word === null) {
        $word = normalizeWord($theme['words'][array_rand($theme['words'])]);
        $source = 'local';
    }

    return [
        'themeKey' => $key,
        'themeLabel' => $theme['label'],
        'word' => $word,
        'source' => $source,
        'hints' => buildWordHints($word, $key, $theme['label']),
    ];
}
