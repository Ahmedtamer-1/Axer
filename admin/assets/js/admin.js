/**
 * Admin shell behaviour: responsive navigation, dark mode, toasts and a
 * small fetch helper that always attaches the CSRF token.
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
     * Toasts — replace the blocking alert() calls the admin used for
     * every save and every error.
     * --------------------------------------------------------------- */

    var ICONS = { success: 'check-circle', error: 'alert-triangle', info: 'info' };

    function dismiss(el) {
        if (!el || !el.parentNode) {
            return;
        }

        el.classList.remove('is-visible');
        setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 200);
    }

    function toast(message, type, timeout) {
        var region = document.getElementById('toastRegion');
        if (!region) {
            return null;
        }

        type = type || 'info';

        var el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');

        var icon = document.createElement('i');
        icon.setAttribute('data-lucide', ICONS[type] || ICONS.info);

        var text = document.createElement('span');
        text.className = 'toast-text';
        // textContent, not innerHTML: messages can carry server strings.
        text.textContent = message;

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.innerHTML = '<i data-lucide="x"></i>';
        close.addEventListener('click', function () { dismiss(el); });

        el.appendChild(icon);
        el.appendChild(text);
        el.appendChild(close);
        region.appendChild(el);

        requestAnimationFrame(function () { el.classList.add('is-visible'); });

        var delay = timeout === undefined ? (type === 'error' ? 6000 : 3500) : timeout;
        if (delay > 0) {
            setTimeout(function () { dismiss(el); }, delay);
        }

        return el;
    }

    /* ---------------------------------------------------------------
     * Dark mode
     * --------------------------------------------------------------- */

    function initTheme() {
        var toggle = document.getElementById('themeToggle');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', function () {
            var next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;

            try {
                localStorage.setItem('axer-theme', next);
            } catch (e) { /* private mode — the choice just will not persist */ }
        });
    }

    /* ---------------------------------------------------------------
     * Responsive sidebar. Below 1024px the sidebar is off-canvas; it was
     * previously position:fixed at a hard 260px, permanently covering the
     * content on a phone.
     * --------------------------------------------------------------- */

    function initSidebar() {
        var toggle = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('sidebar');
        var scrim = document.getElementById('sidebarScrim');

        if (!toggle || !sidebar || !scrim) {
            return;
        }

        function setOpen(open) {
            sidebar.classList.toggle('is-open', open);
            scrim.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('nav-locked', open);
        }

        toggle.addEventListener('click', function () {
            setOpen(!sidebar.classList.contains('is-open'));
        });

        scrim.addEventListener('click', function () { setOpen(false); });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        sidebar.addEventListener('click', function (event) {
            if (event.target.closest('a') && window.innerWidth < 1024) {
                setOpen(false);
            }
        });
    }

    /* ---------------------------------------------------------------
     * Fetch helper. Every admin XHR needs the CSRF token; forgetting it
     * produced an opaque 419 that the UI reported as "save failed".
     * --------------------------------------------------------------- */

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function request(url, options) {
        options = options || {};

        var body = options.body;
        var headers = Object.assign({
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }, options.headers || {});

        if (body && typeof body === 'object' && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(Object.assign({ _csrf: csrfToken() }, body));
        }

        return fetch(url, {
            method: options.method || 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: body
        }).then(function (response) {
            var contentType = response.headers.get('content-type') || '';
            var parse = contentType.indexOf('json') !== -1
                ? response.json()
                : response.text().then(function (t) { return { message: t }; });

            return parse.then(function (payload) {
                if (!response.ok) {
                    var message = (payload && payload.message)
                        || (response.status === 419
                            ? 'Your session expired. Please refresh the page.'
                            : 'Request failed (' + response.status + ')');

                    var error = new Error(message);
                    error.status = response.status;
                    error.payload = payload;
                    throw error;
                }

                return payload;
            });
        });
    }

    /* ---------------------------------------------------------------
     * Progressive enhancement for destructive actions and busy buttons.
     * --------------------------------------------------------------- */

    function initConfirmations() {
        document.addEventListener('submit', function (event) {
            var form = event.target;
            var message = form.getAttribute('data-confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
                return;
            }

            // Stop double submits, which produced duplicate products.
            var submit = form.querySelector('[type="submit"]');
            if (submit && !form.hasAttribute('data-no-busy')) {
                setTimeout(function () {
                    submit.disabled = true;
                    submit.classList.add('is-busy');
                }, 0);
            }
        });
    }

    /* Show any server-side flash message as a toast. */
    function initFlash() {
        var node = document.getElementById('axer-flash');
        if (!node) {
            return;
        }

        try {
            var data = JSON.parse(node.textContent);
            if (data.success) { toast(data.success, 'success'); }
            if (data.error) { toast(data.error, 'error'); }
        } catch (e) { /* malformed payload — nothing to show */ }
    }

    function init() {
        initTheme();
        initSidebar();
        initConfirmations();
        initFlash();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.Axer = {
        toast: toast,
        dismissToast: dismiss,
        request: request,
        csrfToken: csrfToken
    };
})();
