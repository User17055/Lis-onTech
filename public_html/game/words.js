// Banco de palavras do Jogo da Forca, organizado por tema.
// As palavras ficam sem acentos internamente (normalizeWord) para simplificar
// a comparação de letras, mas o tema é exibido normalmente para os jogadores.
const THEMES = {
  animais: {
    label: "Animais",
    emoji: "🐾",
    words: [
      "LEAO", "TIGRE", "ELEFANTE", "GIRAFA", "MACACO", "CACHORRO", "GATO",
      "COELHO", "CAVALO", "ZEBRA", "PINGUIM", "GOLFINHO", "TARTARUGA",
      "CROCODILO", "CANGURU", "PANDA", "COALA", "RINOCERONTE",
      "HIPOPOTAMO", "ONCA", "URSO", "RAPOSA", "LOBO", "AGUIA"
    ]
  },
  frutas: {
    label: "Frutas",
    emoji: "🍎",
    words: [
      "BANANA", "MACA", "LARANJA", "ABACAXI", "MORANGO", "UVA", "MELANCIA",
      "MAMAO", "GOIABA", "MANGA", "PERA", "KIWI", "LIMAO", "COCO",
      "ACEROLA", "JABUTICABA", "CAJU", "PESSEGO", "AMEIXA", "GRAVIOLA"
    ]
  },
  paises: {
    label: "Países",
    emoji: "🌎",
    words: [
      "BRASIL", "ARGENTINA", "PORTUGAL", "FRANCA", "ALEMANHA", "ITALIA",
      "JAPAO", "CANADA", "MEXICO", "ESPANHA", "CHINA", "EGITO",
      "AUSTRALIA", "INDIA", "RUSSIA", "CHILE", "PERU", "COLOMBIA",
      "GRECIA", "SUECIA"
    ]
  },
  profissoes: {
    label: "Profissões",
    emoji: "💼",
    words: [
      "MEDICO", "PROFESSOR", "ENGENHEIRO", "ADVOGADO", "DENTISTA",
      "BOMBEIRO", "POLICIAL", "COZINHEIRO", "PILOTO", "ENFERMEIRO",
      "VETERINARIO", "ARQUITETO", "JORNALISTA", "ELETRICISTA",
      "PROGRAMADOR", "ATOR", "CANTOR", "PINTOR", "MOTORISTA", "AGRICULTOR"
    ]
  },
  filmes: {
    label: "Filmes",
    emoji: "🎬",
    words: [
      "TITANIC", "AVATAR", "MATRIX", "SHREK", "FROZEN", "MOANA", "UP",
      "VINGADORES", "BATMAN", "SUPERMAN", "ALADDIN", "MULAN",
      "RATATOUILLE", "GRAVIDADE", "COCO", "TROVOADA", "TUBARAO"
    ]
  },
  objetos: {
    label: "Objetos",
    emoji: "🪑",
    words: [
      "CADEIRA", "MESA", "COMPUTADOR", "CELULAR", "RELOGIO", "JANELA",
      "PORTA", "LIVRO", "CANETA", "GARRAFA", "MOCHILA", "ESPELHO",
      "LAMPADA", "TESOURA", "SOMBRINHA", "TELEVISAO", "GELADEIRA",
      "FOGAO", "SOFA", "TAPETE"
    ]
  },
  esportes: {
    label: "Esportes",
    emoji: "⚽",
    words: [
      "FUTEBOL", "BASQUETE", "VOLEIBOL", "NATACAO", "TENIS", "SURFE",
      "SKATE", "CICLISMO", "ATLETISMO", "JUDO", "KARATE", "BOXE",
      "GINASTICA", "HANDEBOL", "RUGBY"
    ]
  },
  cores: {
    label: "Cores",
    emoji: "🎨",
    words: [
      "VERMELHO", "AZUL", "AMARELO", "VERDE", "LARANJA", "ROXO", "ROSA",
      "PRETO", "BRANCO", "CINZA", "MARROM", "DOURADO", "PRATEADO",
      "TURQUESA", "BEGE"
    ]
  }
};

function normalizeWord(word) {
  return word
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toUpperCase();
}

function pickRandomTheme() {
  const keys = Object.keys(THEMES);
  return keys[Math.floor(Math.random() * keys.length)];
}

function pickWord(themeKey) {
  const key = themeKey === "aleatorio" ? pickRandomTheme() : themeKey;
  const theme = THEMES[key];
  const raw = theme.words[Math.floor(Math.random() * theme.words.length)];
  return {
    themeKey: key,
    themeLabel: theme.label,
    themeEmoji: theme.emoji,
    word: normalizeWord(raw)
  };
}
