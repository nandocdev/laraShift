/**
 * Clave Payment Adapter
 *
 * Replaces the obfuscated PagueLo Fácil SDK.
 * Responsibilities:
 *   1. Render the checkout iframe.
 *   2. Listen for postMessage events from the gateway iframe.
 *   3. Relay results back to Livewire.
 *
 * The server (ClaveGateway.php) builds the checkout URL. This file is
 * intentionally thin — no API calls, no session state, no promise chains.
 */

const ClaveAdapter = (() => {
    'use strict';

    // Allowed origins for postMessage validation.
    // Must match ClaveEnvironment::checkoutBaseUrl() values.
    const ALLOWED_ORIGINS = [
        'https://checkout.paguelofacil.com',
        'https://sandbox.paguelofacil.com',
        'https://checkout-demo.paguelofacil.com',
    ];

    let _iframe  = null;
    let _options = {};

    /**
     * Mount the checkout iframe inside the given container element.
     *
     * @param {Object} options
     * @param {string}   options.checkoutUrl   - Server-generated URL (from CheckoutManager)
     * @param {string}   options.containerId   - DOM element ID to mount the iframe into
     * @param {Function} [options.onOpen]      - Called when iframe loads
     * @param {Function} [options.onTxSuccess] - Called with result on approval
     * @param {Function} [options.onTxError]   - Called with result on decline/error
     * @param {Function} [options.onClose]     - Called when payment is closed
     */
    function mount(options) {
        if (!options.checkoutUrl) {
            console.error('[ClaveAdapter] checkoutUrl is required');
            return;
        }

        const container = document.getElementById(options.containerId);
        if (!container) {
            console.error('[ClaveAdapter] container not found:', options.containerId);
            return;
        }

        _options = options;

        _iframe = document.createElement('iframe');
        _iframe.src = options.checkoutUrl;
        _iframe.style.cssText = [
            'width: 100%;',
            'height: 500px;',
            'border: none;',
            'background: transparent;',
            'transition: height 0.3s ease;',
        ].join(' ');
        _iframe.setAttribute('allowtransparency', 'true');
        _iframe.setAttribute('scrolling', 'no');
        _iframe.setAttribute('title', 'Clave Payment');

        _iframe.addEventListener('load', () => {
            if (typeof _options.onOpen === 'function') {
                _options.onOpen();
            }
        });

        container.appendChild(_iframe);
        window.addEventListener('message', _onMessage, false);
    }

    /**
     * Handle postMessage from the gateway iframe.
     * The JS SDK used 'framebus' under the hood — we validate origin and
     * relay to Livewire directly.
     */
    function _onMessage(event) {
        if (!ALLOWED_ORIGINS.includes(event.origin)) {
            return; // Silently discard cross-origin noise
        }

        const data = event.data;
        if (!data || typeof data !== 'object') return;

        const status = data.status ?? data.txStatus ?? null;

        if (status === 'approved' || status === 'success') {
            if (typeof _options.onTxSuccess === 'function') {
                _options.onTxSuccess(data);
            }
            // Relay to Livewire component
            Livewire.dispatch('payment-result', { status: 'approved', displayId: data.displayId ?? '' });
        } else if (status === 'declined' || status === 'error' || status === 'failed') {
            if (typeof _options.onTxError === 'function') {
                _options.onTxError(data);
            }
            Livewire.dispatch('payment-result', { status: 'declined', displayId: data.displayId ?? '' });
        } else if (status === 'closed' || status === 'cancel') {
            if (typeof _options.onClose === 'function') {
                _options.onClose();
            }
        }
    }

    /**
     * Tear down the iframe and remove listeners.
     * Call this when the Livewire component is destroyed.
     */
    function destroy() {
        window.removeEventListener('message', _onMessage, false);
        if (_iframe && _iframe.parentNode) {
            _iframe.parentNode.removeChild(_iframe);
        }
        _iframe  = null;
        _options = {};
    }

    return { mount, destroy };
})();

export default ClaveAdapter;
