(function(window) {
    'use strict';

    if (window.WPAIGentBrowserState) return;

    var STORAGE_KEY = 'wp_aigent_browser_state';
    var memoryState = { version: 2, visitor_id: '', preferences: {} };

    function isObject(value) {
        return value && typeof value === 'object' && !Array.isArray(value);
    }

    function clone(value, fallback) {
        try {
            return JSON.parse(JSON.stringify(value));
        } catch (error) {
            return fallback;
        }
    }

    function normalize(value) {
        value = isObject(value) ? value : {};
        var preferences = isObject(value.preferences) ? Object.assign({}, value.preferences) : {};
        if (isObject(preferences.identity)) {
            preferences.identity = Object.assign({}, preferences.identity);
            delete preferences.identity.visitor_token;
            if (!Object.keys(preferences.identity).length) delete preferences.identity;
        }

        return {
            version: 2,
            visitor_id: typeof value.visitor_id === 'string' ? value.visitor_id : '',
            preferences: preferences,
        };
    }

    function read() {
        try {
            if (!window.localStorage) return memoryState;
            var raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) return memoryState;
            memoryState = normalize(JSON.parse(raw));
        } catch (error) {}
        return memoryState;
    }

    function write(state) {
        memoryState = normalize(state);
        try {
            if (window.localStorage) {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(memoryState));
            }
        } catch (error) {}
        return memoryState;
    }

    function update(mutator) {
        var state = read();
        mutator(state);
        return write(state);
    }

    function hasPersistentState() {
        try {
            return Boolean(window.localStorage && window.localStorage.getItem(STORAGE_KEY) !== null);
        } catch (error) {
            return false;
        }
    }

    function getPreference(scope, key, fallback) {
        var values = read().preferences;
        values = isObject(values[scope]) ? values[scope] : {};
        return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : fallback;
    }

    function setPreference(scope, key, value) {
        update(function(state) {
            if (!isObject(state.preferences[scope])) state.preferences[scope] = {};
            state.preferences[scope][key] = value;
        });
    }

    function removePreference(scope, key) {
        update(function(state) {
            if (!isObject(state.preferences[scope])) return;
            delete state.preferences[scope][key];
            if (!Object.keys(state.preferences[scope]).length) delete state.preferences[scope];
        });
    }

    function getPreferenceScope(scope) {
        var values = read().preferences;
        return isObject(values[scope]) ? clone(values[scope], null) : null;
    }

    function setPreferenceScope(scope, value) {
        update(function(state) {
            if (isObject(value)) {
                state.preferences[scope] = clone(value, {});
            } else {
                delete state.preferences[scope];
            }
        });
    }

    function setVisitorIdentity(visitorId, expiresAt) {
        update(function(state) {
            state.visitor_id = typeof visitorId === 'string' ? visitorId : '';
            if (!isObject(state.preferences.identity)) state.preferences.identity = {};
            state.preferences.identity.visitor_confirmed_at_gmt = new Date().toISOString();
            state.preferences.identity.visitor_expires_at_gmt = typeof expiresAt === 'string' ? expiresAt : '';
        });
    }

    function getConfirmedVisitorId() {
        var state = read();
        var visitorId = state.visitor_id;
        var expiresAt = getPreference('identity', 'visitor_expires_at_gmt', '');
        if (typeof visitorId !== 'string'
            || !/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(visitorId)
            || typeof expiresAt !== 'string'
            || !Number.isFinite(Date.parse(expiresAt))
            || Date.parse(expiresAt) <= Date.now()
        ) {
            return '';
        }
        return visitorId.toLowerCase();
    }

    function clearLegacyStorage() {
        try {
            if (!window.localStorage) return;
            for (var index = window.localStorage.length - 1; index >= 0; index--) {
                var key = window.localStorage.key(index);
                if (key === 'wp_aigent_visitor_id'
                    || key === 'ai_chat_visitor'
                    || /^(ai_chat_token_|ai_chat_sid_|ai_chat_open_)/.test(key || '')
                ) {
                    window.localStorage.removeItem(key);
                }
            }
        } catch (error) {}
    }

    window.WPAIGentBrowserState = Object.freeze({
        storageKey: STORAGE_KEY,
        hasPersistentState: hasPersistentState,
        getVisitorId: function() { return read().visitor_id; },
        setVisitorId: function(visitorId) {
            update(function(state) { state.visitor_id = visitorId; });
        },
        setVisitorIdentity: setVisitorIdentity,
        getConfirmedVisitorId: getConfirmedVisitorId,
        getPreference: getPreference,
        setPreference: setPreference,
        removePreference: removePreference,
        getPreferenceScope: getPreferenceScope,
        setPreferenceScope: setPreferenceScope,
        removePreferenceScope: function(scope) { setPreferenceScope(scope, null); },
        clearLegacyStorage: clearLegacyStorage,
    });
})(window);
