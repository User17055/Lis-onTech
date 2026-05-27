self.addEventListener('notificationclick', event => {
  event.notification.close();
  const data = event.notification.data || {};
  const phone = data.phone || '';
  const url = phone
    ? `/painel/index.php?pagina=chat&phone=${encodeURIComponent(phone)}`
    : '/painel/index.php?pagina=chat';

  event.waitUntil((async () => {
    const clientsList = await clients.matchAll({type: 'window', includeUncontrolled: true});
    for (const client of clientsList) {
      const clientUrl = new URL(client.url);
      if (clientUrl.origin === self.location.origin && 'focus' in client) {
        await client.focus();
        if ('navigate' in client) return client.navigate(url);
        return;
      }
    }
    if (clients.openWindow) return clients.openWindow(url);
  })());
});
