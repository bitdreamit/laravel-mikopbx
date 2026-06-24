/**
 * mikopbx/echo-listeners.js
 * Wires up Laravel Echo (Reverb/Pusher) to Livewire events and browser notifications.
 * Import this in your app.js after Echo is configured.
 */

export function initMikoPBXEcho(Echo) {
    if (!Echo) return;

    // ── Incoming call channel ──────────────────────────────────────────
    Echo.channel('mikopbx.calls')
        .listen('.incoming', (data) => {
            // Trigger Livewire IncomingCallPopup
            Livewire.dispatch('incoming-call', data);

            // Browser notification
            if (Notification.permission === 'granted') {
                new Notification('📞 Incoming Call', {
                    body: `${data.caller} → ext ${data.extension}`,
                    icon: '/vendor/mikopbx/icon.png',
                    tag:  'incoming-call',
                    requireInteraction: true,
                });
            }

            // Ring tone via custom event
            window.dispatchEvent(new CustomEvent('mikopbx:ring', { detail: data }));
        })
        .listen('.answered', (data) => {
            Livewire.dispatch('call-answered', data);
            window.dispatchEvent(new CustomEvent('mikopbx:stop-ring'));
        })
        .listen('.ended', (data) => {
            Livewire.dispatch('call-ended', data);
            window.dispatchEvent(new CustomEvent('mikopbx:call-ended', { detail: data }));
        });

    // ── Agent status channel ───────────────────────────────────────────
    Echo.channel('mikopbx.agents')
        .listen('.status', (data) => {
            Livewire.dispatch('agent-status-changed', data);
            window.dispatchEvent(new CustomEvent('mikopbx:agent-status', { detail: data }));
        });

    // Request notification permission on first load
    if (Notification.permission === 'default') {
        Notification.requestPermission();
    }
}
