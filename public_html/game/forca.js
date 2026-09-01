if (!getClientId()) {
  window.location.href = "index.html";
} else {
  const THEME_META = [
    { key: "animais", label: "Animais", emoji: "🐾" },
    { key: "frutas", label: "Frutas", emoji: "🍎" },
    { key: "paises", label: "Países", emoji: "🌎" },
    { key: "profissoes", label: "Profissões", emoji: "💼" },
    { key: "filmes", label: "Filmes", emoji: "🎬" },
    { key: "objetos", label: "Objetos", emoji: "🪑" },
    { key: "esportes", label: "Esportes", emoji: "⚽" },
    { key: "cores", label: "Cores", emoji: "🎨" },
  ];
  const MAX_ERRORS = 6;
  const ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("");

  const themeScreen = document.getElementById("themeScreen");
  const gameScreen = document.getElementById("gameScreen");
  const themeGrid = document.getElementById("themeGrid");
  const themeTag = document.getElementById("themeTag");
  const statusBar = document.getElementById("statusBar");
  const wordDisplay = document.getElementById("wordDisplay");
  const keyboard = document.getElementById("keyboard");
  const errorsValue = document.getElementById("errorsValue");
  const score1Name = document.getElementById("score1Name");
  const score2Name = document.getElementById("score2Name");
  const score1Value = document.getElementById("score1Value");
  const score2Value = document.getElementById("score2Value");

  THEME_META.forEach((theme) => {
    const btn = document.createElement("button");
    btn.className = "theme-btn";
    btn.innerHTML = `<span class="emoji">${theme.emoji}</span>${theme.label}`;
    btn.addEventListener("click", () => pickTheme(theme.key));
    themeGrid.appendChild(btn);
  });
  const randomBtn = document.createElement("button");
  randomBtn.className = "theme-btn";
  randomBtn.innerHTML = `<span class="emoji">🎲</span>Aleatório`;
  randomBtn.addEventListener("click", () => pickTheme("aleatorio"));
  themeGrid.appendChild(randomBtn);

  const keyButtons = {};
  ALPHABET.forEach((letter) => {
    const btn = document.createElement("button");
    btn.className = "key";
    btn.textContent = letter;
    btn.addEventListener("click", () => guess(letter));
    keyboard.appendChild(btn);
    keyButtons[letter] = btn;
  });

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

    if (!opponent || state.activeGame !== "forca") {
      window.location.href = "index.html";
      return;
    }

    document.getElementById("topbarPlayers").textContent = `${me.name} & ${opponent.name}`;

    const f = state.forca;
    if (!f || f.choosingTheme) {
      themeScreen.style.display = "block";
      gameScreen.style.display = "none";
      return;
    }

    themeScreen.style.display = "none";
    gameScreen.style.display = "block";

    score1Name.textContent = state.lobby.slots["1"].name;
    score2Name.textContent = state.lobby.slots["2"].name;
    score1Value.textContent = f.scores["1"] || 0;
    score2Value.textContent = f.scores["2"] || 0;
    themeTag.textContent = `Tema: ${f.themeEmoji} ${f.themeLabel}`;

    errorsValue.textContent = `${f.wrongCount}/${MAX_ERRORS}`;
    for (let i = 0; i < MAX_ERRORS; i++) {
      const part = document.getElementById(`part-${i}`);
      if (part) part.style.visibility = i < f.wrongCount ? "visible" : "hidden";
    }

    wordDisplay.innerHTML = "";
    f.revealed.forEach((letter) => {
      const slot = document.createElement("div");
      slot.className = "letter-slot";
      slot.textContent = letter || "";
      wordDisplay.appendChild(slot);
    });

    ALPHABET.forEach((letter) => {
      const btn = keyButtons[letter];
      const used = f.guessedLetters.includes(letter);
      btn.disabled = used || f.gameOver || f.turnSlot !== mySlot;
      btn.classList.remove("correct", "wrong");
      if (used) {
        btn.classList.add(f.revealed.includes(letter) ? "correct" : "wrong");
      }
    });

    if (f.gameOver) {
      if (f.outcome === "lost") {
        statusBar.innerHTML = `<span class="turn-badge">💀 Fim de jogo! A palavra era: ${f.solution}</span>`;
      } else {
        let text;
        if (f.outcome === "won_tie") {
          text = "Rodada equilibrada, os dois acertaram igual!";
        } else {
          const winnerSlot = f.outcome === "won_1" ? "1" : "2";
          text = `${state.lobby.slots[winnerSlot].name} mandou bem nessa rodada!`;
        }
        statusBar.innerHTML = `<span class="turn-badge">🎉 Palavra: ${f.solution} — ${text}</span>`;
      }
    } else if (f.turnSlot === mySlot) {
      statusBar.innerHTML = `<span class="turn-badge p1">🟢 Sua vez!</span>`;
    } else {
      statusBar.innerHTML = `<span class="turn-badge p2">⏳ Vez de ${opponent.name}...</span>`;
    }
  }

  async function pickTheme(key) {
    const result = await apiAction("forca_pick_theme", { themeKey: key });
    if (result.ok) render(result.state);
    else alert(friendlyError(result.error));
  }

  async function guess(letter) {
    const result = await apiAction("forca_guess", { letter });
    if (result.ok) {
      render(result.state);
    } else if (result.error !== "not_your_turn" && result.error !== "letter_used") {
      alert(friendlyError(result.error));
    }
  }

  document.getElementById("newWordBtn").addEventListener("click", () => {
    const f = lastState && lastState.forca;
    if (f) pickTheme(f.themeKey);
  });

  document.getElementById("changeThemeBtn").addEventListener("click", async () => {
    const result = await apiAction("forca_change_theme");
    if (result.ok) render(result.state);
  });

  async function backToMenu() {
    await apiAction("back_to_menu");
    window.location.href = "index.html";
  }
  document.getElementById("backToMenuBtn1").addEventListener("click", backToMenu);
  document.getElementById("backToMenuBtn2").addEventListener("click", backToMenu);

  document.getElementById("logoutBtn").addEventListener("click", async () => {
    await apiAction("leave");
    clearClientId();
    window.location.href = "index.html";
  });

  let lastState = null;
  startPolling((state) => {
    lastState = state;
    render(state);
  }, 1200);
}
