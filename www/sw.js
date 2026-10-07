// Service worker push notifikací; obsah je prostý text, URL jen https://*.hkfree.org (H5, H13)
function jePovolenaUrl(url) {
    try {
        var u = new URL(url);
        return u.protocol === 'https:' && !u.port && !u.username
            && (u.hostname === 'hkfree.org' || u.hostname.endsWith('.hkfree.org'));
    } catch (e) {
        return false;
    }
}

self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { /* neplatný payload */ }
    event.waitUntil(self.registration.showNotification(String(data.titulek || 'HKFree'), {
        body: String(data.text || ''),
        icon: 'favicon.ico',
        data: { url: data.url && jePovolenaUrl(data.url) ? data.url : null }
    }));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = event.notification.data && event.notification.data.url;
    if (url && jePovolenaUrl(url)) {
        event.waitUntil(clients.openWindow(url));
    }
});
