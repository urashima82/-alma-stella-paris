/**
 * Admin Toast — système de notifications toast pour EasyAdmin.
 *
 * API globale : window.AdminToast.show(type, message)
 * Intercepte automatiquement les requêtes AJAX d'EasyAdmin
 * (toggles boolean, suppressions, etc.) pour afficher un feedback.
 * Flush les flash messages de la session pour éviter les doublons au rechargement.
 */
(function () {
    'use strict';

    var ICONS = {
        success: 'fa-circle-check',
        danger: 'fa-circle-xmark',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    var DURATION = 5000;
    var FLUSH_URL = '/admin/flash-messages';
    var originalFetch;

    function getContainer() {
        var container = document.getElementById('flash-messages');
        if (!container) {
            container = document.createElement('div');
            container.id = 'flash-messages';
            container.className = 'admin-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function dismissToast(toast) {
        if (toast.dataset.dismissing) return;
        toast.dataset.dismissing = '1';
        toast.classList.add('admin-toast--hiding');
        toast.addEventListener('animationend', function () {
            toast.remove();
            var container = document.getElementById('flash-messages');
            if (container && container.children.length === 0) {
                container.remove();
            }
        });
    }

    function show(type, message) {
        var container = getContainer();

        var toast = document.createElement('div');
        toast.className = 'admin-toast admin-toast--' + type;
        toast.setAttribute('role', 'alert');
        toast.innerHTML =
            '<div class="admin-toast__icon"><i class="fa ' + (ICONS[type] || ICONS.info) + '"></i></div>' +
            '<div class="admin-toast__body">' + message + '</div>' +
            '<button type="button" class="admin-toast__close" aria-label="Fermer">&times;</button>' +
            '<div class="admin-toast__progress"></div>';

        container.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('admin-toast--visible');
        });

        toast.querySelector('.admin-toast__close').addEventListener('click', function () {
            dismissToast(toast);
        });

        setTimeout(function () {
            dismissToast(toast);
        }, DURATION);
    }

    /**
     * Consomme les flash messages de la session via l'endpoint dédié
     * et les affiche en tant que toasts.
     */
    function flushAndShowFlashes() {
        originalFetch(FLUSH_URL).then(function (response) {
            if (!response.ok) return;
            return response.json();
        }).then(function (flashes) {
            if (!flashes) return;
            Object.keys(flashes).forEach(function (type) {
                flashes[type].forEach(function (message) {
                    show(type, message);
                });
            });
        }).catch(function () {
            // Silencieux — on ne veut pas de toast pour le flush lui-même
        });
    }

    /**
     * Initialise les toasts déjà présents dans le DOM (flash messages Symfony).
     */
    function initExistingToasts() {
        document.querySelectorAll('.admin-toast').forEach(function (toast, index) {
            setTimeout(function () {
                toast.classList.add('admin-toast--visible');
            }, index * 100);

            toast.querySelector('.admin-toast__close').addEventListener('click', function () {
                dismissToast(toast);
            });

            setTimeout(function () {
                dismissToast(toast);
            }, DURATION + (index * 100));
        });
    }

    function isAdminMutation(url, method) {
        return url.indexOf('/admin') !== -1
            && url.indexOf(FLUSH_URL) === -1
            && method !== 'GET';
    }

    /**
     * Intercepte fetch() pour détecter les opérations AJAX d'EasyAdmin.
     */
    function interceptFetch() {
        window.fetch = function (input, init) {
            return originalFetch.apply(this, arguments).then(function (response) {
                var url = (typeof input === 'string') ? input : (input.url || '');
                var method = (init && init.method) ? init.method.toUpperCase() : 'GET';

                if (!isAdminMutation(url, method)) {
                    return response;
                }

                if (response.ok) {
                    flushAndShowFlashes();
                } else {
                    show('danger', 'Une erreur est survenue.');
                }

                return response;
            }).catch(function (error) {
                show('danger', 'Erreur réseau.');
                throw error;
            });
        };
    }

    /**
     * Intercepte XMLHttpRequest pour les requêtes AJAX legacy d'EasyAdmin.
     */
    function interceptXHR() {
        var originalOpen = XMLHttpRequest.prototype.open;
        var originalSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            this._adminMethod = method;
            this._adminUrl = url;
            return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function () {
            var xhr = this;
            var method = (xhr._adminMethod || '').toUpperCase();
            var url = xhr._adminUrl || '';

            if (isAdminMutation(url, method)) {
                xhr.addEventListener('load', function () {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        flushAndShowFlashes();
                    } else if (xhr.status >= 400) {
                        show('danger', 'Une erreur est survenue.');
                    }
                });
                xhr.addEventListener('error', function () {
                    show('danger', 'Erreur réseau.');
                });
            }

            return originalSend.apply(this, arguments);
        };
    }

    // Expose l'API globale
    window.AdminToast = { show: show };

    // Initialisation au chargement du DOM
    document.addEventListener('DOMContentLoaded', function () {
        originalFetch = window.fetch.bind(window);
        initExistingToasts();
        interceptFetch();
        interceptXHR();
    });
})();
