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

    protected function register_controls(): void {
        $this->start_controls_section('section_content', [
            'label' => __('Chatbot Settings', 'wp-aigent'),
        ]);

        $this->add_control('chatbot_id', [
            'label'   => __('Select Chatbot', 'wp-aigent'),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => $this->get_chatbot_options(),
            'default' => '',
        ]);

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $chatbot_id = (int) ($settings['chatbot_id'] ?? 0);

        if (empty($chatbot_id) || get_post_type($chatbot_id) !== 'ai_chatbot') {
            echo '<div class="ai-chat-error">'
                . __('Please select a chatbot in the widget settings.', 'wp-aigent')
                . '</div>';
            return;
        }

        // Delegate rendering to shared method
        echo WP_AIGent_Plugin::render_chatbot_html($chatbot_id, $this->get_id());
    }

    private function get_chatbot_options(): array {
        $bots = get_posts([
            'post_type'      => 'ai_chatbot',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ]);

        $options = ['' => __('— Select a Chatbot —', 'wp-aigent')];
        foreach ($bots as $bot) {
            $options[$bot->ID] = $bot->post_title;
        }
        return $options;
    }

}
