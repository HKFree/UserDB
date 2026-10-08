// Service worker push notifikací; obsah je prostý text, URL jen https://*.hkfree.org (H5, H13)
function povolenaUrl(url) {
    try {
        var u = new URL(url);
        var ok = u.protocol === 'https:' && !u.port && !u.username && !u.password
            && (u.hostname === 'hkfree.org' || u.hostname.endsWith('.hkfree.org'));
        return ok ? u.href : null;
    } catch (e) {
        return null;
    }
}

self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { /* neplatný payload */ }
    event.waitUntil(self.registration.showNotification(String(data.titulek || 'HKFree'), {
        body: String(data.text || ''),
        icon: 'favicon.ico',
        data: { url: data.url ? povolenaUrl(String(data.url)) : null }
    }));
});

// Stránka pro klik bez odkazu (předává ji push.js při registraci); jen ze stejného originu
function domu() {
    var d = new URL(self.location.href).searchParams.get('domu');
    try {
        var u = new URL(d || '', self.registration.scope);
        return u.origin === new URL(self.registration.scope).origin ? u.href : self.registration.scope;
    } catch (e) {
        return self.registration.scope;
    }
}

// Klik otevře ověřenou URL, bez URL domovskou stránku notifikací
self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = event.notification.data && event.notification.data.url;
    event.waitUntil(clients.openWindow((url && povolenaUrl(url)) || domu()));
});
