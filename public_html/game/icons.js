const ICON_PATHS = {
  gamepad: '<line x1="6" y1="11" x2="10" y2="11"/><line x1="8" y1="9" x2="8" y2="13"/><line x1="15" y1="12" x2="15.01" y2="12"/><line x1="18" y1="10" x2="18.01" y2="10"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258-.007-.05-.011-.1-.017-.152A4 4 0 0 0 17.32 5z"/>',
  trophy: '<path d="M8 4h8v4a4 4 0 0 1-8 0V4Z"/><path d="M8 6H5v1a4 4 0 0 0 4 4M16 6h3v1a4 4 0 0 1-4 4M12 12v5M8 20h8M9 17h6"/>',
  letters: '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="m7 16 3-8 3 8M8 13h4M15 9h3M16.5 9v7"/>',
  bulb: '<path d="M9 18h6M10 21h4M8.5 15.5a6 6 0 1 1 7 0c-.9.7-1.5 1.5-1.5 2.5h-4c0-1-.6-1.8-1.5-2.5Z"/><path d="M12 2V1M4.2 5.2l-.7-.7M19.8 5.2l.7-.7"/>',
  sparkle: '<path d="m12 2 1.5 5.3L19 9l-5.5 1.7L12 16l-1.5-5.3L5 9l5.5-1.7L12 2Z"/><path d="m19 15 .7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15Z"/>',
  palette: '<path d="M12 3a9 9 0 0 0 0 18h1.2a2 2 0 0 0 1.4-3.4 2 2 0 0 1 1.4-3.4h1.2A3.8 3.8 0 0 0 21 10.4 9 9 0 0 0 12 3Z"/><path d="M7.5 10h.01M9.5 6.5h.01M14 6.5h.01M17 9h.01"/>',
  wave: '<path d="M8 12V5a2 2 0 0 1 4 0v5-7a2 2 0 0 1 4 0v7-5a2 2 0 0 1 4 0v8a8 8 0 0 1-16 0V9a2 2 0 0 1 4 0v3Z"/><path d="m4 13-1.5-1.5a2 2 0 0 0-2.5 3L5 20"/>',
  paw: '<path d="M8.5 11.5c-2.7 1.8-4.4 5.7-2.1 7.4 1.8 1.3 3.5-.5 5.6-.5s3.8 1.8 5.6.5c2.3-1.7.6-5.6-2.1-7.4-2.2-1.5-4.8-1.5-7 0Z"/><ellipse cx="6" cy="8" rx="2" ry="3"/><ellipse cx="18" cy="8" rx="2" ry="3"/><ellipse cx="11" cy="5" rx="2" ry="3"/>',
  apple: '<path d="M12 7c-3-3-8-1-8 4 0 5 4 10 8 10s8-5 8-10c0-5-5-7-8-4Z"/><path d="M12 7c0-3 2-5 5-5M12 6c-2 0-3-1-4-3"/>',
  globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
  briefcase: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 12h18M10 12v2h4v-2"/>',
  film: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 5v14M17 5v14M3 9h4M17 9h4M3 15h4M17 15h4"/>',
  chair: '<path d="M7 13V5a3 3 0 0 1 6 0v8M5 13h14v4H5zM7 17v4M17 17v4"/>',
  ball: '<circle cx="12" cy="12" r="9"/><path d="m12 7 4 3-1.5 5h-5L8 10l4-3ZM12 7V3M16 10l4-1M14.5 15l3 3M9.5 15l-3 3M8 10 4 9"/>',
  dice: '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 8h.01M16 8h.01M12 12h.01M8 16h.01M16 16h.01"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  party: '<path d="m4 20 4-12 8 8-12 4Z"/><path d="m13 5 1-2M17 8l3-1M16 3l1-1M19 12l2 1"/><path d="M9 11c3-1 4-2 5-5"/>',
  alert: '<circle cx="12" cy="12" r="9"/><path d="M9 10h.01M15 10h.01M9 17c1.5-3 4.5-3 6 0"/>',
  handshake: '<path d="m8 12 3-3a2 2 0 0 1 3 0l2 2M3 11l4-4 3 2M21 11l-4-4-3 2M7 15l3 3a2 2 0 0 0 3 0l4-4M5 13l-2-2M19 13l2-2"/>',
  circle: '<circle cx="12" cy="12" r="8"/>',
};

function iconSvg(name, className = "") {
  const paths = ICON_PATHS[name] || ICON_PATHS.sparkle;
  return `<svg class="ui-icon ${className}" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${paths}</svg>`;
}

function hydrateIcons(root = document) {
  root.querySelectorAll("[data-icon]").forEach(element => {
    element.innerHTML = iconSvg(element.dataset.icon, element.dataset.iconClass || "");
  });
}
