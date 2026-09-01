if (!getClientId()) {
  location.href = "index.html";
} else {
  const THEMES = [
    { key: "animais", label: "Animais", emoji: "🐾", color: "purple" }, { key: "frutas", label: "Frutas", emoji: "🍎", color: "red" },
    { key: "paises", label: "Países", emoji: "🌎", color: "blue" }, { key: "profissoes", label: "Profissões", emoji: "💼", color: "orange" },
    { key: "filmes", label: "Filmes", emoji: "🎬", color: "pink" }, { key: "objetos", label: "Objetos", emoji: "🪑", color: "green" },
    { key: "esportes", label: "Esportes", emoji: "⚽", color: "cyan" }, { key: "cores", label: "Cores", emoji: "🎨", color: "yellow" },
    { key: "aleatorio", label: "Surpresa", emoji: "🎲", color: "rainbow" },
  ];
  const ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");
  const MAX_ERRORS = 6;
  const themeScreen = document.getElementById("themeScreen"), gameScreen = document.getElementById("gameScreen");
  const themeGrid = document.getElementById("themeGrid"), themeWait = document.getElementById("themeWait");
  const wordDisplay = document.getElementById("wordDisplay"), keyboard = document.getElementById("keyboard"), statusBar = document.getElementById("statusBar");
  let lastState = null, lastWrongCount = 0, choosing = false;

  THEMES.forEach(theme => {
    const button = document.createElement("button");
    button.className = `theme-btn theme-${theme.color}`;
    button.innerHTML = `<span class="emoji">${theme.emoji}</span><strong>${theme.label}</strong><small>Jogar agora</small>`;
    button.addEventListener("click", () => pickTheme(theme.key));
    themeGrid.appendChild(button);
  });

  const keyButtons = {};
  ALPHABET.forEach(letter => {
    const button = document.createElement("button"); button.className = "key"; button.textContent = letter;
    button.addEventListener("click", () => guess(letter)); keyboard.appendChild(button); keyButtons[letter] = button;
  });

  function render(state) {
    lastState = state;
    if (!state.you) { clearClientId(); location.href = "index.html"; return; }
    const mySlot = state.you, opponentSlot = mySlot === "1" ? "2" : "1";
    const me = state.lobby.slots[mySlot], opponent = state.lobby.slots[opponentSlot];
    if (!opponent || state.activeGame !== "forca") { location.href = "index.html"; return; }
    const online = isOpponentOnline(state);
    setPauseOverlay(!online, opponent.name);
    document.getElementById("topbarPlayers").textContent = `${me.name} × ${opponent.name}`;
    const f = state.forca;

    if (!f || f.choosingTheme) {
      themeScreen.style.display = "block"; gameScreen.style.display = "none";
      const myChoice = !f?.themePickerSlot || f.themePickerSlot === mySlot;
      themeGrid.hidden = !myChoice; themeWait.hidden = myChoice;
      document.getElementById("themeTitle").textContent = myChoice ? "Qual será o tema?" : `${opponent.name} está escolhendo`;
      document.getElementById("themeSubtitle").textContent = myChoice ? "Escolha uma categoria para os dois jogarem." : "Aguarde só um pouquinho. A rodada abrirá para vocês dois.";
      themeGrid.querySelectorAll("button").forEach(button => button.disabled = choosing || !online);
      return;
    }

    choosing = false; themeScreen.style.display = "none"; gameScreen.style.display = "block";
    document.getElementById("score1Name").textContent = state.lobby.slots["1"].name;
    document.getElementById("score2Name").textContent = state.lobby.slots["2"].name;
    document.getElementById("score1Value").textContent = f.scores["1"] || 0;
    document.getElementById("score2Value").textContent = f.scores["2"] || 0;
    document.getElementById("themeTag").textContent = `${f.themeEmoji} Tema: ${f.themeLabel}`;
    document.getElementById("errorsValue").textContent = `${f.wrongCount}/${MAX_ERRORS}`;

    for (let i = 0; i < MAX_ERRORS; i++) {
      const part = document.getElementById(`part-${i}`);
      part.classList.toggle("visible", i < f.wrongCount);
      part.classList.toggle("just-shown", i === f.wrongCount - 1 && f.wrongCount > lastWrongCount);
    }
    lastWrongCount = f.wrongCount;

    wordDisplay.innerHTML = "";
    f.revealed.forEach(letter => { const slot = document.createElement("div"); slot.className = `letter-slot${letter ? " revealed" : ""}`; slot.textContent = letter || ""; wordDisplay.appendChild(slot); });
    ALPHABET.forEach(letter => {
      const button = keyButtons[letter], used = f.guessedLetters.includes(letter);
      button.disabled = !online || used || f.gameOver || f.turnSlot !== mySlot;
      button.className = `key${used ? (f.revealed.includes(letter) ? " correct" : " wrong") : ""}`;
    });

    if (f.gameOver && f.outcome === "lost") statusBar.innerHTML = `<span class="turn-badge danger">😮 A palavra era <b>${f.solution}</b></span>`;
    else if (f.gameOver) {
      const winner = f.outcome === "won_tie" ? "Vocês empataram!" : `${state.lobby.slots[f.outcome === "won_1" ? "1" : "2"].name} venceu!`;
      statusBar.innerHTML = `<span class="turn-badge winner">🎉 ${f.solution} — ${winner}</span>`;
    } else if (f.turnSlot === mySlot) statusBar.innerHTML = `<span class="turn-badge p1"><i></i>Sua vez! Escolha uma letra</span>`;
    else statusBar.innerHTML = `<span class="turn-badge p2">⏳ Vez de ${opponent.name}</span>`;
  }

  async function pickTheme(themeKey) {
    if (choosing) return; choosing = true;
    themeGrid.querySelectorAll("button").forEach(button => button.disabled = true);
    const result = await apiAction("forca_pick_theme", { themeKey });
    choosing = false;
    if (result.ok) render(result.state); else { if (!["opponent_offline", "not_theme_picker"].includes(result.error)) alert(friendlyError(result.error)); if (lastState) render(lastState); }
  }
  async function guess(letter) { const result = await apiAction("forca_guess", { letter }); if (result.ok) render(result.state); else if (!["not_your_turn", "letter_used", "opponent_offline"].includes(result.error)) alert(friendlyError(result.error)); }
  document.getElementById("newWordBtn").addEventListener("click", () => { if (lastState?.forca) pickTheme(lastState.forca.themeKey); });
  document.getElementById("changeThemeBtn").addEventListener("click", async () => { const result = await apiAction("forca_change_theme"); if (result.ok) render(result.state); });
  async function backToMenu() { await apiAction("back_to_menu"); location.href = "index.html"; }
  document.getElementById("backToMenuBtn1").addEventListener("click", backToMenu); document.getElementById("backToMenuBtn2").addEventListener("click", backToMenu);
  document.getElementById("logoutBtn").addEventListener("click", async () => { await apiAction("leave"); clearClientId(); location.href = "index.html"; });
  startPolling(render);
}
