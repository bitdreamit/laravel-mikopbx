/**
 * bitdreamit/laravel-mikopbx — JS entry point
 *
 * In your Laravel app's resources/js/app.js, add:
 *   import './mikopbx/app';
 *
 * Or in vite.config.js add this file as an input.
 */

import { initMikoPBXEcho } from './echo-listeners.js';
import { autoWireCallLinks } from './click-to-call.js';

// ── Ring tone management ─────────────────────────────────────────────────
let ringtoneAudio = null;

function playRingtone() {
    if (!ringtoneAudio) {
        ringtoneAudio = new Audio('/vendor/mikopbx/ringtone.mp3');
        ringtoneAudio.loop = true;
    }
    ringtoneAudio.play().catch(() => {});
}

function stopRingtone() {
    if (ringtoneAudio) {
        ringtoneAudio.pause();
        ringtoneAudio.currentTime = 0;
    }
}

window.addEventListener('mikopbx:ring',     () => playRingtone());
window.addEventListener('mikopbx:stop-ring',() => stopRingtone());

// Livewire events
document.addEventListener('livewire:initialized', () => {
    Livewire.on('play-ringtone',  playRingtone);
    Livewire.on('stop-ringtone',  stopRingtone);

    // Dial event from sidebar quick-dial or click-to-call
    window.addEventListener('mikopbx:dial', (e) => {
        const number = e.detail;
        if (!number) return;
        // Dispatch to Alpine global state (mikopbxApp)
        document.dispatchEvent(new CustomEvent('alpine:dial', { detail: number }));
    });
});

// ── Echo / Reverb ────────────────────────────────────────────────────────
// Initialise after window.Echo is defined (set up by your own bootstrap.js)
document.addEventListener('DOMContentLoaded', () => {
    if (window.Echo) {
        initMikoPBXEcho(window.Echo);
    } else {
        // If Echo loads asynchronously
        window.addEventListener('echo:ready', () => initMikoPBXEcho(window.Echo));
    }

    autoWireCallLinks();
});

// ── Global helper — call any number from plain JS ────────────────────────
window.mikopbxDial = (number) => {
    window.dispatchEvent(new CustomEvent('mikopbx:dial', { detail: number }));
};
