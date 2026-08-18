/**
 * PATCH → POST + `_method=PATCH` rewrite for same-origin fetches.
 *
 * The o2switch front (LiteSpeed) kills PATCH requests with an HTTP/2
 * PROTOCOL_ERROR before they reach the application, which breaks every
 * EasyAdmin index toggle ("network error" toast, no request ever sent).
 * Symfony's `framework.http_method_override` reads the `_method` body field
 * of a POST and restores the intended method server-side, so behaviour is
 * identical — the forbidden verb just never crosses the wire.
 */
(function () {
    'use strict';

    const originalFetch = window.fetch;

    window.fetch = function (input, init) {
        const method = (init && init.method ? init.method : (input instanceof Request ? input.method : 'GET')).toUpperCase();

        if (method !== 'PATCH') {
            return originalFetch.call(this, input, init);
        }

        const url = input instanceof Request ? input.url : String(input);
        if (new URL(url, window.location.origin).origin !== window.location.origin) {
            return originalFetch.call(this, input, init);
        }

        const options = Object.assign({}, init, {
            method: 'POST',
            headers: Object.assign({}, (init && init.headers) || {}, {
                'Content-Type': 'application/x-www-form-urlencoded',
            }),
            body: '_method=PATCH',
        });

        return originalFetch.call(this, url, options);
    };
})();
