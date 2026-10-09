// Zapnutí push notifikací; dotaz na povolení až po kliknutí (R13)
(function () {
    var el = document.getElementById('push-odber');
    if (!el) return;
    var stav = el.querySelector('.push-stav');
    var tlacitko = el.querySelector('.push-zapnout');
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !el.dataset.vapid) {
        stav.textContent = 'Push notifikace nejsou v tomto prohlížeči dostupné.';
        return;
    }
    var b64 = el.dataset.vapid.replace(/-/g, '+').replace(/_/g, '/');
    b64 += '='.repeat((4 - b64.length % 4) % 4);
    var klic = Uint8Array.from(atob(b64), function (c) { return c.charCodeAt(0); });

    tlacitko.hidden = false;
    tlacitko.addEventListener('click', async function () {
        try {
            if (await Notification.requestPermission() !== 'granted') {
                stav.textContent = 'Notifikace jsou v prohlížeči zakázané.';
                return;
            }
            // `domu` = stránka, kterou otevře klik na notifikaci bez odkazu
            var reg = await navigator.serviceWorker.register(el.dataset.sw + '?domu=' + encodeURIComponent(el.dataset.domu));
            await navigator.serviceWorker.ready;
            // Stará subscription s jiným VAPID klíčem (po výměně klíčů) by subscribe() odmítl
            var stara = await reg.pushManager.getSubscription();
            if (stara && String(new Uint8Array(stara.options.applicationServerKey || [])) !== String(klic)) {
                await stara.unsubscribe();
            }
            var sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: klic });
            var odpoved = await fetch(el.dataset.ulozit, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(sub)
            });
            if (!odpoved.ok) throw new Error('HTTP ' + odpoved.status);
            location.reload();
        } catch (e) {
            stav.textContent = 'Zapnutí se nepodařilo: ' + e.message;
        }
    });
})();
