if (!getClientId()) {
  location.href = "index.html";
} else {
  hydrateIcons();
  const boardEl = document.getElementById("board");
  const statusBar = document.getElementById("statusBar");
  const cells = [];
  let renderedBoard = Array(9).fill(null);

  for (let i = 0; i < 9; i++) {
    const button = document.createElement("button");
    button.className = "cell";
    button.setAttribute("aria-label", `Casa ${i + 1}`);
    button.addEventListener("click", () => makeMove(i));
    boardEl.appendChild(button);
    cells.push(button);
  }

  function render(state) {
    if (!state.you) { clearClientId(); location.href = "index.html"; return; }
    const mySlot = state.you;
    const opponentSlot = mySlot === "1" ? "2" : "1";
    const me = state.lobby.slots[mySlot];
    const opponent = state.lobby.slots[opponentSlot];
    if (!opponent || state.activeGame !== "velha" || !state.velha) { location.href = "index.html"; return; }

    const online = isOpponentOnline(state);
    setPauseOverlay(!online, opponent.name);
    document.getElementById("topbarPlayers").textContent = `${me.name} × ${opponent.name}`;
    const v = state.velha;
    document.getElementById("score1Name").textContent = state.lobby.slots["1"].name;
    document.getElementById("score2Name").textContent = state.lobby.slots["2"].name;
    document.getElementById("score1Value").textContent = v.scores["1"] || 0;
    document.getElementById("score2Value").textContent = v.scores["2"] || 0;
    document.getElementById("scoreDrawValue").textContent = v.scores.draw || 0;

    v.board.forEach((value, index) => {
      const cell = cells[index];
      const isNew = value && !renderedBoard[index];
      cell.textContent = value === 1 ? "X" : value === 2 ? "O" : "";
      cell.className = `cell${value === 1 ? " x" : value === 2 ? " o" : ""}${v.winLine?.includes(index) ? " win" : ""}${isNew ? " pop" : ""}`;
      cell.disabled = !online || Boolean(value) || v.gameOver || v.turnSlot !== mySlot;
    });
    renderedBoard = [...v.board];

    if (v.gameOver && v.winLine) {
      const winnerSlot = String(v.board[v.winLine[0]]);
      statusBar.innerHTML = `<span class="turn-badge winner">${iconSvg("party")} ${state.lobby.slots[winnerSlot].name} venceu!</span>`;
      boardEl.classList.add("celebrate");
    } else if (v.gameOver) {
      statusBar.innerHTML = `<span class="turn-badge">${iconSvg("handshake")} Deu empate!</span>`;
    } else if (v.turnSlot === mySlot) {
      statusBar.innerHTML = `<span class="turn-badge p1"><i></i>Sua vez! Você é ${mySlot === "1" ? "X" : "O"}</span>`;
    } else {
      statusBar.innerHTML = `<span class="turn-badge p2">${iconSvg("clock")} Vez de ${opponent.name}</span>`;
    }
  }

  async function makeMove(index) {
    const result = await apiAction("velha_move", { index });
    if (result.ok) render(result.state);
    else if (!["not_your_turn", "cell_taken", "opponent_offline"].includes(result.error)) alert(friendlyError(result.error));
  }

  document.getElementById("restartRoundBtn").addEventListener("click", async () => { const r = await apiAction("velha_restart_round"); if (r.ok) { renderedBoard.fill(null); boardEl.classList.remove("celebrate"); render(r.state); } });
  document.getElementById("resetScoreBtn").addEventListener("click", async () => { const r = await apiAction("velha_reset_score"); if (r.ok) render(r.state); });
  document.getElementById("backToMenuBtn").addEventListener("click", async () => { await apiAction("back_to_menu"); location.href = "index.html"; });
  document.getElementById("logoutBtn").addEventListener("click", async () => { await apiAction("leave"); clearClientId(); location.href = "index.html"; });
  startPolling(render);
}
