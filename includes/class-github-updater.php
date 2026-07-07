<?php
/**
 * WP Plugin GitHub Updater
 *
 * 通用 GitHub Release 自动更新器，适用于任何 WordPress 插件。
 * 自动读取插件文件头的 Update URI，从 GitHub Releases API 获取最新版本。
 *
 * ── 使用方法 ─────────────────────────────────────────────────────
 *
 * 1. 在插件主文件头添加 Update URI，指向 GitHub 仓库地址：
 *    * Update URI: https://github.com/username/repo
 *
 *    也支持 api.github.com 格式：
 *    * Update URI: https://api.github.com/repos/username/repo
 *
 * 2. 在插件主文件中加载并实例化：
 *    require_once __DIR__ . '/includes/class-github-updater.php';
 *    new WP_Plugin_Github_Updater(__FILE__);
 *
 * ── 要求 ─────────────────────────────────────────────────────────
 *
 * - GitHub 仓库必须包含 Release，且 tag 名称为 v1.0.0 或 1.0.0 格式。
 * - 插件主文件必须包含 Plugin Name、Version、Update URI 头部信息。
 *
 * ─────────────────────────────────────────────────────────────────
 */

defined('ABSPATH') || exit;

class WP_Plugin_Github_Updater {

    private string $slug;
    private string $plugin_file;
    private string $main_file_path;
    private string $github_repo;
    private string $github_api_url;
    private string $requires_php;
    private string $requires_wp;
    private string $plugin_name;
    private string $author_name;

    /**
     * @param string $main_file_path 插件主文件的绝对路径，传入 __FILE__
     */
    public function __construct(string $main_file_path) {
        $this->main_file_path = $main_file_path;
        $this->plugin_file    = plugin_basename($main_file_path);
        $this->slug           = dirname($this->plugin_file);

        // 从插件头部读取元数据
        $headers = get_file_data($main_file_path, array(
            'RequiresPHP' => 'Requires PHP',
            'RequiresWP'  => 'Requires at least',
            'UpdateURI'   => 'Update URI',
            'PluginName'  => 'Plugin Name',
            'Author'      => 'Author',
        ));

        $this->requires_php = $headers['RequiresPHP'] ?: '7.4';
        $this->requires_wp  = $headers['RequiresWP'] ?: '6.0';
        $this->plugin_name  = $headers['PluginName'] ?: $this->slug;
        $this->author_name  = $headers['Author'] ?: '';

        // 解析仓库路径
        $this->github_repo   = $this->parse_repo_from_uri($headers['UpdateURI']);
        $this->github_api_url = $this->github_repo
            ? "https://api.github.com/repos/{$this->github_repo}/releases/latest"
            : '';

        // 挂载更新 hooks
        add_filter('update_plugins_api.github.com', array($this, 'check_update'), 10, 4);
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'normalize_github_zip_folder'), 10, 4);
    }

    // ──────────────────────────────────── WordPress 钩子 ────────────────────────────────────

    /**
     * 检查更新回调。
     *
     * @see https://developer.wordpress.org/reference/hooks/update_plugins_hostname/
     */
    public function check_update($update, array $plugin_data, string $plugin_file, array $locales) {
        if ($this->plugin_file !== $plugin_file) {
            return $update;
        }

        $release = $this->fetch_release_data();
        if (!$release) {
            return $update;
        }

        return array(
            'slug'         => $this->slug,
            'version'      => $release['version'],
            'url'          => $release['url'],
            'package'      => $release['download_url'],
            'requires_php' => $this->requires_php,
            'requires'     => $this->requires_wp,
            'autoupdate'   => true,
        );
    }

    /**
     * 插件详情弹窗回调。
     */
    public function plugin_info($result, string $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || $this->slug !== $args->slug) {
            return $result;
        }

        $release = $this->fetch_release_data();
        if (!$release) {
            return $result;
        }

        return (object) array(
            'name'          => $this->plugin_name,
            'slug'          => $this->slug,
            'version'       => $release['version'],
            'author'        => $this->author_name,
            'homepage'      => $release['url'],
            'requires_php'  => $this->requires_php,
            'requires'      => $this->requires_wp,
            'download_link' => $release['download_url'],
            'sections'      => array(
                'description' => '<p>Managed via GitHub Releases.</p>',
                'changelog'   => '<div>' . $release['changelog'] . '</div>',
            ),
        );
    }

    /**
     * GitHub 下载的 zip 解压后目录名是 {repo}-{commit-hash}，
     * 修正为插件目录名，避免 WordPress 无法识别。
     */
    public function normalize_github_zip_folder($source, $remote_source, $upgrader, $hook_extra = null) {
        $is_match = false;

        if (isset($hook_extra['plugin']) && $this->plugin_file === $hook_extra['plugin']) {
            $is_match = true;
        } elseif (isset($_GET['plugin']) && strpos($_GET['plugin'], $this->slug) !== false) {
            $is_match = true;
        }

        if (!$is_match) {
            return $source;
        }

        // 确保 WP_Filesystem 可用
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        $corrected = trailingslashit($remote_source) . $this->slug . '/';

        if ($source === $corrected || !$wp_filesystem) {
            return $source;
        }

        $wp_filesystem->move($source, $corrected, true);
        return $corrected;
    }

    // ──────────────────────────────────── 内部方法 ────────────────────────────────────

    /**
     * 从 Update URI 解析出 owner/repo 路径。
     * 兼容两种格式：https://github.com/owner/repo 和 https://api.github.com/repos/owner/repo
     */
    private function parse_repo_from_uri(string $update_uri): string {
        if (empty($update_uri)) {
            return '';
        }

        $patterns = array(
            'https://api.github.com/repos/',
            'https://github.com/',
            'http://api.github.com/repos/',
            'http://github.com/',
        );

        $path = trim(str_replace($patterns, '', $update_uri), '/');
        // 验证格式：至少包含一个 /
        if (substr_count($path, '/') < 1) {
            return '';
        }

        return $path;
    }

    /**
     * 获取 GitHub Release 数据，带简单缓存。
     *
     * - 缓存 1 小时，期间直接返回缓存，不请求 GitHub API
     * - API 失败时设 1 小时 backoff，期间返回旧缓存
     * - 既无缓存又失败才返回 null
     */
    private function fetch_release_data(): ?array {
        if (empty($this->github_api_url)) {
            return null;
        }

        $hash        = md5($this->github_repo);
        $cache_key   = 'wp_github_updater_' . $hash;
        $backoff_key = $cache_key . '_backoff';
        $cached      = get_transient($cache_key);
        $backoff     = get_transient($backoff_key);

        // 缓存命中 or backoff 期内 → 直接返回
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }
        if (false !== $backoff) {
            return null;
        }

        // ── 缓存过期且没有 backoff → 请求 API ──

        $response = wp_remote_get($this->github_api_url, array(
            'timeout'    => 15,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
        ));

        if (is_wp_error($response)) {
            set_transient($backoff_key, 1, HOUR_IN_SECONDS);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            set_transient($backoff_key, 1, HOUR_IN_SECONDS);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['tag_name'])) {
            set_transient($backoff_key, 1, HOUR_IN_SECONDS);
            return null;
        }

        $release = array(
            'version'      => ltrim($data['tag_name'], 'v'),
            'download_url' => $data['zipball_url'] ?? '',
            'url'          => $data['html_url'] ?? '',
            'changelog'    => !empty($data['body'])
                ? nl2br(esc_html($data['body']))
                : 'No changelog provided.',
        );

        set_transient($cache_key, $release, HOUR_IN_SECONDS);

        return $release;
    }
}
