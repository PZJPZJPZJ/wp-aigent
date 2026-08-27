(function(window, document) {
    'use strict';

    if (window.WPAIGentAttribution) return;

    var BrowserState = window.WPAIGentBrowserState;
    var config = window.wpAIgentAttributionConfig || {};
    if (!BrowserState) return;

    var SCHEMA_VERSION = 1;
    var MAX_BYTES = 12288;
    var retentionDays = Math.max(1, Math.min(365, Number(config.retentionDays) || 90));
    var journeyLimit = Math.max(0, Math.min(50, Number(config.journeyLimit) || 0));
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

    function isoNow() {
        return new Date().toISOString();
    }

    function currentPath() {
        var path = clean(window.location.pathname || '/', 500);
        return path.charAt(0) === '/' ? path : '/';
    }

    function isExcluded(path) {
        return excludedPrefixes.some(function(prefix) { return path.indexOf(prefix) === 0; });
    }

    function safeExternalReferrer() {
        try {
            if (!document.referrer) return '';
            var url = new URL(document.referrer);
            if (url.hostname === window.location.hostname) return '';
            return clean(url.origin + (url.pathname || '/'), 500);
        } catch (error) {
            return '';
        }
    }

    function parameter(params, name, maxLength) {
        return params.has(name) ? clean(params.get(name), maxLength || 200) : '';
    }

    function sourceFromReferrer(referrer) {
        if (!referrer) return null;
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
                if (engines[index].pattern.test(host)) {
                    return { source: engines[index].source, medium: 'organic' };
                }
            }
            return { source: clean(host, 120), medium: 'referral' };
        } catch (error) {
            return null;
        }
    }

    function detectTouch() {
        var params = new URLSearchParams(window.location.search);
        var referrer = safeExternalReferrer();
        var referrerSource = sourceFromReferrer(referrer);
        var source = parameter(params, 'utm_source', 120);
        var medium = parameter(params, 'utm_medium', 120);
        var gclid = parameter(params, 'gclid', 200);
        var wbraid = parameter(params, 'wbraid', 200);
        var gbraid = parameter(params, 'gbraid', 200);
        var hasSignal = Boolean(source
            || medium
            || parameter(params, 'utm_campaign', 200)
            || parameter(params, 'utm_term', 200)
            || parameter(params, 'utm_content', 200)
            || gclid
            || wbraid
            || gbraid
            || referrerSource
        );
        if (!hasSignal) return null;

        if (!source && (gclid || wbraid || gbraid)) source = 'google';
        if (!medium && (gclid || wbraid || gbraid)) medium = 'cpc';
        if (!source && referrerSource) source = referrerSource.source;
        if (!medium && referrerSource) medium = referrerSource.medium;

        return {
            source: source || 'unknown',
            medium: medium || 'unknown',
            campaign: parameter(params, 'utm_campaign', 200),
            term: parameter(params, 'utm_term', 200),
            content: parameter(params, 'utm_content', 200),
            gclid: gclid,
            wbraid: wbraid,
            gbraid: gbraid,
            landing_path: currentPath(),
            referrer_url: referrer,
            observed_at_gmt: isoNow(),
        };
    }

    function directTouch() {
        return {
            source: 'direct',
            medium: 'none',
            campaign: '',
            term: '',
            content: '',
            gclid: '',
            wbraid: '',
            gbraid: '',
            landing_path: currentPath(),
            referrer_url: '',
            observed_at_gmt: isoNow(),
        };
    }

    function touchFingerprint(touch) {
        if (!isObject(touch)) return '';
        return [touch.source, touch.medium, touch.campaign, touch.term, touch.content, touch.gclid, touch.wbraid, touch.gbraid].join('|');
    }

    function validState(state) {
        return isObject(state)
            && state.schema_version === SCHEMA_VERSION
            && Number(state.expires_at) > Date.now()
            && isObject(state.first_touch)
            && isObject(state.last_touch)
            && Array.isArray(state.journey);
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
        var json = JSON.stringify(value);
        if (window.TextEncoder) return new TextEncoder().encode(json).length;
        return json.length * 2;
    }

    function trimToLimit(value) {
        while (value.journey.length && byteLength(value) > MAX_BYTES) value.journey.shift();
        return byteLength(value) <= MAX_BYTES ? value : null;
    }

    function updateState() {
        var path = currentPath();
        if (isExcluded(path)) return;

        var detected = detectTouch();
        var state = loadState();
        if (!state) {
            var firstTouch = detected || directTouch();
            state = {
                schema_version: SCHEMA_VERSION,
                first_visit_at_gmt: isoNow(),
                expires_at: 0,
                first_touch: firstTouch,
                last_touch: firstTouch,
                journey: [],
            };
        } else if (detected && touchFingerprint(detected) !== touchFingerprint(state.last_touch)) {
            state.last_touch = detected;
        }

        if (journeyLimit > 0) {
            var previous = state.journey[state.journey.length - 1];
            if (!previous || previous.path !== path) {
                state.journey.push({ path: path, observed_at_gmt: isoNow() });
            }
            state.journey = state.journey.slice(-journeyLimit);
        } else {
            state.journey = [];
        }
        state.expires_at = Date.now() + retentionDays * 24 * 60 * 60 * 1000;
        state = trimToLimit(state);
        if (state) BrowserState.setPreferenceScope('attribution', state);
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

    function getSnapshot(context) {
        try {
            var path = currentPath();
            if (isExcluded(path)) return null;
            var state = loadState();
            if (!state) return null;

            var snapshot = {
                schema_version: SCHEMA_VERSION,
                first_visit_at_gmt: state.first_visit_at_gmt,
                first_touch: state.first_touch,
                last_touch: state.last_touch,
                journey: state.journey.slice(),
                event: {
                    type: context === 'form_submit' ? 'form_submit' : 'chat_message',
                    lead_event_id: context === 'form_submit' ? uuid() : '',
                    page_path: path,
                    observed_at_gmt: isoNow(),
                },
            };
            var visitorId = BrowserState.getConfirmedVisitorId();
            if (visitorId) snapshot.visitor_id = visitorId;
            return trimToLimit(snapshot);
        } catch (error) {
            return null;
        }
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
            var snapshot = getSnapshot('form_submit');
            field.value = snapshot ? JSON.stringify(snapshot) : '';
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
                var leadEventId = snapshot && snapshot.event ? clean(snapshot.event.lead_event_id, 36) : '';
                if (!leadEventId) return;
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    event: 'elementor_generate_lead',
                    form_id: clean(form.id || form.getAttribute('name') || '', 120),
                    lead_event_id: leadEventId,
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
