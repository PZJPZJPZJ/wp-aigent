/**
 * Country Code auto-detection for Elementor forms.
 *
 * When Cloudflare full-page caching is active, the server-rendered country
 * select always shows the configured default. This script reads the real
 * visitor country from Cloudflare's built-in /cdn-cgi/trace endpoint
 * (always available at the edge, no CORS, no PHP involved) and updates
 * the select accordingly.
 */
(function () {
    'use strict';

    /**
     * Find all country-code selects with CF detection enabled and update them.
     */
    function updateCountryCodes() {
        var selects = document.querySelectorAll(
            'select[data-wp-aigent-country-code="1"][data-use-cf-ipcountry="1"]'
        );
        if (selects.length === 0) {
            return;
        }

        fetch('/cdn-cgi/trace', { cache: 'no-store' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (text) {
                var match = text.match(/^loc=([A-Z]{2})$/m);
                if (!match) {
                    return;
                }
                selectCountry(selects, match[1]);
            })
            .catch(function () {
                // Silently fail — not behind Cloudflare or request blocked.
                // The select keeps the configured Default Country.
            });
    }

    /**
     * Select the matching <option> for each country-code select.
     *
     * @param {HTMLElement[]} selects
     * @param {string}        countryCode  ISO 3166-1 alpha-2 code
     */
    function selectCountry(selects, countryCode) {
        for (var i = 0; i < selects.length; i++) {
            if (!selects[i]) {
                continue;
            }
            var option = selects[i].querySelector(
                'option[data-country="' + countryCode + '"]'
            );
            if (option) {
                selects[i].value = option.value;
            }
        }
    }

    // Run after the DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateCountryCodes);
    } else {
        updateCountryCodes();
    }
})();
