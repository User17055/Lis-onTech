if (!getClientId()) {
  location.href = "index.html";
} else {
  const THEMES = [
    { key: "animais", label: "Animais", icon: "paw", color: "purple" },
    { key: "frutas", label: "Frutas", icon: "apple", color: "red" },
    { key: "paises", label: "Países", icon: "globe", color: "blue" },
    { key: "profissoes", label: "Profissões", icon: "briefcase", color: "orange" },
    { key: "filmes", label: "Filmes", icon: "film", color: "pink" },
    { key: "objetos", label: "Objetos", icon: "chair", color: "green" },
    { key: "esportes", label: "Esportes", icon: "ball", color: "cyan" },
    { key: "cores", label: "Cores", icon: "palette", color: "yellow" },
    { key: "aleatorio", label: "Surpresa", icon: "dice", color: "rainbow" },
  ];
  const THEME_BY_KEY = Object.fromEntries(THEMES.map(theme => [theme.key, theme]));
  const ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");
  const MAX_ERRORS = 6;
  const themeScreen = document.getElementById("themeScreen");
  const gameScreen = document.getElementById("gameScreen");
  const themeGrid = document.getElementById("themeGrid");
  const themeWait = document.getElementById("themeWait");
  const wordDisplay = document.getElementById("wordDisplay");
  const keyboard = document.getElementById("keyboard");
  const statusBar = document.getElementById("statusBar");
  let lastState = null;
  let lastWrongCount = 0;
  let choosing = false;

  hydrateIcons();

  THEMES.forEach(theme => {
    const button = document.createElement("button");
    button.type = "button";
    button.dataset.themeKey = theme.key;
    button.className = `theme-btn theme-${theme.color}`;
    button.innerHTML = `<span class="theme-icon">${iconSvg(theme.icon)}</span><strong>${theme.label}</strong><small>Escolher tema</small>`;
    button.addEventListener("click", () => pickTheme(theme.key));
    themeGrid.appendChild(button);
  });

  const keyButtons = {};
  ALPHABET.forEach(letter => {
    const button = document.createElement("button");
    button.className = "key";
    button.textContent = letter;
    button.addEventListener("click", () => guess(letter));
    keyboard.appendChild(button);
    keyButtons[letter] = button;
  });

  function render(state) {
    lastState = state;
    if (!state.you) {
      clearClientId();
      location.href = "index.html";
      return;
    }

    const mySlot = state.you;
    const opponentSlot = mySlot === "1" ? "2" : "1";
    const me = state.lobby.slots[mySlot];
    const opponent = state.lobby.slots[opponentSlot];
    if (!opponent || state.activeGame !== "forca") {
      location.href = "index.html";
      return;
    }

    const online = isOpponentOnline(state);
    setPauseOverlay(!online, opponent.name);
    document.getElementById("topbarPlayers").textContent = `${me.name} × ${opponent.name}`;
    const f = state.forca;

    if (!f || f.choosingTheme) {
      themeScreen.style.display = "block";
      gameScreen.style.display = "none";
      const myTheme = f?.themeChoices?.[mySlot] || null;
      const opponentHasChosen = Boolean(f?.themeChoices?.[opponentSlot]);
      const waiting = Boolean(myTheme);

      themeGrid.hidden = waiting;
      themeWait.hidden = !waiting;
      document.getElementById("themeTitle").textContent = waiting ? "Tema escolhido!" : "Escolha o seu tema";
      document.getElementById("themeSubtitle").textContent = waiting
        ? `${opponentHasChosen ? "As duas escolhas chegaram." : `Aguardando ${opponent.name} escolher`}. O sorteio começa automaticamente.`
        : "Cada pessoa escolhe uma categoria. A rodada sorteia uma das duas opções.";
      document.getElementById("themeWaitTitle").textContent = opponentHasChosen ? "Sorteando o tema..." : `${opponent.name} ainda está escolhendo...`;
      document.getElementById("themeWaitText").textContent = myTheme
        ? `Sua escolha: ${THEME_BY_KEY[myTheme]?.label || "Surpresa"}.`
        : "A rodada vai começar automaticamente.";
      themeGrid.querySelectorAll("button").forEach(button => {
        button.disabled = choosing || waiting || !online;
        button.classList.toggle("selected", button.dataset.themeKey === myTheme);
      });
      return;
    }

    choosing = false;
    themeScreen.style.display = "none";
    gameScreen.style.display = "block";
    document.getElementById("score1Name").textContent = state.lobby.slots["1"].name;
    document.getElementById("score2Name").textContent = state.lobby.slots["2"].name;
    document.getElementById("score1Value").textContent = f.scores["1"] || 0;
    document.getElementById("score2Value").textContent = f.scores["2"] || 0;
    const chosenTheme = THEME_BY_KEY[f.themeKey] || THEME_BY_KEY.aleatorio;
    document.getElementById("themeTag").innerHTML = `${iconSvg(chosenTheme.icon)}<span>Tema sorteado: ${f.themeLabel}</span>`;
    document.getElementById("errorsValue").textContent = `${f.wrongCount}/${MAX_ERRORS}`;

    for (let i = 0; i < MAX_ERRORS; i++) {
      const part = document.getElementById(`part-${i}`);
      part.classList.toggle("visible", i < f.wrongCount);
      part.classList.toggle("just-shown", i === f.wrongCount - 1 && f.wrongCount > lastWrongCount);
    }
    lastWrongCount = f.wrongCount;

    wordDisplay.innerHTML = "";
    f.revealed.forEach(letter => {
      const slot = document.createElement("div");
      const isSeparator = letter === " " || letter === "-";
      slot.className = `letter-slot${letter ? " revealed" : ""}${isSeparator ? " separator" : ""}`;
      slot.textContent = letter === " " ? "\u00a0" : (letter || "");
      if (letter === " ") slot.setAttribute("aria-label", "espaço");
      wordDisplay.appendChild(slot);
    });

    document.getElementById("wordTip").textContent = f.hintLetter
      ? `Dica usada: a letra ${f.hintLetter} foi revelada.`
      : "Clique em uma letra para jogar";

    ALPHABET.forEach(letter => {
      const button = keyButtons[letter];
      const used = f.guessedLetters.includes(letter);
      button.disabled = !online || used || f.gameOver || f.turnSlot !== mySlot;
      button.className = `key${used ? (f.revealed.includes(letter) ? " correct" : " wrong") : ""}`;
    });

    document.getElementById("newWordBtn").disabled = !online;
    document.getElementById("changeThemeBtn").disabled = !online;
    const hintBtn = document.getElementById("hintBtn");
    hintBtn.disabled = !online || f.gameOver || f.hintUsed || f.turnSlot !== mySlot;
    hintBtn.innerHTML = f.hintUsed
      ? `${iconSvg("bulb")} Dica usada`
      : `${iconSvg("bulb")} Pedir dica`;
    if (f.gameOver && f.outcome === "lost") {
      statusBar.innerHTML = `<span class="turn-badge danger">${iconSvg("alert")} A palavra era <b>${f.solution}</b></span>`;
    } else if (f.gameOver) {
      const winner = f.outcome === "won_tie" ? "Vocês empataram!" : `${state.lobby.slots[f.outcome === "won_1" ? "1" : "2"].name} venceu!`;
      statusBar.innerHTML = `<span class="turn-badge winner">${iconSvg("party")} ${f.solution} — ${winner}</span>`;
    } else if (f.turnSlot === mySlot) {
      statusBar.innerHTML = '<span class="turn-badge p1"><i></i>Sua vez! Escolha uma letra</span>';
    } else {
      statusBar.innerHTML = `<span class="turn-badge p2">${iconSvg("clock")} Vez de ${opponent.name}</span>`;
    }
  }

  async function pickTheme(themeKey) {
    if (choosing) return;
    choosing = true;
    themeGrid.querySelectorAll("button").forEach(button => { button.disabled = true; });
    const result = await apiAction("forca_pick_theme", { themeKey });
    choosing = false;
    if (result.ok) render(result.state);
    else {
      if (!["opponent_offline", "theme_selection_closed"].includes(result.error)) alert(friendlyError(result.error));
      if (lastState) render(lastState);
    }
  }

  async function guess(letter) {
    const result = await apiAction("forca_guess", { letter });
    if (result.ok) render(result.state);
    else if (!["not_your_turn", "letter_used", "opponent_offline"].includes(result.error)) alert(friendlyError(result.error));
  }

  document.getElementById("newWordBtn").addEventListener("click", async () => {
    const result = await apiAction("forca_new_word");
    if (result.ok) render(result.state);
    else if (result.error !== "opponent_offline") alert(friendlyError(result.error));
  });
  document.getElementById("changeThemeBtn").addEventListener("click", async () => {
    const result = await apiAction("forca_change_theme");
    if (result.ok) render(result.state);
    else if (result.error !== "opponent_offline") alert(friendlyError(result.error));
  });
  document.getElementById("hintBtn").addEventListener("click", async () => {
    const result = await apiAction("forca_hint");
    if (result.ok) render(result.state);
    else if (!['not_your_turn', 'hint_used', 'opponent_offline'].includes(result.error)) alert(friendlyError(result.error));
  });
  async function backToMenu() {
    await apiAction("back_to_menu");
    location.href = "index.html";
  }
  document.getElementById("backToMenuBtn1").addEventListener("click", backToMenu);
  document.getElementById("backToMenuBtn2").addEventListener("click", backToMenu);
  document.getElementById("logoutBtn").addEventListener("click", async () => {
    await apiAction("leave");
    clearClientId();
    location.href = "index.html";
  });
  startPolling(render);
}
