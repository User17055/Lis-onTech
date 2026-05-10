let lastTrackId = null;

async function loadSpotifyData() {
  const res = await fetch('/api/spotify-now-playing.php');
  if (!res.ok) return;

  const data = await res.json();
  if (!data || !data.item) return;

  const trackId = data.item.id;

  // Só atualiza se a música mudou
  if (trackId !== lastTrackId) {
    lastTrackId = trackId;

    const iframe = `
      <iframe style="border-radius:12px"
        src="https://open.spotify.com/embed/track/${trackId}?utm_source=generator&theme=0"
        width="300" height="80" frameborder="0"
        allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
        loading="lazy"></iframe>
    `;

    const container = document.getElementById('spotify-now-playing');
    container.innerHTML = iframe;
    container.style.display = 'block';
  }
}

setInterval(loadSpotifyData, 10000);
loadSpotifyData();
