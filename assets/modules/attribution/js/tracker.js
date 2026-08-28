(function(window, document) {
    'use strict';

    if (window.WPAIGentAttribution) return;

    var BrowserState = window.WPAIGentBrowserState;
    var config = window.wpAIgentAttributionConfig || {};
    if (!BrowserState) return;

    var MAX_BYTES = 12288;
    var MAX_JOURNEY_BYTES = MAX_BYTES - 64;
    var MAX_URL_LENGTH = 2048;
    var retentionDays = Math.max(1, Math.min(365, Number(config.retentionDays) || 90));
    var journeyLimit = Math.max(2, Math.min(50, Number(config.journeyLimit) || 20));
    var excludedPrefixes = Array.isArray(config.excludedPathPrefixes)
        ? config.excludedPathPrefixes.filter(function(value) { return typeof value === 'string' && value.charAt(0) === '/'; })
        : [];

    function isObject(value) {
        return value && typeof value === 'object' && !Array.isArray(value);
    }

    function clean(value, maxLength) {
        return String(value || '')
            .replace(/[\u0000-\u001f\u007f]/g, '')
            .trim()
            .slice(0, maxLength || 200);
    }

    function readable(value, maxLength) {
        return clean(value, maxLength).replace(/\|/g, '%7C');
    }

    function isoNow() {
        return new Date().toISOString();
    }

    function currentPath() {
        var path = clean((window.location.pathname || '/') + (window.location.search || ''), MAX_URL_LENGTH).split('#')[0];
        return path.charAt(0) === '/' ? path : '/';
    }

    function isExcluded(path) {
        var pathname = path.split('?')[0];
        return excludedPrefixes.some(function(prefix) { return pathname.indexOf(prefix) === 0; });
    }

    function externalReferrer() {
        try {
            if (!document.referrer) return '';
            var url = new URL(document.referrer);
            if (url.hostname === window.location.hostname) return '';
            return clean(url.origin + (url.pathname || '/') + (url.search || ''), MAX_URL_LENGTH);
        } catch (error) {
            return '';
        }
    }

    function sourceFromReferrer(referrer) {
        if (!referrer) return '';
        try {
            var host = new URL(referrer).hostname.toLowerCase().replace(/^www\./, '');
            var engines = [
                { pattern: /(^|\.)google\./, source: 'google' },
                { pattern: /(^|\.)bing\.com$/, source: 'bing' },
                { pattern: /(^|\.)yahoo\./, source: 'yahoo' },
                { pattern: /(^|\.)duckduckgo\.com$/, source: 'duckduckgo' },
                { pattern: /(^|\.)baidu\.com$/, source: 'baidu' },
            ];
            for (var index = 0; index < engines.length; index++) {
                if (engines[index].pattern.test(host)) return engines[index].source;
            }
            return clean(host, 120);
        } catch (error) {
            return '';
        }
    }

    function acquisitionData(isFirst) {
        var params = new URLSearchParams(window.location.search || '');
        var utmSource = clean(params.get('utm_source'), 120);
        var referrer = externalReferrer();
        var source = utmSource;
        if (!source && (params.has('gclid') || params.has('wbraid') || params.has('gbraid'))) source = 'google';
        if (!source && referrer) source = sourceFromReferrer(referrer);
        if (!source && isFirst) source = 'direct';

        var result = {};
        if (source) result.source = source;
        if (referrer) result.referrer_url = referrer;
        return result;
    }

    function pageItem(path, isFirst) {
        return Object.assign({ path: path, time: isoNow() }, acquisitionData(isFirst));
    }

    function samePageItem(previous, current) {
        return isObject(previous)
            && !previous.event
            && previous.path === current.path
            && (previous.source || '') === (current.source || '')
            && (previous.referrer_url || '') === (current.referrer_url || '');
    }

    function validState(state) {
        if (!isObject(state) || Number(state.expires_at) <= Date.now() || !Array.isArray(state.journey) || !state.journey.length) {
            return false;
        }
        return state.journey.every(function(item) {
            return isObject(item) && typeof item.path === 'string' && typeof item.time === 'string';
        });
    }

    function loadState() {
        if (!BrowserState.hasPersistentState()) return null;
        var state = BrowserState.getPreferenceScope('attribution');
        if (!validState(state)) {
            if (state !== null) BrowserState.removePreferenceScope('attribution');
            return null;
        }
        return state;
    }

    function byteLength(value) {
        var json = typeof value === 'string' ? value : JSON.stringify(value);
        if (window.TextEncoder) return new TextEncoder().encode(json).length;
        return json.length * 2;
    }

    function trimJourney(journey, limit) {
        if (journey.length > limit) {
            journey = limit === 1
                ? [journey[0]]
                : [journey[0]].concat(journey.slice(-(limit - 1)));
        } else {
            journey = journey.slice();
        }
        while (journey.length > 1 && byteLength({ journey: journey }) > MAX_JOURNEY_BYTES) {
            journey.splice(1, 1);
        }
        return byteLength({ journey: journey }) <= MAX_JOURNEY_BYTES ? journey : [];
    }

    function updateState() {
        var path = currentPath();
        if (isExcluded(path)) return;

        var state = loadState();
        if (!state) {
            state = {
                expires_at: 0,
                journey: [pageItem(path, true)],
            };
        } else {
            var current = pageItem(path, false);
            var previous = state.journey[state.journey.length - 1];
            if (!samePageItem(previous, current)) state.journey.push(current);
        }

        state.journey = trimJourney(state.journey, journeyLimit);
        if (!state.journey.length) {
            BrowserState.removePreferenceScope('attribution');
            return;
        }
        state.expires_at = Date.now() + retentionDays * 24 * 60 * 60 * 1000;
        BrowserState.setPreferenceScope('attribution', state);
    }

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') return window.crypto.randomUUID();
        if (!window.crypto || typeof window.crypto.getRandomValues !== 'function') return '';
        var bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        var hex = Array.prototype.map.call(bytes, function(byte) { return byte.toString(16).padStart(2, '0'); }).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }

    function getSnapshot() {
        try {
            var path = currentPath();
            if (isExcluded(path)) return null;
            var state = loadState();
            if (!state) return null;
            var journey = trimJourney(state.journey, journeyLimit);
            return journey.length ? { journey: journey } : null;
        } catch (error) {
            return null;
        }
    }

    function formJson() {
        var state = loadState();
        if (!state) return '';
        var eventId = uuid();
        if (!eventId) return '';
        var eventItem = {
            path: currentPath(),
            time: isoNow(),
            event: 'form_submit',
            event_id: eventId,
        };
        state.journey.push(eventItem);
        state.journey = trimJourney(state.journey, journeyLimit);
        state.expires_at = Date.now() + retentionDays * 24 * 60 * 60 * 1000;
        if (!state.journey.length || state.journey[state.journey.length - 1].event_id !== eventId) return '';
        BrowserState.setPreferenceScope('attribution', state);

        var persisted = loadState();
        if (!persisted || !persisted.journey.length || persisted.journey[persisted.journey.length - 1].event_id !== eventId) return '';
        var json = JSON.stringify({ journey: persisted.journey });
        return byteLength(json) <= MAX_BYTES ? json : '';
    }

    try {
        updateState();
    } catch (error) {
        console.warn('WP AIgent attribution state was not updated.', error);
    }

    document.addEventListener('submit', function(event) {
        var field = null;
        try {
            var form = event.target;
            if (!form || String(form.tagName).toLowerCase() !== 'form') return;
            field = form.querySelector('input[type="hidden"][name="form_fields[wp_aigent_attribution]"]');
            if (!field) return;
            field.value = formJson();
        } catch (error) {
            if (field) field.value = '';
            console.warn('WP AIgent attribution was omitted from this form.', error);
        }
    }, true);

    if (config.dataLayerEnabled === true && window.jQuery) {
        window.jQuery(document).on('submit_success.wpAIgentAttribution', function(event) {
            try {
                var form = window.jQuery(event.target).closest('form')[0] || null;
                if (!form) return;
                var field = form.querySelector('input[type="hidden"][name="form_fields[wp_aigent_attribution]"]');
                if (!field || !field.value) return;
                var snapshot = JSON.parse(field.value);
                var journey = snapshot && Array.isArray(snapshot.journey) ? snapshot.journey : [];
                var submitted = null;
                for (var index = journey.length - 1; index >= 0; index--) {
                    if (journey[index] && journey[index].event === 'form_submit' && journey[index].event_id) {
                        submitted = journey[index];
                        break;
                    }
                }
                if (!submitted || !/^[a-f0-9-]{36}$/i.test(submitted.event_id)) return;
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    event: 'elementor_generate_lead',
                    form_id: readable(form.id || form.getAttribute('name') || '', 120),
                    lead_event_id: submitted.event_id.toLowerCase(),
                    page_path: currentPath(),
                });
                field.value = '';
            } catch (error) {
                console.warn('WP AIgent dataLayer event was omitted.', error);
            }
        });
    }

    window.WPAIGentAttribution = Object.freeze({
        getSnapshot: getSnapshot,
        reset: function() { BrowserState.removePreferenceScope('attribution'); },
    });
})(window, document);
