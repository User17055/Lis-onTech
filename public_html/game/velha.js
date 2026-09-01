if (!getClientId()) {
  window.location.href = "index.html";
} else {
  const boardEl = document.getElementById("board");
  const statusBar = document.getElementById("statusBar");
  const score1Name = document.getElementById("score1Name");
  const score2Name = document.getElementById("score2Name");
  const score1Value = document.getElementById("score1Value");
  const score2Value = document.getElementById("score2Value");
  const scoreDrawValue = document.getElementById("scoreDrawValue");

  const cells = [];
  for (let i = 0; i < 9; i++) {
    const btn = document.createElement("button");
    btn.className = "cell";
    btn.addEventListener("click", () => makeMove(i));
    boardEl.appendChild(btn);
    cells.push(btn);
  }

  function render(state) {
    if (!state.you) {
      clearClientId();
      window.location.href = "index.html";
      return;
    }

    const mySlot = state.you;
    const oppSlot = mySlot === "1" ? "2" : "1";
    const opponent = state.lobby.slots[oppSlot];
    const me = state.lobby.slots[mySlot];

    if (!opponent || state.activeGame !== "velha" || !state.velha) {
      window.location.href = "index.html";
      return;
    }

    document.getElementById("topbarPlayers").textContent = `${me.name} & ${opponent.name}`;

    const v = state.velha;
    score1Name.textContent = state.lobby.slots["1"].name;
    score2Name.textContent = state.lobby.slots["2"].name;
    score1Value.textContent = v.scores["1"] || 0;
    score2Value.textContent = v.scores["2"] || 0;
    scoreDrawValue.textContent = v.scores.draw || 0;

    v.board.forEach((val, i) => {
      const cell = cells[i];
      cell.textContent = val === 1 ? "X" : val === 2 ? "O" : "";
      cell.className =
        "cell" +
        (val === 1 ? " x" : val === 2 ? " o" : "") +
        (v.winLine && v.winLine.includes(i) ? " win" : "");
      cell.disabled = !!val || v.gameOver || v.turnSlot !== mySlot;
    });

    if (v.gameOver) {
      if (v.winLine) {
        const winnerSlot = String(v.board[v.winLine[0]]);
        const winnerName = state.lobby.slots[winnerSlot].name;
        const badgeClass = winnerSlot === mySlot ? "p1" : "p2";
        statusBar.innerHTML = `<span class="turn-badge ${badgeClass}">🎉 ${winnerName} venceu!</span>`;
      } else {
        statusBar.innerHTML = `<span class="turn-badge">Empate!</span>`;
      }
    } else if (v.turnSlot === mySlot) {
      statusBar.innerHTML = `<span class="turn-badge p1">🟢 Sua vez! (${mySlot === "1" ? "X" : "O"})</span>`;
    } else {
      statusBar.innerHTML = `<span class="turn-badge p2">⏳ Vez de ${opponent.name}...</span>`;
    }
  }

  async function makeMove(index) {
    const result = await apiAction("velha_move", { index });
    if (result.ok) {
      render(result.state);
    } else if (result.error !== "not_your_turn" && result.error !== "cell_taken") {
      alert(friendlyError(result.error));
    }
  }

  document.getElementById("restartRoundBtn").addEventListener("click", async () => {
    const result = await apiAction("velha_restart_round");
    if (result.ok) render(result.state);
  });

  document.getElementById("resetScoreBtn").addEventListener("click", async () => {
    const result = await apiAction("velha_reset_score");
    if (result.ok) render(result.state);
  });

  document.getElementById("backToMenuBtn").addEventListener("click", async () => {
    await apiAction("back_to_menu");
    window.location.href = "index.html";
  });

  document.getElementById("logoutBtn").addEventListener("click", async () => {
    await apiAction("leave");
    clearClientId();
    window.location.href = "index.html";
  });

  startPolling(render, 1200);
}
