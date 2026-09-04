const CLIENT_ID_KEY = "jogos.clientId";
const API_URL = "api/state.php";

function getClientId() { return localStorage.getItem(CLIENT_ID_KEY); }
function setClientId(id) { localStorage.setItem(CLIENT_ID_KEY, id); }
function clearClientId() { localStorage.removeItem(CLIENT_ID_KEY); }

async function apiPoll() {
  const clientId = getClientId();
  if (!clientId) return { ok: true, state: { lobby: { slots: { "1": null, "2": null } }, activeGame: null, you: null } };
  const res = await fetch(`${API_URL}?clientId=${encodeURIComponent(clientId)}`, { cache: "no-store" });
  return res.json();
}

async function apiAction(action, data = {}) {
  const res = await fetch(API_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ action, clientId: getClientId(), ...data }),
  });
  return res.json();
}

function startPolling(onUpdate, intervalMs = 1200) {
  let stopped = false;
  let timer = null;
  async function tick() {
    if (stopped) return;
    try {
      const result = await apiPoll();
      if (result.ok && !stopped) onUpdate(result.state);
    } catch (_) {
      // Uma queda rápida de rede não encerra a partida.
    }
    if (!stopped) timer = setTimeout(tick, intervalMs);
  }
  tick();
  return () => { stopped = true; clearTimeout(timer); };
}

function isOpponentOnline(state) {
  if (!state.you) return false;
  const opponentSlot = state.you === "1" ? "2" : "1";
  return Boolean(state.lobby.slots[opponentSlot]?.online);
}

function setPauseOverlay(paused, opponentName = "O outro jogador") {
  const overlay = document.getElementById("pauseOverlay");
  if (!overlay) return;
  const name = overlay.querySelector("[data-opponent-name]");
  if (name) name.textContent = opponentName;
  overlay.classList.toggle("show", paused);
  overlay.setAttribute("aria-hidden", paused ? "false" : "true");
}

const ERROR_MESSAGES = {
  invalid_password: "Senha incorreta.", missing_name: "Digite seu nome.",
  room_full: "A sala já está cheia com dois jogadores.", not_your_turn: "Calma, ainda não é sua vez!",
  cell_taken: "Essa casa já foi jogada.", letter_used: "Essa letra já foi tentada.",
  game_over: "Essa rodada já terminou.", waiting_for_opponent: "Espere o outro jogador entrar.",
  opponent_offline: "Partida pausada: o outro jogador está ausente.",
  theme_selection_closed: "A escolha de temas desta rodada já terminou.", no_active_game: "Nenhum jogo ativo no momento.",
  not_in_lobby: "Sua sessão expirou, entre novamente.", invalid_theme: "Tema inválido.",
  invalid_letter: "Letra inválida.", invalid_index: "Jogada inválida.", invalid_game: "Escolha um jogo válido.",
  hint_used: "A dica desta rodada já foi usada.", no_hint_available: "Não há outra letra disponível para revelar.",
};

function friendlyError(code) { return ERROR_MESSAGES[code] || "Algo deu errado. Tente novamente."; }
