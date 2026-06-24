/**
 * mikopbx/click-to-call.js
 * Alpine.js component — attach to any element to trigger an outbound call.
 *
 * Usage:
 *   <button x-data="clickToCall('01711000000')" @click="call()">Call</button>
 *   <span x-data="clickToCall()" data-number="01711000000" @click="callNumber($el.dataset.number)">Call</span>
 */

export function clickToCall(defaultNumber = '') {
    return {
        number: defaultNumber,
        loading: false,

        async call(to = null) {
            const target = to || this.number;
            if (!target) return;

            // If web dialer (SIP.js) is active, use it
            window.dispatchEvent(new CustomEvent('mikopbx:dial', { detail: target }));
        },

        async callViaApi(from, to) {
            if (this.loading) return;
            this.loading = true;
            try {
                const res = await fetch('/api/pbx/originate', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ from, to }),
                });
                const data = await res.json();
                if (data.success) {
                    window.dispatchEvent(new CustomEvent('mikopbx:toast', {
                        detail: { type: 'success', msg: `Calling ${to}...` }
                    }));
                } else {
                    throw new Error(data.message || 'Call failed');
                }
            } catch (err) {
                window.dispatchEvent(new CustomEvent('mikopbx:toast', {
                    detail: { type: 'error', msg: err.message }
                }));
            } finally {
                this.loading = false;
            }
        },

        callNumber(number) {
            this.number = number;
            this.call();
        }
    };
}

/**
 * Auto-wire all [data-pbx-call] elements on page load.
 * <a href="tel:01711000000" data-pbx-call>Call</a>
 */
export function autoWireCallLinks() {
    document.querySelectorAll('[data-pbx-call]').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const number = el.dataset.pbxCall || el.getAttribute('href')?.replace('tel:', '') || el.textContent.trim();
            if (number) {
                window.dispatchEvent(new CustomEvent('mikopbx:dial', { detail: number }));
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', autoWireCallLinks);
