<?php
declare(strict_types=1);

const STATE_DIR = __DIR__ . '/../data';
const STATE_FILE = STATE_DIR . '/state.json';

// Depois desse tempo sem nenhum poll, consideramos que o jogador saiu
// (aba fechada, celular desligado etc). Generoso de propósito para não
// expulsar alguém só porque o celular travou a aba em segundo plano.
const STALE_SECONDS = 300;
// Pausa rapidamente a partida sem expulsar quem pode estar reconectando.
const OFFLINE_SECONDS = 8;

function defaultState(): array {
    return [
        'lobby' => ['slots' => ['1' => null, '2' => null]],
        'activeGame' => null,
        'velha' => null,
        'forca' => null,
    ];
}

// Lê o estado, aplica $mutator($state) -> $state e grava, tudo sob lock
// exclusivo do arquivo para evitar corrida entre os dois jogadores.
function withState(callable $mutator): array {
    if (!is_dir(STATE_DIR)) {
        mkdir(STATE_DIR, 0775, true);
    }

    $fp = fopen(STATE_FILE, 'c+');
    if ($fp === false) {
        throw new RuntimeException('state_unavailable');
    }

    flock($fp, LOCK_EX);
    $size = filesize(STATE_FILE) ?: 0;
    $raw = $size > 0 ? fread($fp, $size) : '';
    $state = $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($state)) {
        $state = defaultState();
    }

    $state = $mutator($state);

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($state, JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $state;
}

function expireStaleSlots(array $state): array {
    $now = time();
    $changed = false;
    foreach (['1', '2'] as $slot) {
        $s = $state['lobby']['slots'][$slot] ?? null;
        if ($s && ($now - ($s['lastSeen'] ?? 0)) > STALE_SECONDS) {
            $state['lobby']['slots'][$slot] = null;
            $changed = true;
        }
    }
    if ($changed) {
        $state['activeGame'] = null;
        $state['velha'] = null;
        $state['forca'] = null;
    }
    return $state;
}

function findSlotByClientId(array $state, string $clientId): ?string {
    foreach (['1', '2'] as $slot) {
        $s = $state['lobby']['slots'][$slot] ?? null;
        if ($s && ($s['clientId'] ?? null) === $clientId) {
            return $slot;
        }
    }
    return null;
}

function otherSlot(string $slot): string {
    return $slot === '1' ? '2' : '1';
}

function isSlotOnline(array $state, string $slot): bool {
    $player = $state['lobby']['slots'][$slot] ?? null;
    return $player !== null && (time() - (int)($player['lastSeen'] ?? 0)) <= OFFLINE_SECONDS;
}

// Monta a versão do estado que pode ser enviada ao cliente: nunca inclui a
// palavra secreta da forca, só o tamanho e as letras já reveladas.
function publicState(array $state, ?string $clientId): array {
    $mySlot = $clientId ? findSlotByClientId($state, $clientId) : null;
    $out = $state;

    foreach (['1', '2'] as $slot) {
        if (!empty($out['lobby']['slots'][$slot])) {
            unset($out['lobby']['slots'][$slot]['clientId']);
            $out['lobby']['slots'][$slot]['online'] = isSlotOnline($state, $slot);
        }
    }

    if (!empty($state['forca'])) {
        $f = $state['forca'];
        $word = $f['word'] ?? '';
        $guessed = $f['guessedLetters'] ?? [];
        $revealed = [];
        foreach (str_split($word) as $ch) {
            $isLetter = preg_match('/^[A-Z]$/', $ch) === 1;
            $revealed[] = !$isLetter || in_array($ch, $guessed, true) ? $ch : null;
        }
        unset($f['hints']);
        unset($f['word']);
        $f['wordLength'] = strlen($word);
        $f['revealed'] = $revealed;
        if (!empty($f['gameOver'])) {
            $f['solution'] = $word;
        }
        if (!empty($f['themeChoices']) && $mySlot) {
            $opponentSlot = otherSlot($mySlot);
            $f['themeChoices'][$opponentSlot] = $f['themeChoices'][$opponentSlot] !== null;
        }
        $out['forca'] = $f;
    }

    $out['you'] = $mySlot;
    return $out;
}
