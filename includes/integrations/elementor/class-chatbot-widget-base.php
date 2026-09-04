<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Widget_Base extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'ai_chatbot';
    }

    public function get_title(): string {
        return __('AI Chatbot', 'wp-aigent');
    }

    public function get_icon(): string {
        return 'eicon-comments';
    }

    public function get_categories(): array {
        return ['general'];
    }

    public function get_script_depends(): array {
        return ['ai-chat-widget'];
    }

    public function get_style_depends(): array {
        return ['ai-chat-widget', 'font-awesome'];
    }

    protected function register_controls(): void {
        $this->start_controls_section('section_chatbot', [
            'label' => __('Chatbot', 'wp-aigent'),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_live_control('chatbot_id', [
            'label'   => __('Select Chatbot', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => $this->get_chatbot_options(),
            'default' => '',
        ]);

        $this->add_live_control('layout_mode', [
            'label'   => __('Layout Mode', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'button' => [
                    'title' => __('Button', 'wp-aigent'),
                    'icon'  => 'eicon-button',
                ],
                'box' => [
                    'title' => __('Box', 'wp-aigent'),
                    'icon'  => 'eicon-editor-list-ul',
                ],
            ],
            'default' => 'button',
            'toggle'  => false,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_messages', [
            'label' => __('Messages', 'wp-aigent'),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_live_control('greeting', [
            'label'       => __('Greeting Message', 'wp-aigent'),
            'type'        => \Elementor\Controls_Manager::TEXTAREA,
            'rows'        => 3,
            'default'     => 'Hello! How can I help you today?',
            'description' => __('Supports Markdown formatting.', 'wp-aigent'),
        ]);

        $this->add_live_control('offline_msg', [
            'label'       => __('Offline Message', 'wp-aigent'),
            'type'        => \Elementor\Controls_Manager::TEXTAREA,
            'rows'        => 3,
            'default'     => 'We are currently offline. Please leave a message.',
            'description' => __('Supports Markdown formatting.', 'wp-aigent'),
        ]);

        $this->add_live_control('title', [
            'label'   => __('Title', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Contact Us For Any Support',
        ]);

        $this->add_live_control('subtitle', [
            'label'   => __('Subtitle', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Share Your Needs and We Will Contact You Within 24 Hours.',
        ]);

        $this->add_live_control('input_placeholder', [
            'label'   => __('Input Placeholder', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Type your message...',
        ]);

        $this->add_live_control('thinking_text', [
            'label'   => __('Thinking Text', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_button_content', [
            'label'     => __('Button Content', 'wp-aigent'),
            'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
            'condition' => ['layout_mode' => 'button'],
        ]);

        $this->add_live_control('button_icon', [
            'label'   => __('Button Icon', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::ICONS,
            'default' => [
                'value'   => 'fas fa-envelope',
                'library' => 'fa-solid',
            ],
        ]);

        $this->add_live_control('hint_enabled', [
            'label'        => __('Show Hint', 'wp-aigent'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => '1',
            'default'      => '',
        ]);

        $this->add_live_control('hint_text', [
            'label'       => __('Hint Text', 'wp-aigent'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => 'Contact Us',
            'placeholder' => 'Contact Us',
            'condition'   => ['hint_enabled' => '1'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Style', 'wp-aigent'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_live_responsive_control('chat_width', [
            'label'       => __('Chat Width', 'wp-aigent'),
            'description' => __('Sets the popup or inline chat width. Leave empty to use the default size, and set device-specific values when needed.', 'wp-aigent'),
            'type'        => \Elementor\Controls_Manager::SLIDER,
            'size_units'  => ['px', '%', 'vw'],
            'range'       => [
                'px' => [
                    'min' => 240,
                    'max' => 1600,
                ],
                '%' => [
                    'min' => 10,
                    'max' => 100,
                ],
                'vw' => [
                    'min' => 10,
                    'max' => 100,
                ],
            ],
            'selectors'   => [
                '{{WRAPPER}} .ai-chatbot-popup, {{WRAPPER}} .ai-chatbot-box' => 'width: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_live_responsive_control('chat_height', [
            'label'       => __('Chat Height', 'wp-aigent'),
            'description' => __('Sets the popup or inline chat height. Leave empty to use the default size, and set device-specific values when needed.', 'wp-aigent'),
            'type'        => \Elementor\Controls_Manager::SLIDER,
            'size_units'  => ['px', 'vh'],
            'range'       => [
                'px' => [
                    'min' => 240,
                    'max' => 1200,
                ],
                'vh' => [
                    'min' => 20,
                    'max' => 100,
                ],
            ],
            'selectors'   => [
                '{{WRAPPER}} .ai-chatbot-popup, {{WRAPPER}} .ai-chatbot-box' => 'height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_live_control('popup_color', [
            'label'   => __('Popup/Header Color', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#25b366',
        ]);

        $this->add_live_control('button_color', [
            'label'   => __('Button Color', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#25b366',
        ]);

        $this->add_live_control('popup_transition_duration', [
            'label'     => __('Popup Transition (ms)', 'wp-aigent'),
            'type'      => \Elementor\Controls_Manager::NUMBER,
            'min'       => 0,
            'max'       => 1000,
            'step'      => 10,
            'default'   => 100,
            'condition' => ['layout_mode' => 'button'],
        ]);

        $this->add_live_control('send_icon', [
            'label'   => __('Send Icon', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::ICONS,
            'default' => [
                'value'   => 'fas fa-paper-plane',
                'library' => 'fa-solid',
            ],
        ]);

        $this->add_live_control('close_icon', [
            'label'     => __('Close Icon', 'wp-aigent'),
            'type'      => \Elementor\Controls_Manager::ICONS,
            'default'   => [
                'value'   => 'fas fa-times',
                'library' => 'fa-solid',
            ],
            'condition' => ['layout_mode' => 'button'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_input_bar_style', [
            'label' => __('Input Bar', 'wp-aigent'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_live_control('input_bar_color', [
            'label'       => __('Background Color', 'wp-aigent'),
            'description' => __('Choose a color with transparency to keep messages visible behind the input bar. Recommended opacity: 75–90%. A fully opaque color disables the see-through effect.', 'wp-aigent'),
            'type'        => \Elementor\Controls_Manager::COLOR,
            'alpha'       => true,
            'default'     => 'rgba(255, 255, 255, 0.82)',
            'selectors'   => [
                '{{WRAPPER}} .ai-chatbot-input-area' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_live_control('input_bar_blur', [
            'label'       => __('Background Blur', 'wp-aigent'),
            'description' => __('Applies Gaussian blur to chat content behind the translucent input bar. Recommended: 12px. Set to 0 to disable blur; unsupported browsers keep the selected background color.', 'wp-aigent'),
            'type'        => \Elementor\Controls_Manager::SLIDER,
            'size_units'  => ['px'],
            'range'       => [
                'px' => [
                    'min'  => 0,
                    'max'  => 40,
                    'step' => 1,
                ],
            ],
            'default'     => [
                'unit' => 'px',
                'size' => 12,
            ],
            'selectors'   => [
                '{{WRAPPER}} .ai-chatbot-input-area' => '-webkit-backdrop-filter: blur({{SIZE}}{{UNIT}}); backdrop-filter: blur({{SIZE}}{{UNIT}});',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_button_animation', [
            'label'     => __('Button Animation', 'wp-aigent'),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => ['layout_mode' => 'button'],
        ]);

        $this->add_live_control('ripple_enabled', [
            'label'        => __('Enable Ripple Animation', 'wp-aigent'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => '1',
            'default'      => '',
        ]);

        $this->add_live_control('ripple_color', [
            'label'     => __('Ripple Color', 'wp-aigent'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#25b366',
            'condition' => ['ripple_enabled' => '1'],
        ]);

        $this->add_live_control('ripple_opacity', [
            'label'     => __('Ripple Opacity', 'wp-aigent'),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'size_units' => [''],
            'range'     => [
                '' => [
                    'min'  => 0.1,
                    'max'  => 1,
                    'step' => 0.1,
                ],
            ],
            'default'   => ['size' => 0.2],
            'condition' => ['ripple_enabled' => '1'],
        ]);

        $this->add_live_control('ripple_speed', [
            'label'     => __('Ripple Speed', 'wp-aigent'),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['s'],
            'range'     => [
                's' => [
                    'min'  => 0.5,
                    'max'  => 3,
                    'step' => 0.1,
                ],
            ],
            'default'   => ['unit' => 's', 'size' => 1],
            'condition' => ['ripple_enabled' => '1'],
        ]);

        $this->add_live_control('ripple_radius', [
            'label'     => __('Ripple Radius', 'wp-aigent'),
            'type'      => \Elementor\Controls_Manager::SLIDER,
            'size_units' => [''],
            'range'     => [
                '' => [
                    'min'  => 1.5,
                    'max'  => 4,
                    'step' => 0.1,
                ],
            ],
            'default'   => ['size' => 2.5],
            'condition' => ['ripple_enabled' => '1'],
        ]);

        $this->add_live_control('icon_shake', [
            'label'        => __('Icon Shake', 'wp-aigent'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => '1',
            'default'      => '',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_button_hint', [
            'label'     => __('Button Hint', 'wp-aigent'),
            'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
            'condition' => [
                'layout_mode'   => 'button',
                'hint_enabled'  => '1',
            ],
        ]);

        $this->add_live_control('hint_position', [
            'label'   => __('Hint Position', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'left'   => __('Left', 'wp-aigent'),
                'right'  => __('Right', 'wp-aigent'),
                'top'    => __('Top', 'wp-aigent'),
                'bottom' => __('Bottom', 'wp-aigent'),
            ],
            'default' => 'left',
        ]);

        $this->add_live_control('hint_bg', [
            'label'   => __('Hint Background', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#ffffff',
        ]);

        $this->add_live_control('hint_text_color', [
            'label'   => __('Hint Text Color', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::COLOR,
            'default' => '#333333',
        ]);

        $this->add_live_control('hint_font_size', [
            'label'   => __('Hint Font Size', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'min'     => 8,
            'max'     => 48,
            'step'    => 1,
            'default' => 15,
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_button_behavior', [
            'label'     => __('Button Behavior', 'wp-aigent'),
            'tab'       => \Elementor\Controls_Manager::TAB_CONTENT,
            'condition' => ['layout_mode' => 'button'],
        ]);

        $this->add_live_control('default_open', [
            'label'        => __('Open Popup By Default', 'wp-aigent'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => '1',
            'default'      => '',
        ]);

        $this->add_live_control('open_delay', [
            'label'     => __('Open Delay (seconds)', 'wp-aigent'),
            'type'      => \Elementor\Controls_Manager::NUMBER,
            'min'       => 0,
            'max'       => 300,
            'step'      => 1,
            'default'   => 20,
            'condition' => ['default_open' => '1'],
        ]);

        $this->add_live_control('open_cache_ttl', [
            'label'       => __('Close Cache TTL (minutes)', 'wp-aigent'),
            'description' => __('After a visitor closes an automatically opened popup, do not automatically reopen it until this time has passed.', 'wp-aigent'),
            'type'        => \Elementor\Controls_Manager::NUMBER,
            'min'         => 1,
            'max'         => 10080,
            'step'        => 1,
            'default'     => 1440,
            'condition'   => ['default_open' => '1'],
        ]);

        $this->end_controls_section();
    }

    private function add_live_control(string $id, array $args): void {
        $args['render_type'] = 'template';
        $this->add_control($id, $args);
    }

    private function add_live_responsive_control(string $id, array $args): void {
        $args['render_type'] = 'template';
        $this->add_responsive_control($id, $args);
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $chatbot_id = (int) ($settings['chatbot_id'] ?? 0);

        if (empty($chatbot_id) || get_post_type($chatbot_id) !== 'ai_chatbot') {
            echo '<div class="ai-chat-error">'
                . esc_html__('Please select a chatbot in the widget settings.', 'wp-aigent')
                . '</div>';
            return;
        }

        $bot_config = AI_Chatbot_CPT_Chatbot::get_meta($chatbot_id);
        $widget_id = $this->get_id();
        $container_id = 'ai-chatbot-container-' . $widget_id;
        $config = $this->build_frontend_config($chatbot_id, $widget_id, $settings, $bot_config);
        $config['fab_icon_html'] = $this->render_icon_html($settings['button_icon'] ?? [], 'fas fa-envelope');
        $config['send_icon_html'] = $this->render_icon_html($settings['send_icon'] ?? [], 'fas fa-paper-plane');
        $config['close_icon_html'] = $this->render_icon_html($settings['close_icon'] ?? [], 'fas fa-times');

        $config_json = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $config_hash = md5((string) $config_json);
        $style = $this->build_css_vars($config);

        echo '<div id="' . esc_attr($container_id) . '" class="ai-chatbot-container" style="' . esc_attr($style) . '" data-widget-id="' . esc_attr($widget_id) . '" data-config="' . esc_attr((string) $config_json) . '" data-config-hash="' . esc_attr($config_hash) . '" data-layout="' . esc_attr($config['layout_mode']) . '"></div>';
    }

    private function build_frontend_config(int $chatbot_id, string $widget_id, array $settings, array $bot_config): array {
        $layout_mode = ($settings['layout_mode'] ?? 'button') === 'box' ? 'box' : 'button';
        $ripple_opacity = $this->slider_size($settings['ripple_opacity'] ?? null, '0.2');
        $ripple_speed = $this->slider_size($settings['ripple_speed'] ?? null, '1');
        $ripple_radius = $this->slider_size($settings['ripple_radius'] ?? null, '2.5');
        $hint_enabled = !empty($settings['hint_enabled']);
        $hint_text = (string) ($settings['hint_text'] ?? 'Contact Us');
        if ($hint_text === '') {
            $hint_text = 'Contact Us';
        }

        return [
            'chatbot_id'    => $chatbot_id,
            'widget_id'     => $widget_id,
            'layout_mode'   => $layout_mode,
            'greeting'      => (string) ($settings['greeting'] ?? ''),
            'offline_msg'   => (string) ($settings['offline_msg'] ?? ''),
            'avatar'        => '',
            'i18n'          => [
                'title'             => (string) ($settings['title'] ?? ''),
                'subtitle'          => (string) ($settings['subtitle'] ?? ''),
                'input_placeholder' => (string) ($settings['input_placeholder'] ?? ''),
                'thinking_text'     => (string) ($settings['thinking_text'] ?? ''),
            ],
            'fab_icon'      => $this->normalize_icon_setting($settings['button_icon'] ?? [], 'fas fa-envelope'),
            'fab_icon_html' => '',
            'send_icon'      => $this->normalize_icon_setting($settings['send_icon'] ?? [], 'fas fa-paper-plane'),
            'send_icon_html' => '',
            'close_icon'      => $this->normalize_icon_setting($settings['close_icon'] ?? [], 'fas fa-times'),
            'close_icon_html' => '',
            'ripple_enabled' => !empty($settings['ripple_enabled']) ? '1' : '0',
            'ripple_color'   => (string) ($settings['ripple_color'] ?? ''),
            'ripple_opacity' => $ripple_opacity,
            'ripple_speed'   => $ripple_speed,
            'ripple_radius'  => $ripple_radius,
            'icon_shake'     => !empty($settings['icon_shake']) ? '1' : '0',
            'lead_fields'    => is_array($bot_config['chatbot_lead_fields'] ?? null) ? $bot_config['chatbot_lead_fields'] : [],
            'fab_hint_enabled' => $hint_enabled ? '1' : '0',
            'fab_hint'       => $hint_text,
            'fab_hint_position' => (string) ($settings['hint_position'] ?? 'left'),
            'fab_hint_font_size' => (string) ($settings['hint_font_size'] ?? '15'),
            'fab_default_open' => !empty($settings['default_open']) ? '1' : '0',
            'fab_open_delay'   => (string) ($settings['open_delay'] ?? '20'),
            'open_cache_ttl'   => (string) ($settings['open_cache_ttl'] ?? '1440'),
            'popup_transition_duration' => (string) ($settings['popup_transition_duration'] ?? '100'),
            'is_editor'      => $this->is_elementor_editor() ? '1' : '0',
        ];
    }

    private function normalize_icon_setting($icon, string $default_value): array {
        if (is_array($icon) && !empty($icon['value'])) {
            return [
                'value'   => $icon['value'],
                'library' => (string) ($icon['library'] ?? ''),
            ];
        }

        return [
            'value'   => $default_value,
            'library' => 'fa-solid',
        ];
    }

    private function render_icon_html($icon, string $default_value): string {
        $icon = $this->normalize_icon_setting($icon, $default_value);

        ob_start();
        \Elementor\Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);
        $html = trim((string) ob_get_clean());

        if ($html !== '') {
            return $html;
        }

        return '<i class="' . esc_attr($default_value) . '" aria-hidden="true"></i>';
    }

    protected function content_template(): void {
        ?>
        <#
        var widgetId = view.getID();
        var sliderSize = function(value, fallback) {
            if (value && typeof value === 'object' && value.size !== undefined && value.size !== '') {
                return String(value.size);
            }
            if (value !== undefined && value !== null && value !== '') {
                return String(value);
            }
            return fallback;
        };
        var layoutMode = settings.layout_mode === 'box' ? 'box' : 'button';
        var defaultIcon = {
            value: 'fas fa-envelope',
            library: 'fa-solid'
        };
        var defaultCloseIcon = {
            value: 'fas fa-times',
            library: 'fa-solid'
        };
        var defaultSendIcon = {
            value: 'fas fa-paper-plane',
            library: 'fa-solid'
        };
        var renderElementorIcon = function(icon, fallbackClass) {
            var iconData = icon && icon.value ? icon : {
                value: fallbackClass,
                library: 'fa-solid'
            };
            var iconHTML = '';
            if (typeof elementor !== 'undefined' && elementor.helpers && elementor.helpers.renderIcon) {
                try {
                    var renderedIcon = elementor.helpers.renderIcon(view, iconData, {'aria-hidden': true}, 'i', 'object');
                    if (renderedIcon && renderedIcon.rendered) {
                        iconHTML = renderedIcon.value;
                    }
                } catch (e) {
                    iconHTML = '';
                }
            }
            if (!iconHTML && typeof iconData.value === 'string' && iconData.value) {
                iconHTML = '<i class="' + _.escape(iconData.value) + '" aria-hidden="true"></i>';
            }
            return {
                icon: iconData,
                html: iconHTML
            };
        };
        var buttonIcon = settings.button_icon && settings.button_icon.value ? settings.button_icon : defaultIcon;
        var sendIcon = settings.send_icon && settings.send_icon.value ? settings.send_icon : defaultSendIcon;
        var closeIcon = settings.close_icon && settings.close_icon.value ? settings.close_icon : defaultCloseIcon;
        var buttonIconRendered = renderElementorIcon(buttonIcon, 'fas fa-envelope');
        var sendIconRendered = renderElementorIcon(sendIcon, 'fas fa-paper-plane');
        var closeIconRendered = renderElementorIcon(closeIcon, 'fas fa-times');
        var config = {
            chatbot_id: settings.chatbot_id || '',
            widget_id: widgetId,
            layout_mode: layoutMode,
            greeting: settings.greeting || '',
            offline_msg: settings.offline_msg || '',
            avatar: '',
            i18n: {
                title: settings.title || '',
                subtitle: settings.subtitle || '',
                input_placeholder: settings.input_placeholder || '',
                thinking_text: settings.thinking_text || ''
            },
            fab_icon: buttonIconRendered.icon,
            fab_icon_html: buttonIconRendered.html,
            send_icon: sendIconRendered.icon,
            send_icon_html: sendIconRendered.html,
            close_icon: closeIconRendered.icon,
            close_icon_html: closeIconRendered.html,
            ripple_enabled: settings.ripple_enabled ? '1' : '0',
            ripple_color: settings.ripple_color || '',
            ripple_opacity: sliderSize(settings.ripple_opacity, '0.2'),
            ripple_speed: sliderSize(settings.ripple_speed, '1'),
            ripple_radius: sliderSize(settings.ripple_radius, '2.5'),
            icon_shake: settings.icon_shake ? '1' : '0',
            lead_fields: [],
            fab_hint_enabled: settings.hint_enabled ? '1' : '0',
            fab_hint: settings.hint_text || 'Contact Us',
            fab_hint_position: settings.hint_position || 'left',
            fab_hint_font_size: String(settings.hint_font_size || '15'),
            fab_default_open: settings.default_open ? '1' : '0',
            fab_open_delay: String(settings.open_delay || '20'),
            open_cache_ttl: String(settings.open_cache_ttl || '1440'),
            popup_transition_duration: String(settings.popup_transition_duration || '100'),
            is_editor: '1'
        };
        var configJson = JSON.stringify(config);
        var cssVars = [
            '--ripple-color:' + (settings.ripple_color || settings.button_color || '#25b366'),
            '--ripple-opacity:' + config.ripple_opacity,
            '--ripple-speed:' + config.ripple_speed + 's',
            '--ripple-radius:' + config.ripple_radius,
            '--hint-bg:' + (settings.hint_bg || '#ffffff'),
            '--hint-text:' + (settings.hint_text_color || '#333333'),
            '--hint-font-size:' + (settings.hint_font_size || '15') + 'px',
            '--popup-transition-duration:' + config.popup_transition_duration + 'ms',
            '--ai-chatbot-primary:' + (settings.button_color || '#25b366'),
            '--ai-chatbot-popup:' + (settings.popup_color || '#25b366'),
            '--ai-chatbot-button:' + (settings.button_color || '#25b366'),
            '--ai-chatbot-button-hover:' + (settings.button_color || '#25b366')
        ].join(';') + ';';
        #>
        <div id="ai-chatbot-container-{{ widgetId }}"
             class="ai-chatbot-container"
             style="{{ cssVars }}"
             data-widget-id="{{ widgetId }}"
             data-config="{{ configJson }}"
             data-config-hash="{{ encodeURIComponent( configJson ) }}"
             data-layout="{{ layoutMode }}"></div>
        <?php
    }

    private function is_elementor_editor(): bool {
        return isset(\Elementor\Plugin::$instance->editor)
            && method_exists(\Elementor\Plugin::$instance->editor, 'is_edit_mode')
            && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }

    private function build_css_vars(array $config): string {
        $settings = $this->get_settings_for_display();
        $popup_color = $settings['popup_color'] ?: '#25b366';
        $button_color = $settings['button_color'] ?: '#25b366';
        $ripple_color = $settings['ripple_color'] ?: $button_color;
        $hint_bg = $settings['hint_bg'] ?: '#ffffff';
        $hint_text = $settings['hint_text_color'] ?: '#333333';
        $hint_font_size = $settings['hint_font_size'] ?: '15';
        $transition_dur = $config['popup_transition_duration'] ?: '100';

        return implode('', [
            '--ripple-color:' . $ripple_color . ';',
            '--ripple-opacity:' . $config['ripple_opacity'] . ';',
            '--ripple-speed:' . $config['ripple_speed'] . 's;',
            '--ripple-radius:' . $config['ripple_radius'] . ';',
            '--hint-bg:' . $hint_bg . ';',
            '--hint-text:' . $hint_text . ';',
            '--hint-font-size:' . $hint_font_size . 'px;',
            '--popup-transition-duration:' . $transition_dur . 'ms;',
            '--ai-chatbot-primary:' . $button_color . ';',
            '--ai-chatbot-popup:' . $popup_color . ';',
            '--ai-chatbot-button:' . $button_color . ';',
            '--ai-chatbot-button-hover:' . $button_color . ';',
        ]);
    }

    private function slider_size($value, string $default): string {
        if (is_array($value) && isset($value['size']) && $value['size'] !== '') {
            return (string) $value['size'];
        }
        if (is_scalar($value) && $value !== '') {
            return (string) $value;
        }
        return $default;
    }

    private function get_chatbot_options(): array {
        $bots = get_posts([
            'post_type'      => 'ai_chatbot',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);

        $options = ['' => __('Select a Chatbot', 'wp-aigent')];
        foreach ($bots as $bot) {
            $options[$bot->ID] = $bot->post_title;
        }
        return $options;
    }
}
