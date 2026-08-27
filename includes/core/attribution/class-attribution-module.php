<?php
defined('ABSPATH') || exit;

/** Registers the shared browser state and optional attribution collector. */
class WP_AIGent_Attribution_Module {

    public function init(): void {
        add_action('wp_enqueue_scripts', [$this, 'register_assets'], 5);
    }

    public function register_assets(): void {
        $browser_state_path = 'assets/modules/identity/js/browser-state.js';
        if (!wp_script_is('wp-aigent-browser-state', 'registered')) {
            wp_register_script(
                'wp-aigent-browser-state',
                WP_AIGENT_URL . $browser_state_path,
                [],
                $this->asset_version($browser_state_path),
                ['strategy' => 'defer', 'in_footer' => true]
            );
        }

        if ((string) WP_AIGent_Attribution_Settings::get('tracking_enabled') !== '1') {
            return;
        }

        $attribution_path = 'assets/modules/attribution/js/tracker.js';
        wp_enqueue_script(
            'wp-aigent-attribution',
            WP_AIGENT_URL . $attribution_path,
            ['wp-aigent-browser-state'],
            $this->asset_version($attribution_path),
            ['strategy' => 'defer', 'in_footer' => true]
        );
        wp_localize_script('wp-aigent-attribution', 'wpAIgentAttributionConfig', [
            'retentionDays'        => (int) WP_AIGent_Attribution_Settings::get('retention_days'),
            'journeyLimit'         => (int) WP_AIGent_Attribution_Settings::get('journey_limit'),
            'excludedPathPrefixes' => WP_AIGent_Attribution_Settings::excluded_path_prefixes(),
            'dataLayerEnabled'     => (string) WP_AIGent_Attribution_Settings::get('data_layer_enabled') === '1',
        ]);
    }

    private function asset_version(string $path): string {
        $mtime = is_readable(WP_AIGENT_PATH . $path) ? filemtime(WP_AIGENT_PATH . $path) : false;
        return $mtime ? (string) $mtime : WP_AIGENT_VERSION;
    }
}
