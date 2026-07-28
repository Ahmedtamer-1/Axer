/**
 * Storefront behaviour.
 *
 * Adding to cart used to be a plain form POST that reloaded the whole page
 * and dumped the shopper on /cart, losing their place in the grid. This
 * intercepts the submit, posts in the background, and updates the header
 * badge in place — with a full page navigation as the fallback whenever
 * JavaScript or the network is unavailable.
 */
(function () {
    'use strict';

    function csrfToken(form) {
        var field = form && form.querySelector('input[name="_csrf"]');
        if (field) {
            return field.value;
        }

        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /* ---------------------------------------------------------------
     * Toasts
     * --------------------------------------------------------------- */

    function toast(message, type) {
        var region = document.getElementById('toastRegion');
        if (!region) {
            return;
        }

        var el = document.createElement('div');
        el.className = 'toast toast-' + (type || 'info');
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');

        var text = document.createElement('span');
        text.textContent = message;
        el.appendChild(text);

        region.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('is-visible'); });

        setTimeout(function () {
            el.classList.remove('is-visible');
            setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 200);
        }, type === 'error' ? 5000 : 3000);
    }

    /* ---------------------------------------------------------------
     * Cart badge
     * --------------------------------------------------------------- */

    function setCartCount(count) {
        var badges = document.querySelectorAll('[data-cart-count]');

        Array.prototype.forEach.call(badges, function (badge) {
            badge.textContent = count;
            badge.hidden = !count;
        });
    }

    /* ---------------------------------------------------------------
     * Add to cart
     * --------------------------------------------------------------- */

    function initAddToCart() {
        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!form.matches('form[action="/cart/add"]')) {
                return;
            }

            event.preventDefault();

            var button = form.querySelector('[type="submit"]');
            var originalLabel = button ? button.innerHTML : '';

            if (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            }

            var payload = new FormData(form);
            payload.set('_csrf', csrfToken(form));

            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(form)
                },
                body: payload
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok || data.success === false) {
                            throw new Error(data.message || 'Could not add this item to your cart.');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    if (typeof data.cart_count === 'number') {
                        setCartCount(data.cart_count);
                    }

                    toast(data.message || 'Added to your cart.', 'success');

                    if (button) {
                        button.innerHTML = '<span>Added</span>';
                        setTimeout(function () { button.innerHTML = originalLabel; }, 1600);
                    }
                })
                .catch(function (error) {
                    toast(error.message, 'error');

                    if (button) {
                        button.innerHTML = originalLabel;
                    }
                })
                .finally(function () {
                    if (button) {
                        button.disabled = false;
                        button.removeAttribute('aria-busy');
                    }
                });
        });
    }

    /* ---------------------------------------------------------------
     * Cart quantity steppers submit their form automatically.
     * --------------------------------------------------------------- */

    function initQuantityInputs() {
        document.addEventListener('change', function (event) {
            var input = event.target;

            if (input.matches('input[data-auto-submit]')) {
                var form = input.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    }

    function init() {
        initAddToCart();
        initQuantityInputs();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.AxerStore = { toast: toast, setCartCount: setCartCount };
})();
