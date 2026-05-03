<?php
/**
 * Minimal WordPress function stubs for unit testing.
 *
 * These provide basic implementations of the WordPress functions that
 * helpers and DTOs call directly. For hook functions (add_action,
 * add_filter, do_action, apply_filters with side effects, etc.) use
 * Brain Monkey inside the test, NOT a stub here.
 *
 * Patchwork (loaded by Brain Monkey) cannot redefine functions that
 * already exist when it boots — so any stub here takes precedence
 * over a Brain Monkey expectation for the same name.
 */

// ── Sanitization functions ──────────────────────────────────────

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key));
    }
}

if (!function_exists('absint')) {
    function absint($maybeint): int {
        return abs((int) $maybeint);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

// ── Escaping functions ──────────────────────────────────────────

if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

// ── Site / URL functions ────────────────────────────────────────
// Backed by $GLOBALS['_test_wp_url_base'] so tests can override the host.

if (!function_exists('_test_wp_url_base')) {
    function _test_wp_url_base(): string {
        return $GLOBALS['_test_wp_url_base'] ?? 'http://localhost';
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = '', ?string $scheme = null): string {
        $base = _test_wp_url_base();
        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = '', ?string $scheme = null): string {
        return site_url($path, $scheme);
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $s): string {
        return rtrim($s, '/');
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $s): string {
        return rtrim($s, '/') . '/';
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type, $gmt = 0) {
        return $type === 'timestamp' || $type === 'U' ? time() : gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1) {
        return parse_url($url, $component);
    }
}

// ── i18n functions ──────────────────────────────────────────────

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e(string $text, string $domain = 'default'): void {
        echo $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string {
        return esc_html($text);
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = 'default'): void {
        echo esc_html($text);
    }
}

// ── Utility functions ───────────────────────────────────────────

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, array $defaults = []): array {
        if (is_object($args)) {
            $parsed = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed = $args;
        } else {
            parse_str((string) $args, $parsed);
        }
        return array_merge($defaults, $parsed);
    }
}

if (!function_exists('did_action')) {
    function did_action(string $hook_name): int {
        return 0;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook_name, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook_name, ...$args): void {
        // No-op in unit tests. Use Brain Monkey if you need to assert.
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

// ── Options API (backed by $GLOBALS so tests can set/reset state) ──

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        $store = $GLOBALS['_test_wp_options'] ?? [];
        return array_key_exists($option, $store) ? $store[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, $value, $autoload = null): bool {
        if (!isset($GLOBALS['_test_wp_options'])) {
            $GLOBALS['_test_wp_options'] = [];
        }
        $GLOBALS['_test_wp_options'][$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $option): bool {
        if (isset($GLOBALS['_test_wp_options'][$option])) {
            unset($GLOBALS['_test_wp_options'][$option]);
        }
        return true;
    }
}

// ── Salts (used by Crypto class via wp_salt) ────────────────────

if (!function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string {
        // Deterministic per-scheme salt for tests. Real WP returns long
        // random strings derived from wp-config.php constants.
        return 'test-salt-for-' . $scheme;
    }
}
