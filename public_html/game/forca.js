const session = requireAuth();

if (session) {
  initTopbar(session);

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
  const score1Value = document.getElementById("score1Value");
  const score2Value = document.getElementById("score2Value");

  document.getElementById("score1Name").textContent = session.player1;
  document.getElementById("score2Name").textContent = session.player2;

  Object.entries(THEMES).forEach(([key, theme]) => {
    const btn = document.createElement("button");
    btn.className = "theme-btn";
    btn.innerHTML = `<span class="emoji">${theme.emoji}</span>${theme.label}`;
    btn.addEventListener("click", () => startRound(key));
    themeGrid.appendChild(btn);
  });
  const randomBtn = document.createElement("button");
  randomBtn.className = "theme-btn";
  randomBtn.innerHTML = `<span class="emoji">🎲</span>Aleatório`;
  randomBtn.addEventListener("click", () => startRound("aleatorio"));
  themeGrid.appendChild(randomBtn);

  const scores = { 1: 0, 2: 0 };
  let starter = 1;
  let currentPlayer = starter;
  let currentThemeKey = null;
  let wordInfo = null;
  let guessed = new Set();
  let roundPoints = { 1: 0, 2: 0 };
  let wrongCount = 0;
  let gameOver = false;

  function playerName(p) {
    return p === 1 ? session.player1 : session.player2;
  }

  function startRound(themeKey) {
    currentThemeKey = themeKey;
    wordInfo = pickWord(themeKey);
    guessed = new Set();
    roundPoints = { 1: 0, 2: 0 };
    wrongCount = 0;
    gameOver = false;
    starter = starter === 1 ? 2 : 1;
    currentPlayer = starter;

    themeTag.textContent = `Tema: ${wordInfo.themeEmoji} ${wordInfo.themeLabel}`;
    themeScreen.style.display = "none";
    gameScreen.style.display = "block";

    document.querySelectorAll(".part").forEach((p) => (p.style.visibility = "hidden"));
    errorsValue.textContent = `0/${MAX_ERRORS}`;

    renderWord();
    renderKeyboard();
    updateStatus();
  }

  function renderWord() {
    wordDisplay.innerHTML = "";
    wordInfo.word.split("").forEach((letter) => {
      const slot = document.createElement("div");
      slot.className = "letter-slot";
      slot.textContent = guessed.has(letter) ? letter : "";
      wordDisplay.appendChild(slot);
    });
  }

  function renderKeyboard() {
    keyboard.innerHTML = "";
    ALPHABET.forEach((letter) => {
      const btn = document.createElement("button");
      btn.className = "key";
      btn.textContent = letter;
      btn.addEventListener("click", () => handleGuess(letter));
      keyboard.appendChild(btn);
    });
  }

  function updateStatus() {
    if (gameOver) return;
    const badgeClass = currentPlayer === 1 ? "p1" : "p2";
    statusBar.innerHTML = `<span class="turn-badge ${badgeClass}">Vez de ${playerName(currentPlayer)}</span>`;
  }

  function handleGuess(letter) {
    if (gameOver || guessed.has(letter)) return;
    guessed.add(letter);

    const keyBtn = Array.from(keyboard.children).find((b) => b.textContent === letter);
    const isHit = wordInfo.word.includes(letter);

    if (isHit) {
      keyBtn.classList.add("correct");
      roundPoints[currentPlayer]++;
    } else {
      keyBtn.classList.add("wrong");
      wrongCount++;
      errorsValue.textContent = `${wrongCount}/${MAX_ERRORS}`;
      const part = document.getElementById(`part-${wrongCount - 1}`);
      if (part) part.style.visibility = "visible";
    }
    keyBtn.disabled = true;

    renderWord();

    const wordComplete = wordInfo.word.split("").every((l) => guessed.has(l));

    if (wordComplete) {
      finishRound("won");
      return;
    }
    if (wrongCount >= MAX_ERRORS) {
      finishRound("lost");
      return;
    }

    currentPlayer = currentPlayer === 1 ? 2 : 1;
    updateStatus();
  }

  function finishRound(outcome) {
    gameOver = true;
    Array.from(keyboard.children).forEach((b) => (b.disabled = true));

    if (outcome === "won") {
      let winnerText;
      if (roundPoints[1] > roundPoints[2]) {
        scores[1]++;
        winnerText = `${session.player1} mandou bem nessa rodada!`;
      } else if (roundPoints[2] > roundPoints[1]) {
        scores[2]++;
        winnerText = `${session.player2} mandou bem nessa rodada!`;
      } else {
        winnerText = `Rodada equilibrada, os dois acertaram igual!`;
      }
      statusBar.innerHTML = `<span class="turn-badge">🎉 Palavra: ${wordInfo.word} — ${winnerText}</span>`;
    } else {
      statusBar.innerHTML = `<span class="turn-badge">💀 Fim de jogo! A palavra era: ${wordInfo.word}</span>`;
    }

    updateScoreboard();
  }

  function updateScoreboard() {
    score1Value.textContent = scores[1];
    score2Value.textContent = scores[2];
  }

  document.getElementById("newWordBtn").addEventListener("click", () => {
    startRound(currentThemeKey);
  });

  document.getElementById("changeThemeBtn").addEventListener("click", () => {
    gameScreen.style.display = "none";
    themeScreen.style.display = "block";
  });

  updateScoreboard();
}
