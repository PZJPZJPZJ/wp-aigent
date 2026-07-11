(function() {
    'use strict';

    class AIChatWidget {
        constructor(container, config) {
            this.container = container;
            if (!this.container) return;

            this.config = config;
            this.widgetId = config.widget_id;
            this.sessionId = config.session_id || '';
            this.sessionToken = config.session_token || '';
            this.apiUrl = (window.AIChatBotGlobals && AIChatBotGlobals.rest_url) || '';
            this.isEditor = config.is_editor === '1';
            this.isOpen = config.layout_mode === 'box';
            this.hasHistory = false;
            this.timers = [];
            this.destroyed = false;

            this.prepareSession();
            this.init();
        }

        prepareSession() {
            if (this.isEditor) {
                this.visitorId = 'editor-preview';
                return;
            }

            this.visitorId = this.safeStorageGet('ai_chat_visitor');
            if (!this.visitorId) {
                this.visitorId = this.generateUUID();
                this.safeStorageSet('ai_chat_visitor', this.visitorId);
            }

            var storedToken = this.safeStorageGet('ai_chat_token_' + this.visitorId);
            this.sessionToken = storedToken || '';

            var storedSessionId = this.safeStorageGet('ai_chat_sid_' + this.visitorId + '_' + this.config.chatbot_id);
            if (storedSessionId) {
                this.sessionId = storedSessionId;
            }
        }

        generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        }

        safeStorageGet(key) {
            try {
                return window.localStorage ? localStorage.getItem(key) : null;
            } catch (e) {
                return null;
            }
        }

        safeStorageSet(key, value) {
            try {
                if (window.localStorage) {
                    localStorage.setItem(key, value);
                }
            } catch (e) {}
        }

        safeStorageRemove(key) {
            try {
                if (window.localStorage) {
                    localStorage.removeItem(key);
                }
            } catch (e) {}
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

            if (!this.isEditor) {
                await this.loadHistory();
            }

            if (!this.destroyed && !this.hasHistory && this.config.greeting) {
                this.addMessage('bot', this.config.greeting);
            }
        }

        scheduleDefaultOpen() {
            var cacheKey = 'ai_chat_open_' + this.config.chatbot_id;
            var cached = this.safeStorageGet(cacheKey);
            var ttl = Number(this.config.open_cache_ttl || 1440) * 60 * 1000;

            if (cached === 'closed') {
                var cachedTime = this.safeStorageGet(cacheKey + '_time');
                if (cachedTime && (Date.now() - Number(cachedTime)) > ttl) {
                    this.safeStorageRemove(cacheKey);
                    this.safeStorageRemove(cacheKey + '_time');
                    cached = null;
                }
            }

            if (cached !== 'closed') {
                var delay = Number(this.config.fab_open_delay || 0) * 1000;
                var timer = setTimeout(function() {
                    if (!this.destroyed) {
                        this.toggleChat(true);
                    }
                }.bind(this), delay);
                this.timers.push(timer);
            }
        }

        async loadHistory() {
            if (!this.apiUrl || !window.AIChatBotGlobals) return;

            try {
                var url = (AIChatBotGlobals.history_url || this.apiUrl.replace('/chat', '/history')) + '?' + new URLSearchParams({
                    chatbot_id: this.config.chatbot_id,
                    visitor_id: this.visitorId,
                    session_id: this.sessionId || '',
                    session_token: this.sessionToken || '',
                });

                var res = await fetch(url, {
                    method: 'GET',
                    headers: { 'X-WP-Nonce': AIChatBotGlobals.nonce },
                });

                var data = await res.json();
                if (this.destroyed) return;

                if (data.ok && data.data) {
                    if (data.data.session_token) {
                        this.sessionToken = data.data.session_token;
                        this.safeStorageSet('ai_chat_token_' + this.visitorId, data.data.session_token);
                        if (data.data.session_id) {
                            this.safeStorageSet('ai_chat_sid_' + this.visitorId + '_' + this.config.chatbot_id, data.data.session_id);
                        }
                    }

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
                this.fabButtonEl.addEventListener('click', () => this.toggleChat());
            }
            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    this.toggleChat(false);
                }.bind(this));
            }
        }

        toggleChat(forceState) {
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
                if (!this.isEditor && this.config.fab_default_open === '1') {
                    var cacheKey = 'ai_chat_open_' + this.config.chatbot_id;
                    this.safeStorageSet(cacheKey, 'closed');
                    this.safeStorageSet(cacheKey + '_time', Date.now().toString());
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
                this.addMessage('bot', 'Sorry, the chat endpoint is not available.');
                return;
            }

            this.addMessage('user', text);
            this.showTyping();

            try {
                const res = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': AIChatBotGlobals.nonce,
                    },
                    body: JSON.stringify({
                        chatbot_id: this.config.chatbot_id,
                        message: text,
                        session_id: this.sessionId,
                        session_token: this.sessionToken,
                        visitor_id: this.visitorId,
                        metadata: {
                            page: location.href,
                            referrer: document.referrer,
                            language: navigator.language,
                            user_agent: navigator.userAgent,
                            screen: screen.width + 'x' + screen.height,
                            timestamp: new Date().toISOString(),
                        },
                    }),
                });

                const data = await res.json();
                if (this.destroyed) return;
                this.hideTyping();

                if (data.ok) {
                    if (data.data && data.data.session_token) {
                        this.sessionToken = data.data.session_token;
                        this.safeStorageSet('ai_chat_token_' + this.visitorId, data.data.session_token);
                        this.safeStorageSet('ai_chat_sid_' + this.visitorId + '_' + this.config.chatbot_id, data.data.session_id);
                    }
                    this.addMessage('bot', data.data.reply);
                    if (data.data.should_collect_contact && !this.contactShown) {
                        this.contactShown = true;
                        this.showContactForm();
                    }
                } else {
                    console.warn('API error:', data.message || data.code);
                    this.addMessage('bot', 'Sorry: ' + (data.message || 'Request failed.'));
                }
            } catch (err) {
                console.error('Chat fetch error:', err);
                this.hideTyping();
                this.addMessage('bot', 'Sorry, a network error occurred. Please try again.');
            }
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
