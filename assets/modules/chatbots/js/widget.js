(function() {
    'use strict';

    const BROWSER_STATE_KEY = 'wp_aigent_browser_state';
    const BrowserState = window.WPAIGentBrowserState || (function() {
        var memoryState = { version: 2, visitor_id: '', preferences: {} };

        function isObject(value) {
            return value && typeof value === 'object' && !Array.isArray(value);
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
                var raw = localStorage.getItem(BROWSER_STATE_KEY);
                if (!raw) return memoryState;
                memoryState = normalize(JSON.parse(raw));
            } catch (e) {}
            return memoryState;
        }

        function write(state) {
            memoryState = normalize(state);
            try {
                if (window.localStorage) {
                    localStorage.setItem(BROWSER_STATE_KEY, JSON.stringify(memoryState));
                }
            } catch (e) {}
            return memoryState;
        }

        function update(mutator) {
            var state = read();
            mutator(state);
            return write(state);
        }

        function getPreference(scope, key, fallback) {
            var preferences = read().preferences;
            var values = isObject(preferences[scope]) ? preferences[scope] : {};
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

        function clearLegacyStorage() {
            try {
                if (!window.localStorage) return;
                for (var index = localStorage.length - 1; index >= 0; index--) {
                    var key = localStorage.key(index);
                    if (key === 'wp_aigent_visitor_id' || key === 'ai_chat_visitor' || /^(ai_chat_token_|ai_chat_sid_|ai_chat_open_)/.test(key || '')) {
                        localStorage.removeItem(key);
                    }
                }
            } catch (e) {}
        }

        return Object.freeze({
            storageKey: BROWSER_STATE_KEY,
            getVisitorId: function() { return read().visitor_id; },
            setVisitorId: function(visitorId) {
                update(function(state) { state.visitor_id = visitorId; });
            },
            getPreference: getPreference,
            setPreference: setPreference,
            removePreference: removePreference,
            clearLegacyStorage: clearLegacyStorage,
        });
    })();
    window.WPAIGentBrowserState = BrowserState;

    class AIChatWidget {
        constructor(container, config) {
            this.container = container;
            if (!this.container) return;

            this.config = config;
            this.widgetId = config.widget_id;
            this.apiUrl = (window.AIChatBotGlobals && AIChatBotGlobals.rest_url) || '';
            this.isEditor = config.is_editor === '1';
            this.isOpen = config.layout_mode === 'box';
            this.hasHistory = false;
            this.timers = [];
            this.destroyed = false;
            this.offlineMessageShown = false;

            this.init();
        }

        async prepareSession(forceRefresh) {
            if (this.isEditor) {
                this.visitorId = 'editor-preview';
                return;
            }

            BrowserState.clearLegacyStorage();
            BrowserState.setVisitorId(BrowserState.getVisitorId());
            if (forceRefresh) window.wpAIgentVisitorCredentialPromise = null;

            if (!window.wpAIgentVisitorCredentialPromise) {
                window.wpAIgentVisitorCredentialPromise = this.requestVisitorCredential().catch(function(error) {
                    window.wpAIgentVisitorCredentialPromise = null;
                    throw error;
                });
            }
            var credential = await window.wpAIgentVisitorCredentialPromise;
            this.visitorId = credential.visitor_id;
            BrowserState.setVisitorId(this.visitorId);
        }

        async requestVisitorCredential() {
            var globals = window.AIChatBotGlobals || {};
            if (!globals.visitor_url) throw new Error('Visitor identity endpoint is unavailable.');

            var response = await fetch(globals.visitor_url, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Accept': 'application/json' },
            });
            var payload = await response.json();
            var credential = payload && payload.data ? payload.data : {};
            if (!response.ok || !payload.ok || !this.isVisitorId(credential.visitor_id)) {
                throw new Error(payload.message || 'The server could not issue a visitor identity.');
            }
            return credential;
        }

        isVisitorId(value) {
            return typeof value === 'string' && /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(value);
        }

        async init() {
            this.render();
            this.bindEvents();

            if (this.config.layout_mode === 'button') {
                if (this.config.fab_default_open === '1') {
                    if (this.isEditor) {
                        this.toggleChat(true);
                    } else {
                        this.scheduleDefaultOpen();
                    }
                } else {
                    this.toggleChat(false);
                }
            }

            var identityReady = this.isEditor;
            if (!this.isEditor) {
                identityReady = true;
                try {
                    await this.prepareSession();
                    await this.loadHistory();
                } catch (error) {
                    identityReady = false;
                    console.error('Visitor identity error:', error);
                    this.showOfflineMessage();
                }
            }

            if (!this.destroyed && !this.hasHistory && this.config.greeting && (this.isEditor || identityReady)) {
                this.addMessage('bot', this.config.greeting);
            }
        }

        scheduleDefaultOpen() {
            var scope = 'chatbot:' + this.config.chatbot_id;
            var closedAt = Number(BrowserState.getPreference(scope, 'popup_closed_at', 0));
            var ttl = Number(this.config.open_cache_ttl || 1440) * 60 * 1000;
            if (closedAt && ttl > 0 && Date.now() - closedAt < ttl) return;
            if (closedAt) BrowserState.removePreference(scope, 'popup_closed_at');

            var delay = Number(this.config.fab_open_delay || 0) * 1000;
            var timer = setTimeout(function() {
                if (!this.destroyed) this.toggleChat(true);
            }.bind(this), delay);
            this.timers.push(timer);
        }

        async loadHistory(allowCredentialRetry) {
            if (!this.apiUrl || !window.AIChatBotGlobals) return;
            if (allowCredentialRetry === undefined) allowCredentialRetry = true;

            try {
                var url = AIChatBotGlobals.history_url || this.apiUrl.replace('/chat', '/history');

                var res = await fetch(url, {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ chatbot_id: this.config.chatbot_id }),
                });

                var data = await res.json();
                if (this.destroyed) return;
                if (res.status === 401 && allowCredentialRetry) {
                    await this.prepareSession(true);
                    return this.loadHistory(false);
                }

                if (!data.ok) {
                    console.warn('Chat history API error:', {
                        status: res.status,
                        code: data.code || '',
                        message: data.message || '',
                    });
                    return;
                }

                if (data.ok && data.data) {
                    if (Array.isArray(data.data.messages) && data.data.messages.length > 0) {
                        this.hasHistory = true;
                        const fragment = document.createDocumentFragment();
                        data.data.messages.forEach(function(msg) {
                            fragment.appendChild(this.createMessageElement(msg.role, msg.content));
                        }.bind(this));
                        this.messagesEl.appendChild(fragment);
                        this.scrollToBottom();
                    }
                }
            } catch (err) {
                console.error('History fetch error:', err);
            }
        }

        render() {
            const i18n = this.config.i18n || {};
            const isButton = this.config.layout_mode === 'button';
            const title = this.escapeHtml(i18n.title || 'Contact Us For Any Support');
            const subtitle = this.escapeHtml(i18n.subtitle || 'Share Your Needs and We Will Contact You Within 24 Hours.');
            const placeholder = this.escapeAttr(i18n.input_placeholder || 'Type your message...');
            const closeLabel = this.escapeAttr('Close');
            const sendIconHtml = '<span class="ai-chatbot-send-icon">' + this.renderSendIcon() + '</span>';

            let html = '';
            if (isButton) {
                var shakeClass = this.config.icon_shake === '1' ? ' ai-chatbot-fab-icon-shake' : '';
                var rippleDivs = '';
                if (this.config.ripple_enabled === '1') {
                    var speed = parseFloat(this.config.ripple_speed) || 1.5;
                    for (var r = 0; r < 3; r++) {
                        var delay = (speed / 4) * r;
                        rippleDivs += '<div class="ai-chatbot-fab-ripple" style="animation-delay:' + delay.toFixed(2) + 's;"></div>';
                    }
                }
                var shouldShowHint = this.config.fab_hint_enabled === '1' && this.config.fab_hint;
                var hint = shouldShowHint ? '<div class="ai-chatbot-fab-hint ai-chatbot-fab-hint-' + this.escapeAttr(this.config.fab_hint_position || 'left') + '"><span>' + this.escapeHtml(this.config.fab_hint) + '</span></div>' : '';
                var iconHtml = '<span class="ai-chatbot-fab-icon' + shakeClass + '">' + this.renderFabIcon() + '</span>';
                var closeIconHtml = '<span class="ai-chatbot-close-icon">' + this.renderCloseIcon() + '</span>';
                html = `
                    <div class="ai-chatbot-fab" data-widget="${this.escapeAttr(this.widgetId)}">
                        <button type="button" class="ai-chatbot-fab-button" aria-expanded="false">${rippleDivs}${iconHtml}</button>
                        ${hint}
                    </div>
                    <div class="ai-chatbot-popup">
                        <div class="ai-chatbot-header">
                            <span class="ai-chatbot-title">${title}</span>
                            <span class="ai-chatbot-subtitle">${subtitle}</span>
                            <button type="button" class="ai-chatbot-close" aria-label="${closeLabel}">${closeIconHtml}</button>
                        </div>
                        <div class="ai-chatbot-messages"></div>
                        <div class="ai-chatbot-input-area">
                            <textarea class="ai-chatbot-input" placeholder="${placeholder}" rows="1" maxlength="2000"></textarea>
                            <button type="button" class="ai-chatbot-send">${sendIconHtml}</button>
                        </div>
                    </div>
                `;
            } else {
                html = `
                    <div class="ai-chatbot-box">
                        <div class="ai-chatbot-header">
                            <span class="ai-chatbot-title">${title}</span>
                            <span class="ai-chatbot-subtitle">${subtitle}</span>
                        </div>
                        <div class="ai-chatbot-messages"></div>
                        <div class="ai-chatbot-input-area">
                            <textarea class="ai-chatbot-input" placeholder="${placeholder}" rows="1" maxlength="2000"></textarea>
                            <button type="button" class="ai-chatbot-send">${sendIconHtml}</button>
                        </div>
                    </div>
                `;
            }

            this.container.innerHTML = html;

            this.messagesEl = this.container.querySelector('.ai-chatbot-messages');
            this.inputEl = this.container.querySelector('.ai-chatbot-input');
            this.sendBtn = this.container.querySelector('.ai-chatbot-send');
            this.fabEl = this.container.querySelector('.ai-chatbot-fab');
            this.fabButtonEl = this.container.querySelector('.ai-chatbot-fab-button');
            this.popupEl = this.container.querySelector('.ai-chatbot-popup');
            this.closeBtn = this.container.querySelector('.ai-chatbot-close');
        }

        bindEvents() {
            if (this.sendBtn) {
                this.sendBtn.addEventListener('click', () => this.handleSend());
            }
            if (this.inputEl) {
                this.inputEl.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.handleSend();
                    }
                });
                this.inputEl.addEventListener('input', () => this.autoResize());
            }
            if (this.fabButtonEl) {
                this.fabButtonEl.addEventListener('click', () => this.toggleChat(undefined, true));
            }
            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    this.toggleChat(false, true);
                }.bind(this));
            }
        }

        toggleChat(forceState, persistClose) {
            if (!this.popupEl) return;
            this.isOpen = forceState !== undefined ? forceState : !this.isOpen;

            if (this.fabButtonEl) {
                this.fabButtonEl.setAttribute('aria-expanded', this.isOpen ? 'true' : 'false');
            }

            if (this.isOpen) {
                this.popupEl.classList.add('is-open');
                requestAnimationFrame(function() {
                    this.keepPopupInViewport();
                    this.scrollToBottom();
                }.bind(this));
                if (!this.isEditor && this.inputEl) {
                    this.inputEl.focus();
                }
            } else {
                this.popupEl.classList.remove('is-open');
                this.popupEl.style.right = '';
                this.popupEl.style.left = '';
                if (persistClose && !this.isEditor && this.config.fab_default_open === '1') {
                    BrowserState.setPreference('chatbot:' + this.config.chatbot_id, 'popup_closed_at', Date.now());
                }
            }
        }

        keepPopupInViewport() {
            if (!this.popupEl) return;

            var rect = this.popupEl.getBoundingClientRect();
            if (rect.right > window.innerWidth) {
                this.popupEl.style.left = 'auto';
                this.popupEl.style.right = '0';
            }
            if (rect.left < 0) {
                this.popupEl.style.right = 'auto';
                this.popupEl.style.left = '0';
            }
        }

        handleSend() {
            if (!this.inputEl) return;

            const text = this.inputEl.value.trim();
            if (!text) return;
            this.inputEl.value = '';
            this.autoResize();

            if (this.isEditor) {
                this.addMessage('user', text);
                this.addMessage('bot', this.config.offline_msg || 'Preview mode.');
                return;
            }

            this.sendMessage(text);
        }

        async sendMessage(text) {
            if (!this.apiUrl || !window.AIChatBotGlobals) {
                console.error('Chat endpoint is not available.');
                this.showOfflineMessage();
                return;
            }

            this.addMessage('user', text);
            this.showTyping();

            try {
                await this.prepareSession();
                const result = await this.requestChat(text, true);
                const data = result.data;
                if (this.destroyed) return;
                this.hideTyping();

                if (data.ok) {
                    this.offlineMessageShown = false;
                    this.addMessage('bot', data.data.reply);
                    if (data.data.should_collect_contact && !this.contactShown) {
                        this.contactShown = true;
                        this.showContactForm();
                    }
                } else {
                    console.warn('Chat API error:', {
                        status: result.status,
                        code: data.code || '',
                        message: data.message || '',
                    });
                    this.showOfflineMessage();
                }
            } catch (err) {
                console.error('Chat fetch error:', err);
                this.hideTyping();
                this.showOfflineMessage();
            }
        }

        async requestChat(text, allowCredentialRetry) {
            const res = await fetch(this.apiUrl, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    chatbot_id: this.config.chatbot_id,
                    message: text,
                    metadata: {
                        page: location.href,
                        referrer: document.referrer,
                        language: navigator.language,
                    },
                }),
            });

            const data = await res.json();
            if (res.status === 401 && allowCredentialRetry) {
                await this.prepareSession(true);
                return this.requestChat(text, false);
            }

            return { status: res.status, data: data };
        }

        showOfflineMessage() {
            var message = typeof this.config.offline_msg === 'string'
                ? this.config.offline_msg.trim()
                : '';
            if (!message || this.offlineMessageShown || this.destroyed) return;

            this.offlineMessageShown = true;
            this.addMessage('bot', message);
        }

        createMessageElement(role, content) {
            const div = document.createElement('div');
            div.className = 'ai-chatbot-message ai-chatbot-' + role;

            if (role === 'bot' && this.config.avatar) {
                const img = document.createElement('img');
                img.className = 'ai-chatbot-avatar';
                img.src = this.config.avatar;
                img.alt = '';
                div.appendChild(img);
            }

            const bubble = document.createElement('div');
            bubble.className = 'ai-chatbot-bubble';

            if (role === 'bot') {
                bubble.innerHTML = this.renderMarkdown(content);
            } else {
                bubble.textContent = content;
            }

            div.appendChild(bubble);
            return div;
        }

        addMessage(role, content) {
            if (!this.messagesEl) return;

            const div = this.createMessageElement(role, content);
            this.messagesEl.appendChild(div);
            this.scrollToBottom();
        }

        showTyping() {
            if (!this.messagesEl) return;

            const div = document.createElement('div');
            div.className = 'ai-chatbot-message ai-chatbot-bot';
            div.id = this.widgetId + '-typing';

            const bubble = document.createElement('div');
            bubble.className = 'ai-chatbot-bubble ai-chatbot-typing';
            var thinkingText = (this.config.i18n && this.config.i18n.thinking_text) ? this.config.i18n.thinking_text : '';
            bubble.innerHTML = (thinkingText ? '<span class="ai-chatbot-thinking-text">' + this.escapeHtml(thinkingText) + '</span>' : '') + '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
            div.appendChild(bubble);
            this.messagesEl.appendChild(div);
            this.scrollToBottom();
        }

        hideTyping() {
            const el = document.getElementById(this.widgetId + '-typing');
            if (el) el.remove();
        }

        showContactForm() {
            var fields = this.config.lead_fields || [
                {name:'name', placeholder:'Name'},
                {name:'email', placeholder:'Email'},
                {name:'whatsapp', placeholder:'WhatsApp'}
            ];
            var html = '<p>Would you like to leave your contact information?</p>';
            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                var type = f.name && f.name.toLowerCase().indexOf('email') !== -1 ? 'email' : 'text';
                html += '<input type="' + type + '" placeholder="' + this.escapeAttr(f.placeholder || f.name) + '" data-field="' + this.escapeAttr(f.name || '') + '" class="ai-chat-contact-input" />';
            }
            html += '<button type="button" class="ai-chat-contact-submit">Submit</button>';

            const div = document.createElement('div');
            div.className = 'ai-chatbot-message ai-chatbot-bot';
            const bubble = document.createElement('div');
            bubble.className = 'ai-chatbot-bubble ai-chatbot-contact-form';
            bubble.innerHTML = html;
            div.appendChild(bubble);
            this.messagesEl.appendChild(div);

            var self = this;
            var submitBtn = div.querySelector('.ai-chat-contact-submit');
            submitBtn.addEventListener('click', function() {
                var parts = [];
                var inputs = div.querySelectorAll('.ai-chat-contact-input');
                for (var j = 0; j < inputs.length; j++) {
                    var inp = inputs[j];
                    var label = inp.getAttribute('data-field') || inp.getAttribute('placeholder') || 'field_' + j;
                    var val = inp.value.trim() || 'not provided';
                    parts.push(label + ': ' + val);
                }
                var msg = 'My contact information: ' + parts.join(', ');
                div.style.display = 'none';
                self.sendMessage(msg);
            });
        }

        renderFabIcon() {
            return this.renderIcon('fab_icon_html', 'fab_icon', 'fas fa-envelope');
        }

        renderCloseIcon() {
            return this.renderIcon('close_icon_html', 'close_icon', 'fas fa-times');
        }

        renderSendIcon() {
            return this.renderIcon('send_icon_html', 'send_icon', 'fas fa-paper-plane');
        }

        renderIcon(htmlKey, iconKey, fallbackClass) {
            var iconHtml = this.config[htmlKey];
            if (iconHtml) {
                return iconHtml;
            }

            var icon = this.config[iconKey];
            if (icon && typeof icon === 'object') {
                icon = icon.value || '';
            }

            if (!icon) {
                return '<i class="' + this.escapeAttr(fallbackClass) + '" aria-hidden="true"></i>';
            }
            if (typeof icon === 'string' && icon.indexOf('dashicons-') === 0) {
                return '<span class="dashicons ' + this.escapeAttr(icon) + '"></span>';
            }
            if (typeof icon !== 'string') {
                return '<i class="' + this.escapeAttr(fallbackClass) + '" aria-hidden="true"></i>';
            }
            return '<i class="' + this.escapeAttr(icon) + '" aria-hidden="true"></i>';
        }

        renderMarkdown(text) {
            if (typeof text !== 'string') return '';
            try {
                return text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.+?)\*/g, '<em>$1</em>')
                    .replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
                    .replace(/\n/g, '<br>');
            } catch (e) {
                console.warn('AI Chatbot markdown render error:', e);
                return this.escapeHtml(text);
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        escapeAttr(text) {
            return this.escapeHtml(String(text)).replace(/"/g, '&quot;');
        }

        autoResize() {
            if (this.inputEl) {
                this.inputEl.style.height = 'auto';
                this.inputEl.style.height = this.inputEl.scrollHeight + 'px';
            }
        }

        scrollToBottom() {
            if (this.messagesEl) {
                this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
            }
        }

        destroy() {
            this.destroyed = true;
            this.timers.forEach(function(timer) {
                clearTimeout(timer);
            });
            this.timers = [];
            if (this.container) {
                this.container.innerHTML = '';
                this.container.__chatWidget = null;
                this.container.__chatWidgetHash = null;
            }
        }
    }

    function readConfig(el) {
        var configJson = el.getAttribute('data-config');
        if (!configJson) return null;

        try {
            return JSON.parse(configJson);
        } catch (e) {
            var decodedConfigJson = decodeHtmlEntities(configJson);
            if (decodedConfigJson !== configJson) {
                try {
                    return JSON.parse(decodedConfigJson);
                } catch (decodedError) {
                    console.warn('AI Chatbot config parse error:', decodedError);
                    return null;
                }
            }

            console.warn('AI Chatbot config parse error:', e);
            return null;
        }
    }

    function decodeHtmlEntities(value) {
        var textarea = document.createElement('textarea');
        textarea.innerHTML = value;
        return textarea.value;
    }

    function initContainer(el) {
        var config = readConfig(el);
        if (!config) return;

        var hash = el.getAttribute('data-config-hash') || JSON.stringify(config);
        if (el.__chatWidget && el.__chatWidgetHash === hash) {
            return;
        }

        if (el.__chatWidget && typeof el.__chatWidget.destroy === 'function') {
            el.__chatWidget.destroy();
        }

        el.__chatWidget = new AIChatWidget(el, config);
        el.__chatWidgetHash = hash;
    }

    function initScope(scope) {
        var root = scope && scope.querySelectorAll ? scope : document;
        root.querySelectorAll('.ai-chatbot-container').forEach(initContainer);
    }

    function initElementorWidget($scope) {
        var scopeEl = $scope && $scope[0] ? $scope[0] : document;
        initScope(scopeEl);
    }

    function bindElementorReadyHook() {
        if (typeof elementorFrontend === 'undefined' || !elementorFrontend.hooks) {
            return;
        }

        if (window.aiChatbotElementorHookBound) {
            return;
        }

        elementorFrontend.hooks.addAction('frontend/element_ready/ai_chatbot.default', initElementorWidget);
        window.aiChatbotElementorHookBound = true;
    }

    if (window.jQuery) {
        jQuery(window).on('elementor/frontend/init', bindElementorReadyHook);
    }
    bindElementorReadyHook();

    document.addEventListener('DOMContentLoaded', function() {
        initScope(document);
    });
})();
