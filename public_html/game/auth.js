const GAME_PASSWORD = "dudadech";
const SESSION_KEY = "jogos.sessao";

function getSession() {
  try {
    return JSON.parse(localStorage.getItem(SESSION_KEY));
  } catch (e) {
    return null;
  }
}

function setSession(player1, player2) {
  localStorage.setItem(SESSION_KEY, JSON.stringify({ player1, player2 }));
}

function clearSession() {
  localStorage.removeItem(SESSION_KEY);
}

// Redireciona para a tela de login caso ainda não exista uma sessão válida.
// Retorna a sessão para as páginas de jogo usarem os nomes dos jogadores.
function requireAuth() {
  const session = getSession();
  if (!session || !session.player1 || !session.player2) {
    window.location.href = "index.html";
    return null;
  }
  return session;
}

function initTopbar(session) {
  const namesEl = document.getElementById("topbarPlayers");
  if (namesEl) {
    namesEl.textContent = `${session.player1} & ${session.player2}`;
  }
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      clearSession();
      window.location.href = "index.html";
    });
  }
}
