const CLIENT_ID_KEY = "jogos.clientId";
const API_URL = "api/state.php";

function getClientId() {
  return localStorage.getItem(CLIENT_ID_KEY);
}

function setClientId(id) {
  localStorage.setItem(CLIENT_ID_KEY, id);
}

function clearClientId() {
  localStorage.removeItem(CLIENT_ID_KEY);
}

async function apiPoll() {
  const clientId = getClientId();
  if (!clientId) {
    return {
      ok: true,
      state: { lobby: { slots: { "1": null, "2": null } }, activeGame: null, you: null },
    };
  }
  const res = await fetch(`${API_URL}?clientId=${encodeURIComponent(clientId)}`, { cache: "no-store" });
  return res.json();
}

async function apiAction(action, data = {}) {
  const clientId = getClientId();
  const res = await fetch(API_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action, clientId, ...data }),
  });
  return res.json();
}

// Chama onUpdate(state) a cada poll bem-sucedido. Retorna uma função que
// interrompe o polling (chame ao sair da tela para não deixar timers soltos).
function startPolling(onUpdate, intervalMs = 1500) {
  let stopped = false;

  async function tick() {
    if (stopped) return;
    try {
      const result = await apiPoll();
      if (result.ok && !stopped) onUpdate(result.state);
    } catch (e) {
      // rede instável — tenta de novo no próximo ciclo
    }
    if (!stopped) setTimeout(tick, intervalMs);
  }

  tick();
  return () => {
    stopped = true;
  };
}

const ERROR_MESSAGES = {
  invalid_password: "Senha incorreta.",
  missing_name: "Digite seu nome.",
  room_full: "A sala já está cheia com dois jogadores.",
  not_your_turn: "Calma, ainda não é sua vez!",
  cell_taken: "Essa casa já foi jogada.",
  letter_used: "Essa letra já foi tentada.",
  game_over: "Essa rodada já terminou.",
  waiting_for_opponent: "Espere o outro jogador entrar.",
  no_active_game: "Nenhum jogo ativo no momento.",
  not_in_lobby: "Sua sessão expirou, entre novamente.",
  invalid_theme: "Tema inválido.",
  invalid_letter: "Letra inválida.",
  invalid_index: "Jogada inválida.",
  invalid_game: "Escolha um jogo válido.",
};

function friendlyError(code) {
  return ERROR_MESSAGES[code] || "Algo deu errado, tenta de novo.";
}
