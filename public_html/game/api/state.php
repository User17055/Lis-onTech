<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/words.php';
require __DIR__ . '/store.php';

const GAME_PASSWORD = 'dudadech';
const MAX_ERRORS = 6;

function jsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function sanitizeName(string $name): string {
    $name = trim((string)preg_replace('/\s+/', ' ', strip_tags($name)));
    return mb_substr($name, 0, 20, 'UTF-8');
}

function respondState(array $state, ?string $clientId): void {
    echo json_encode(['ok' => true, 'state' => publicState($state, $clientId)], JSON_UNESCAPED_UNICODE);
    exit;
}

function respondError(string $error, int $httpCode = 400): void {
    http_response_code($httpCode);
    echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

function newVelhaState(int $starter): array {
    return [
        'board' => array_fill(0, 9, null),
        'turnSlot' => (string)$starter,
        'starter' => $starter,
        'gameOver' => false,
        'winLine' => null,
        'scores' => ['1' => 0, '2' => 0, 'draw' => 0],
    ];
}

function checkVelhaWinner(array $board): ?array {
    $lines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]];
    foreach ($lines as $line) {
        [$a, $b, $c] = $line;
        if ($board[$a] !== null && $board[$a] === $board[$b] && $board[$a] === $board[$c]) {
            return $line;
        }
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $clientId = isset($_GET['clientId']) ? (string)$_GET['clientId'] : null;
        $state = withState(function (array $state) use ($clientId) {
            $state = expireStaleSlots($state);
            if ($clientId) {
                $slot = findSlotByClientId($state, $clientId);
                if ($slot) {
                    $state['lobby']['slots'][$slot]['lastSeen'] = time();
                }
            }
            return $state;
        });
        respondState($state, $clientId);
    }

    if ($method !== 'POST') {
        respondError('method_not_allowed', 405);
    }

    $input = jsonInput();
    $action = (string)($input['action'] ?? '');
    $clientId = isset($input['clientId']) ? (string)$input['clientId'] : null;

    switch ($action) {
        case 'join': {
            $name = sanitizeName((string)($input['name'] ?? ''));
            $password = (string)($input['password'] ?? '');

            if ($name === '') {
                respondError('missing_name');
            }
            if ($password !== GAME_PASSWORD) {
                respondError('invalid_password', 401);
            }

            $resultError = null;
            $assignedClientId = $clientId;

            $state = withState(function (array $state) use ($clientId, $name, &$resultError, &$assignedClientId) {
                $state = expireStaleSlots($state);

                if ($clientId) {
                    $slot = findSlotByClientId($state, $clientId);
                    if ($slot) {
                        $state['lobby']['slots'][$slot]['name'] = $name;
                        $state['lobby']['slots'][$slot]['lastSeen'] = time();
                        return $state;
                    }
                }

                $freeSlot = null;
                foreach (['1', '2'] as $slot) {
                    if ($state['lobby']['slots'][$slot] === null) {
                        $freeSlot = $slot;
                        break;
                    }
                }

                if ($freeSlot === null) {
                    $resultError = 'room_full';
                    return $state;
                }

                $newClientId = bin2hex(random_bytes(12));
                $assignedClientId = $newClientId;
                $now = time();
                $state['lobby']['slots'][$freeSlot] = [
                    'clientId' => $newClientId,
                    'name' => $name,
                    'joinedAt' => $now,
                    'lastSeen' => $now,
                ];
                return $state;
            });

            if ($resultError) {
                respondError($resultError, 409);
            }

            echo json_encode([
                'ok' => true,
                'clientId' => $assignedClientId,
                'state' => publicState($state, $assignedClientId),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        case 'leave': {
            if (!$clientId) {
                respondError('missing_client');
            }
            $state = withState(function (array $state) use ($clientId) {
                $slot = findSlotByClientId($state, $clientId);
                if ($slot) {
                    $state['lobby']['slots'][$slot] = null;
                    $state['activeGame'] = null;
                    $state['velha'] = null;
                    $state['forca'] = null;
                }
                return $state;
            });
            respondState($state, null);
        }

        case 'choose_game': {
            if (!$clientId) {
                respondError('missing_client');
            }
            $game = (string)($input['game'] ?? '');
            if (!in_array($game, ['velha', 'forca'], true)) {
                respondError('invalid_game');
            }

            $resultError = null;
            $state = withState(function (array $state) use ($clientId, $game, &$resultError) {
                $mySlot = findSlotByClientId($state, $clientId);
                if (!$mySlot) {
                    $resultError = 'not_in_lobby';
                    return $state;
                }
                if (!($state['lobby']['slots'][otherSlot($mySlot)] ?? null)) {
                    $resultError = 'waiting_for_opponent';
                    return $state;
                }

                $state['activeGame'] = $game;
                $state['velha'] = $game === 'velha' ? newVelhaState(1) : null;
                $state['forca'] = $game === 'forca' ? [
                    'choosingTheme' => true,
                    'themePickerSlot' => $mySlot,
                    'scores' => ['1' => 0, '2' => 0],
                    'starter' => 2,
                ] : null;
                return $state;
            });

            if ($resultError) {
                respondError($resultError, 409);
            }
            respondState($state, $clientId);
        }

        case 'back_to_menu': {
            if (!$clientId) {
                respondError('missing_client');
            }
            $state = withState(function (array $state) use ($clientId) {
                $mySlot = findSlotByClientId($state, $clientId);
                if ($mySlot) {
                    $state['activeGame'] = null;
                    $state['velha'] = null;
                    $state['forca'] = null;
                }
                return $state;
            });
            respondState($state, $clientId);
        }

        case 'velha_move': {
            if (!$clientId) {
                respondError('missing_client');
            }
            $index = filter_var($input['index'] ?? null, FILTER_VALIDATE_INT);
            if ($index === false || $index === null || $index < 0 || $index > 8) {
                respondError('invalid_index');
            }

            $resultError = null;
            $state = withState(function (array $state) use ($clientId, $index, &$resultError) {
                $mySlot = findSlotByClientId($state, $clientId);
                if (!$mySlot) {
                    $resultError = 'not_in_lobby';
                    return $state;
                }
                if ($state['activeGame'] !== 'velha' || !$state['velha']) {
                    $resultError = 'no_active_game';
                    return $state;
                }
                if (!isSlotOnline($state, otherSlot($mySlot))) {
                    $resultError = 'opponent_offline';
                    return $state;
                }
                $v = $state['velha'];
                if (!empty($v['gameOver'])) {
                    $resultError = 'game_over';
                    return $state;
                }
                if ($v['turnSlot'] !== $mySlot) {
                    $resultError = 'not_your_turn';
                    return $state;
                }
                if ($v['board'][$index] !== null) {
                    $resultError = 'cell_taken';
                    return $state;
                }

                $v['board'][$index] = (int)$mySlot;

                $winLine = checkVelhaWinner($v['board']);
                if ($winLine) {
                    $v['gameOver'] = true;
                    $v['winLine'] = $winLine;
                    $v['scores'][$mySlot] = ($v['scores'][$mySlot] ?? 0) + 1;
                } elseif (!in_array(null, $v['board'], true)) {
                    $v['gameOver'] = true;
                    $v['winLine'] = null;
                    $v['scores']['draw'] = ($v['scores']['draw'] ?? 0) + 1;
                } else {
                    $v['turnSlot'] = otherSlot($mySlot);
                }

                $state['velha'] = $v;
                return $state;
            });

            if ($resultError) {
                respondError($resultError, 409);
            }
            respondState($state, $clientId);
        }

        case 'velha_restart_round': {
            if (!$clientId) {
                respondError('missing_client');
            }
            $resultError = null;
            $state = withState(function (array $state) use ($clientId, &$resultError) {
                $mySlot = findSlotByClientId($state, $clientId);
                if (!$mySlot) {
                    $resultError = 'not_in_lobby';
                    return $state;
                }
                if ($state['activeGame'] !== 'velha' || !$state['velha']) {
                    $resultError = 'no_active_game';
                    return $state;
                }
                if (!isSlotOnline($state, otherSlot($mySlot))) {
                    $resultError = 'opponent_offline';
                    return $state;
                }
                $v = $state['velha'];
                $newStarter = $v['starter'] === 1 ? 2 : 1;
                $scores = $v['scores'];
                $state['velha'] = newVelhaState($newStarter);
                $state['velha']['scores'] = $scores;
                return $state;
            });
            if ($resultError) {
                respondError($resultError, 409);
            }
            respondState($state, $clientId);
        }

        case 'velha_reset_score': {
            if (!$clientId) {
                respondError('missing_client');
            }
            $resultError = null;
            $state = withState(function (array $state) use ($clientId, &$resultError) {
                $mySlot = findSlotByClientId($state, $clientId);
                if (!$mySlot) {
                    $resultError = 'not_in_lobby';
                    return $state;
                }
                if ($state['activeGame'] !== 'velha') {
                    $resultError = 'no_active_game';
                    return $state;
                }
                if (!isSlotOnline($state, otherSlot($mySlot))) {
                    $resultError = 'opponent_offline';
                    return $state;
                }
                $state['velha'] = newVelhaState(1);
                return $state;
            });
            if ($resultError) {
                respondError($resultError, 409);
            }
            respondState($state, $clientId);
        }

        case 'forca_pick_theme': {
            if (!$clientId) {
                respondError('missing_client');
            }
            $themeKey = (string)($input['themeKey'] ?? '');
            $validKeys = array_merge(array_keys(THEMES), ['aleatorio']);
            if (!in_array($themeKey, $validKeys, true)) {
                respondError('invalid_theme');
            }

            $resultError = null;
            $state = withState(function (array $state) use ($clientId, $themeKey, &$resultError) {
                $mySlot = findSlotByClientId($state, $clientId);
                if (!$mySlot) {
                    $resultError = 'not_in_lobby';
                    return $state;
                }
                if (!($state['lobby']['slots'][otherSlot($mySlot)] ?? null)) {
                    $resultError = 'waiting_for_opponent';
                    return $state;
                }
                if ($state['activeGame'] !== 'forca') {
                    $resultError = 'no_active_game';
                    return $state;
                }

                $currentForca = $state['forca'] ?? [];
                $pickerSlot = $currentForca['themePickerSlot'] ?? $mySlot;
                if (!empty($currentForca['choosingTheme']) && $pickerSlot !== $mySlot) {
                    $resultError = 'not_theme_picker';
                    return $state;
                }
                if (!isSlotOnline($state, otherSlot($mySlot))) {
                    $resultError = 'opponent_offline';
                    return $state;
                }

                $picked = pickWord($themeKey);
                $prevScores = $state['forca']['scores'] ?? ['1' => 0, '2' => 0];
                $prevStarter = $state['forca']['starter'] ?? 2;
                $newStarter = $prevStarter === 1 ? 2 : 1;

                $state['forca'] = [
                    'themeKey' => $picked['themeKey'],
                    'themeLabel' => $picked['themeLabel'],
                    'themeEmoji' => $picked['themeEmoji'],
                    'source' => $picked['source'],
                    'word' => $picked['word'],
                    'guessedLetters' => [],
                    'wrongCount' => 0,
                    'turnSlot' => (string)$newStarter,
                    'starter' => $newStarter,
                    'gameOver' => false,
                    'outcome' => null,
                    'roundPoints' => ['1' => 0, '2' => 0],
                    'scores' => $prevScores,
                    'choosingTheme' => false,
                    'themePickerSlot' => null,
                ];
                return $state;
            });

            if ($resultError) {
                respondError($resultError, 409);
            }
            respondState($state, $clientId);
        }

        case 'forca_change_theme': {
            if (!$clientId) {
                respondError('missing_client');
            }
            $resultError = null;
            $state = withState(function (array $state) use ($clientId, &$resultError) {
                $mySlot = findSlotByClientId($state, $clientId);
                if (!$mySlot) {
                    $resultError = 'not_in_lobby';
                    return $state;
                }
                if ($state['activeGame'] !== 'forca' || !$state['forca']) {
                    $resultError = 'no_active_game';
                    return $state;
                }
                $state['forca']['choosingTheme'] = true;
                $state['forca']['themePickerSlot'] = $mySlot;
                return $state;
            });
            if ($resultError) {
                respondError($resultError, 409);
            }
            respondState($state, $clientId);
        }

        case 'forca_guess': {
            if (!$clientId) {
                respondError('missing_client');
            }
            $letter = mb_strtoupper((string)($input['letter'] ?? ''), 'UTF-8');
            if (!preg_match('/^[A-Z]$/', $letter)) {
                respondError('invalid_letter');
            }

            $resultError = null;
            $state = withState(function (array $state) use ($clientId, $letter, &$resultError) {
                $mySlot = findSlotByClientId($state, $clientId);
                if (!$mySlot) {
                    $resultError = 'not_in_lobby';
                    return $state;
                }
                if ($state['activeGame'] !== 'forca' || !$state['forca']) {
                    $resultError = 'no_active_game';
                    return $state;
                }
                if (!isSlotOnline($state, otherSlot($mySlot))) {
                    $resultError = 'opponent_offline';
                    return $state;
                }
                $f = $state['forca'];
                if (!empty($f['gameOver'])) {
                    $resultError = 'game_over';
                    return $state;
                }
                if ($f['turnSlot'] !== $mySlot) {
                    $resultError = 'not_your_turn';
                    return $state;
                }
                if (in_array($letter, $f['guessedLetters'], true)) {
                    $resultError = 'letter_used';
                    return $state;
                }

                $f['guessedLetters'][] = $letter;
                $isHit = str_contains($f['word'], $letter);

                if ($isHit) {
                    $f['roundPoints'][$mySlot] = ($f['roundPoints'][$mySlot] ?? 0) + 1;
                } else {
                    $f['wrongCount']++;
                }

                $wordLetters = array_unique(str_split($f['word']));
                $wordComplete = true;
                foreach ($wordLetters as $wl) {
                    if (!in_array($wl, $f['guessedLetters'], true)) {
                        $wordComplete = false;
                        break;
                    }
                }

                if ($wordComplete) {
                    $f['gameOver'] = true;
                    $p1 = $f['roundPoints']['1'] ?? 0;
                    $p2 = $f['roundPoints']['2'] ?? 0;
                    if ($p1 > $p2) {
                        $f['scores']['1'] = ($f['scores']['1'] ?? 0) + 1;
                        $f['outcome'] = 'won_1';
                    } elseif ($p2 > $p1) {
                        $f['scores']['2'] = ($f['scores']['2'] ?? 0) + 1;
                        $f['outcome'] = 'won_2';
                    } else {
                        $f['outcome'] = 'won_tie';
                    }
                } elseif ($f['wrongCount'] >= MAX_ERRORS) {
                    $f['gameOver'] = true;
                    $f['outcome'] = 'lost';
                } else {
                    $f['turnSlot'] = otherSlot($mySlot);
                }

                $state['forca'] = $f;
                return $state;
            });

            if ($resultError) {
                respondError($resultError, 409);
            }
            respondState($state, $clientId);
        }

        default:
            respondError('unknown_action');
    }
} catch (Throwable $e) {
    respondError('server_error', 500);
}
