/**
 * Laptop Configurator — RAM / Storage dropdowns on product cards.
 *
 * Recomputes the card price as the user picks upgrades and stamps the choice
 * onto the add-to-cart button. WooCommerce's add-to-cart.js copies the button's
 * dataset into the AJAX request, so the selection reaches the server as
 * shopys_ram / shopys_storage.
 */
(function () {
    'use strict';

    var P = window.shopysLcParams || {};

    function formatPrice(amount) {
        var decimals = parseInt(P.decimals, 10);
        if (isNaN(decimals)) decimals = 2;

        var fixed = Math.abs(amount).toFixed(decimals);
        var parts = fixed.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, P.thousandSep || ',');
        var num = parts.join(parts.length > 1 ? (P.decimalSep || '.') : '');

        // WooCommerce format strings: %1$s = symbol, %2$s = amount.
        // Function replacements avoid `$` in the symbol being read as a
        // back-reference by String.replace.
        var sym = P.symbol || '$';
        var out = (P.format || '%1$s%2$s')
            .replace('%1$s', function () { return sym; })
            .replace('%2$s', function () { return num; });

        return (amount < 0 ? '-' : '') + out;
    }

    function selectedPrice(select) {
        var opt = select.options[select.selectedIndex];
        return opt ? (parseFloat(opt.getAttribute('data-price')) || 0) : 0;
    }

    function update(box) {
        var base = parseFloat(box.getAttribute('data-base')) || 0;
        var selects = box.querySelectorAll('.ppg-lc-select');
        var extra = 0;
        var chosen = {};

        Array.prototype.forEach.call(selects, function (sel) {
            if (!sel.value) return;
            extra += selectedPrice(sel);
            chosen[sel.getAttribute('data-part')] = sel.value;
        });

        var isCustom = Object.keys(chosen).length > 0;

        var total = box.querySelector('.ppg-lc-total');
        if (total) total.textContent = formatPrice(base + extra);

        var badge = box.querySelector('.ppg-lc-badge');
        if (badge) {
            badge.textContent = isCustom ? (P.iCustom || 'Custom') : (P.iStandard || 'Standard');
            badge.classList.toggle('ppg-lc-badge--custom', isCustom);
            badge.classList.toggle('ppg-lc-badge--standard', !isCustom);
        }
        box.classList.toggle('ppg-lc--custom', isCustom);

        stampButton(box, chosen);
    }

    /**
     * Write the selection onto the card's add-to-cart button, both as data
     * attributes (read by the AJAX handler) and onto the href (no-JS/no-AJAX
     * fallback path).
     */
    function stampButton(box, chosen) {
        var card = box.closest('.ppg-card, .ppg-lt-row');
        if (!card) return;
        var btn = card.querySelector('a.add_to_cart_button, a.ajax_add_to_cart, a.button[href*="add-to-cart"]');
        if (!btn) return;

        ['ram', 'storage'].forEach(function (part) {
            var attr = 'data-shopys_' + part;
            if (chosen[part]) {
                btn.setAttribute(attr, chosen[part]);
            } else {
                btn.removeAttribute(attr);
            }
            // jQuery caches .data() on first read; keep its store in sync too.
            if (window.jQuery) {
                if (chosen[part]) {
                    window.jQuery(btn).data('shopys_' + part, chosen[part]);
                } else {
                    window.jQuery(btn).removeData('shopys_' + part);
                }
            }
        });

        // Non-AJAX fallback: keep the href's query string in sync.
        var href = btn.getAttribute('href') || '';
        if (href.indexOf('add-to-cart') !== -1) {
            var url = new URL(href, window.location.href);
            ['ram', 'storage'].forEach(function (part) {
                if (chosen[part]) {
                    url.searchParams.set('shopys_' + part, chosen[part]);
                } else {
                    url.searchParams.delete('shopys_' + part);
                }
            });
            btn.setAttribute('href', url.pathname + url.search + url.hash);
        }
    }

    // Collapse / expand the dropdowns. The badge and price live outside the
    // collapsible body, so they stay readable in either state.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.ppg-lc-toggle') : null;
        if (!btn) return;
        e.preventDefault();
        var box = btn.closest('.ppg-lc');
        if (!box) return;
        var collapsed = box.classList.toggle('ppg-lc--collapsed');
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    });

    // Delegated so cards added by AJAX pagination / infinite scroll work too.
    document.addEventListener('change', function (e) {
        var sel = e.target.closest ? e.target.closest('.ppg-lc-select') : null;
        if (!sel) return;
        var box = sel.closest('.ppg-lc');
        if (box) update(box);
    });
})();
