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
    var journeyLimit = Math.max(1, Math.min(50, Number(config.journeyLimit) || 20));
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

    function firstSource(referrer) {
        var params = new URLSearchParams(window.location.search || '');
        var utmSource = clean(params.get('utm_source'), 120);
        if (utmSource) return utmSource;
        if (params.has('gclid') || params.has('wbraid') || params.has('gbraid')) return 'google';
        return sourceFromReferrer(referrer) || 'direct';
    }

    function validState(state) {
        if (!isObject(state) || Number(state.expires_at) <= Date.now() || !Array.isArray(state.journey) || !state.journey.length) {
            return false;
        }
        var first = state.journey[0];
        return isObject(first)
            && typeof first.path === 'string'
            && typeof first.time === 'string'
            && typeof first.source === 'string'
            && typeof first.referrer_url === 'string';
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
            var referrer = externalReferrer();
            state = {
                expires_at: 0,
                journey: [{
                    path: path,
                    time: isoNow(),
                    source: firstSource(referrer),
                    referrer_url: referrer,
                }],
            };
        } else {
            var previous = state.journey[state.journey.length - 1];
            if (!previous || previous.path !== path) {
                state.journey.push({ path: path, time: isoNow() });
            }
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

    function formatLine(item) {
        var parts = [readable(item.time, 40), readable(item.path, MAX_URL_LENGTH)];
        if (item.source) parts.push('source=' + readable(item.source, 120));
        if (item.referrer_url) parts.push('referrer=' + readable(item.referrer_url, MAX_URL_LENGTH));
        if (item.event) parts.push('event=' + readable(item.event, 40));
        if (item.event_id) parts.push('event_id=' + readable(item.event_id, 40));
        return parts.join(' | ');
    }

    function formText() {
        var snapshot = getSnapshot();
        if (!snapshot) return '';
        var eventItem = {
            path: currentPath(),
            time: isoNow(),
            event: 'form_submit',
            event_id: uuid(),
        };
        var journey = snapshot.journey.slice();
        journey.push(eventItem);
        while (journey.length > 2 && byteLength(journey.map(formatLine).join('\n')) > MAX_BYTES) journey.splice(1, 1);
        var text = journey.map(formatLine).join('\n');
        return byteLength(text) <= MAX_BYTES ? text : '';
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
            field.value = formText();
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
                var matches = field.value.match(/(?:^|\n)[^\n]*\| event=form_submit \| event_id=([a-f0-9-]{36})(?:\n|$)/i);
                if (!matches) return;
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    event: 'elementor_generate_lead',
                    form_id: readable(form.id || form.getAttribute('name') || '', 120),
                    lead_event_id: matches[1].toLowerCase(),
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
