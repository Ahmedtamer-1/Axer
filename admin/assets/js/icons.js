/**
 * Icon renderer.
 *
 * Replaces the Lucide CDN script that the app's own Content-Security-Policy
 * blocked (unpkg.com was never in the allowlist), so no icon rendered at
 * all. Markup is unchanged — elements still opt in with data-lucide="name"
 * — but the glyphs now come from a self-hosted sprite.
 *
 * The sprite is fetched once and inlined, because <use href="file.svg#id">
 * cross-document references are inconsistently supported and cannot be
 * styled with currentColor in every browser.
 */
(function () {
    'use strict';

    var SPRITE_URL = '/admin/assets/icons.svg';
    var spritePromise = null;

    function loadSprite() {
        if (spritePromise) {
            return spritePromise;
        }

        // Already inlined by a previous call or by server-side rendering.
        if (document.getElementById('axer-icon-sprite')) {
            spritePromise = Promise.resolve();
            return spritePromise;
        }

        spritePromise = fetch(SPRITE_URL, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('sprite ' + response.status);
                }
                return response.text();
            })
            .then(function (markup) {
                var holder = document.createElement('div');
                holder.id = 'axer-icon-sprite';
                holder.setAttribute('aria-hidden', 'true');
                holder.style.display = 'none';
                holder.innerHTML = markup;
                document.body.insertBefore(holder, document.body.firstChild);
            })
            .catch(function (error) {
                // Missing icons must never break the page.
                if (window.console) {
                    console.warn('Axer: icon sprite unavailable —', error.message);
                }
            });

        return spritePromise;
    }

    function renderInto(element) {
        var name = element.getAttribute('data-lucide');

        if (!name || element.getAttribute('data-icon-rendered') === '1') {
            return;
        }

        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', element.getAttribute('data-stroke-width') || '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.classList.add('axer-icon');

        // Preserve any sizing the markup set inline on the placeholder.
        var inlineWidth = element.style.width;
        var inlineHeight = element.style.height;
        if (inlineWidth) { svg.style.width = inlineWidth; }
        if (inlineHeight) { svg.style.height = inlineHeight; }
        if (element.style.color) { svg.style.color = element.style.color; }

        var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', '#i-' + name);
        svg.appendChild(use);

        element.setAttribute('data-icon-rendered', '1');
        element.replaceWith(svg);
    }

    function createIcons(root) {
        var scope = root || document;

        return loadSprite().then(function () {
            var nodes = scope.querySelectorAll('[data-lucide]:not([data-icon-rendered])');
            Array.prototype.forEach.call(nodes, renderInto);
        });
    }

    // Render icons added later (dynamic table rows, builder blocks, toasts).
    function observe() {
        if (!window.MutationObserver) {
            return;
        }

        var pending = false;

        new MutationObserver(function () {
            if (pending) {
                return;
            }

            pending = true;
            window.requestAnimationFrame(function () {
                pending = false;
                createIcons(document);
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    function init() {
        createIcons(document).then(observe);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Keep the old call site working: templates end with lucide.createIcons().
    window.lucide = window.lucide || {};
    window.lucide.createIcons = createIcons;
})();
