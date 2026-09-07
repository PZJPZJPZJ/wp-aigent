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

    function getCountryCodeSelects(scope) {
        var root = scope && scope.querySelectorAll ? scope : document;
        var selector = 'select[data-wp-aigent-country-code="1"]';
        var selects = [];

        if (root.matches && root.matches(selector)) {
            selects.push(root);
        }

        root.querySelectorAll(selector).forEach(function (select) {
            selects.push(select);
        });

        return selects;
    }

    function isElementorEditMode() {
        return typeof elementorFrontend !== 'undefined'
            && typeof elementorFrontend.isEditMode === 'function'
            && elementorFrontend.isEditMode();
    }

    /**
     * Find all country-code selects and update them for the current render.
     */
    function updateCountryCodes(scope) {
        var selects = getCountryCodeSelects(scope);
        if (selects.length === 0) {
            return;
        }

        applyDefaultCountries(selects);

        var cloudflareSelects = selects.filter(function (select) {
            return select.getAttribute('data-use-cf-ipcountry') === '1';
        });
        if (cloudflareSelects.length === 0 || isElementorEditMode()) {
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
                var match = text.match(/^loc=([A-Z0-9]{2})$/m);
                if (!match) {
                    return;
                }
                if (match[1] === 'XX' || match[1] === 'T1') {
                    return;
                }
                selectCountry(cloudflareSelects, match[1]);
            })
            .catch(function () {
                // Silently fail — not behind Cloudflare or request blocked.
                // The select keeps the configured Default Country.
            });
    }

    /**
     * In Elementor editor, show the configured default immediately whenever
     * the Form widget re-renders. On the public page this is also the fallback
     * before CF detection finishes.
     *
     * @param {HTMLElement[]} selects
     */
    function applyDefaultCountries(selects) {
        for (var i = 0; i < selects.length; i++) {
            if (!selects[i]) {
                continue;
            }
            var defaultCountry = selects[i].getAttribute('data-default-country');
            if (defaultCountry) {
                selectCountry([selects[i]], defaultCountry);
            }
        }
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
        document.addEventListener('DOMContentLoaded', function () {
            updateCountryCodes(document);
        });
    } else {
        updateCountryCodes(document);
    }

    function bindElementorHooks() {
        if (typeof elementorFrontend === 'undefined' || !elementorFrontend.hooks) {
            return;
        }

        if (window.wpAigentCountryCodeElementorHookBound) {
            return;
        }

        elementorFrontend.hooks.addAction('frontend/element_ready/form.default', function ($scope) {
            var scopeEl = $scope && $scope[0] ? $scope[0] : document;
            updateCountryCodes(scopeEl);
        });
        window.wpAigentCountryCodeElementorHookBound = true;
    }

    if (window.jQuery) {
        jQuery(window).on('elementor/frontend/init', bindElementorHooks);
    }
    bindElementorHooks();
})();
