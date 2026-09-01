const session = requireAuth();

if (session) {
  initTopbar(session);

  const WIN_LINES = [
    [0, 1, 2], [3, 4, 5], [6, 7, 8],
    [0, 3, 6], [1, 4, 7], [2, 5, 8],
    [0, 4, 8], [2, 4, 6]
  ];

  const boardEl = document.getElementById("board");
  const statusBar = document.getElementById("statusBar");
  const score1Value = document.getElementById("score1Value");
  const score2Value = document.getElementById("score2Value");
  const scoreDrawValue = document.getElementById("scoreDrawValue");

  document.getElementById("score1Name").textContent = session.player1;
  document.getElementById("score2Name").textContent = session.player2;

  const scores = { 1: 0, 2: 0, draw: 0 };
  let board = Array(9).fill(null);
  let starter = 1;
  let currentPlayer = starter;
  let gameOver = false;

  const cells = [];
  for (let i = 0; i < 9; i++) {
    const btn = document.createElement("button");
    btn.className = "cell";
    btn.addEventListener("click", () => handleMove(i));
    boardEl.appendChild(btn);
    cells.push(btn);
  }

  function playerName(p) {
    return p === 1 ? session.player1 : session.player2;
  }

  function playerSymbol(p) {
    return p === 1 ? "X" : "O";
  }

  function updateStatus() {
    if (gameOver) return;
    const badgeClass = currentPlayer === 1 ? "p1" : "p2";
    statusBar.innerHTML =
      `<span class="turn-badge ${badgeClass}">Vez de ${playerName(currentPlayer)} (${playerSymbol(currentPlayer)})</span>`;
  }

  function renderBoard() {
    board.forEach((val, i) => {
      const cell = cells[i];
      cell.textContent = val ? playerSymbol(val) : "";
      cell.className = "cell" + (val === 1 ? " x" : val === 2 ? " o" : "");
      cell.disabled = !!val || gameOver;
    });
  }

  function checkWinner() {
    for (const line of WIN_LINES) {
      const [a, b, c] = line;
      if (board[a] && board[a] === board[b] && board[a] === board[c]) {
        return { player: board[a], line };
      }
    }
    if (board.every((v) => v !== null)) {
      return { draw: true };
    }
    return null;
  }

  function handleMove(index) {
    if (gameOver || board[index]) return;
    board[index] = currentPlayer;
    renderBoard();

    const result = checkWinner();
    if (result && result.player) {
      gameOver = true;
      scores[result.player]++;
      updateScoreboard();
      result.line.forEach((i) => cells[i].classList.add("win"));
      cells.forEach((c) => (c.disabled = true));
      const badgeClass = result.player === 1 ? "p1" : "p2";
      statusBar.innerHTML =
        `<span class="turn-badge ${badgeClass}">🎉 ${playerName(result.player)} venceu!</span>`;
      return;
    }

    if (result && result.draw) {
      gameOver = true;
      scores.draw++;
      updateScoreboard();
      statusBar.innerHTML = `<span class="turn-badge">Empate!</span>`;
      return;
    }

    currentPlayer = currentPlayer === 1 ? 2 : 1;
    updateStatus();
  }

  function updateScoreboard() {
    score1Value.textContent = scores[1];
    score2Value.textContent = scores[2];
    scoreDrawValue.textContent = scores.draw;
  }

  function newRound() {
    board = Array(9).fill(null);
    gameOver = false;
    starter = starter === 1 ? 2 : 1;
    currentPlayer = starter;
    cells.forEach((c) => c.classList.remove("win"));
    renderBoard();
    updateStatus();
  }

  document.getElementById("restartRoundBtn").addEventListener("click", newRound);
  document.getElementById("resetScoreBtn").addEventListener("click", () => {
    scores[1] = 0;
    scores[2] = 0;
    scores.draw = 0;
    updateScoreboard();
    starter = 1;
    board = Array(9).fill(null);
    gameOver = false;
    currentPlayer = starter;
    cells.forEach((c) => c.classList.remove("win"));
    renderBoard();
    updateStatus();
  });

  renderBoard();
  updateStatus();
  updateScoreboard();
}
