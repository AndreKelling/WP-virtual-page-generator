<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. With these methods, you can check values, strings, arrays, etc.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can register functions for
| use within your test files.
|
*/

function mock_wp_functions() {
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/var/www/html/');
    }

    if (!function_exists('add_action')) {
        function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
    }
    if (!function_exists('add_filter')) {
        function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {}
    }
    if (!function_exists('register_activation_hook')) {
        function register_activation_hook($file, $callback) {}
    }
    if (!function_exists('register_deactivation_hook')) {
        function register_deactivation_hook($file, $callback) {}
    }
    if (!function_exists('plugin_dir_path')) {
        function plugin_dir_path($file) {
            return dirname($file) . '/';
        }
    }
    if (!function_exists('flush_rewrite_rules')) {
        function flush_rewrite_rules($hard = true) {}
    }
    if (!function_exists('get_current_screen')) {
        function get_current_screen() {
            return null;
        }
    }
    if (!class_exists('WP_Sitemaps_Provider')) {
        class WP_Sitemaps_Provider {
            public $name;
            public $object_type;
        }
    }
    if (!class_exists('WP_REST_Search_Handler')) {
        class WP_REST_Search_Handler {
            const RESULT_IDS = 'ids';
            const RESULT_TOTAL = 'total';
            public $type;
            public $subtypes;
        }
    }
    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request implements \ArrayAccess {
            private $params = [];
            public function __construct($method, $route) {}
            public function set_param($key, $value) { $this->params[$key] = $value; }
            public function offsetExists($offset): bool { return isset($this->params[$offset]); }
            public function offsetGet($offset): mixed { return $this->params[$offset] ?? null; }
            public function offsetSet($offset, $value): void { $this->params[$offset] = $value; }
            public function offsetUnset($offset): void { unset($this->params[$offset]); }
        }
    }
    if (!class_exists('WP_Post')) {
        class WP_Post {
            public $ID;
            public $post_title;
            public $post_name;
            public $post_type;
            public $post_status;
            public $post_modified_gmt;
            public $post_content;
            public $post_excerpt;
        }
    }

    if (!function_exists('esc_url')) {
        function esc_url($url) {
            return $url;
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('home_url')) {
        function home_url($path = '') {
            return 'http://example.com' . $path;
        }
    }

    if (!function_exists('sanitize_title')) {
        function sanitize_title($title) {
            return strtolower(str_replace(' ', '-', $title));
        }
    }

    if (!function_exists('get_post')) {
        function get_post($id) {
            global $mock_posts;
            if (!isset($mock_posts)) return null;
            foreach ($mock_posts as $post) {
                if ($post->ID == $id) return $post;
            }
            return null;
        }
    }

    if (!function_exists('get_posts')) {
        function get_posts($args) {
            global $mock_posts;
            if (!isset($mock_posts)) return [];
            
            $type = $args['post_type'] ?? 'post';
            $name = $args['name'] ?? null;
            $title = $args['title'] ?? null;
            return array_values(array_filter($mock_posts, function($post) use ($type, $name, $title) {
                if (($post->post_type ?? 'post') !== $type) {
                    return false;
                }
                if ($name !== null && ($post->post_name ?? '') !== $name) {
                    return false;
                }
                if ($title !== null && ($post->post_title ?? '') !== $title) {
                    return false;
                }
                return true;
            }));
        }
    }

    if (!function_exists('get_post_meta')) {
        function get_post_meta($post_id, $key = '', $single = false) {
            global $mock_post_meta;
            if (isset($mock_post_meta[$post_id][$key])) {
                return $mock_post_meta[$post_id][$key];
            }
            return $single ? '' : [];
        }
    }

    if (!function_exists('update_post_meta')) {
        function update_post_meta($post_id, $key, $value, $prev_value = '') {
            global $mock_post_meta;
            if (!isset($mock_post_meta[$post_id])) {
                $mock_post_meta[$post_id] = [];
            }
            $mock_post_meta[$post_id][$key] = $value;
            return true;
        }
    }

    if (!function_exists('wp_editor')) {
        function wp_editor($content, $editor_id, $settings = []) {
            echo '<textarea id="' . esc_attr($editor_id) . '" name="' . esc_attr($settings['textarea_name'] ?? $editor_id) . '">' . esc_html($content) . '</textarea>';
        }
    }

    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($data) {
            return $data;
        }
    }

    if (!function_exists('wp_unslash')) {
        function wp_unslash($data) {
            return $data;
        }
    }

    if (!function_exists('wp_nonce_field')) {
        function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
            $html = '<input type="hidden" name="' . esc_attr($name) . '" value="mock_nonce" />';
            if ($echo) {
                echo $html;
            }
            return $html;
        }
    }

    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($nonce, $action = -1) {
            return true;
        }
    }

    if (!function_exists('current_user_can')) {
        function current_user_can($capability, ...$args) {
            return true;
        }
    }

    if (!function_exists('current_theme_supports')) {
        function current_theme_supports($feature, ...$args) {
            global $mock_theme_supports;
            return !empty($mock_theme_supports[$feature]);
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('esc_textarea')) {
        function esc_textarea($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('sanitize_textarea_field')) {
        function sanitize_textarea_field($str) {
            return trim(strip_tags((string) $str));
        }
    }

    if (!function_exists('wp_strip_all_tags')) {
        function wp_strip_all_tags($text, $remove_breaks = false) {
            $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text);
            $text = strip_tags($text);
            if ($remove_breaks) {
                $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
            }
            return trim($text);
        }
    }

    if (!function_exists('wp_trim_words')) {
        function wp_trim_words($text, $num_words = 55, $more = null) {
            if (null === $more) {
                $more = '&hellip;';
            }
            $words = preg_split("/[\n\r\t ]+/", (string) $text, $num_words + 1, PREG_SPLIT_NO_EMPTY);
            if ($words && count($words) > $num_words) {
                array_pop($words);
                $text = implode(' ', $words) . $more;
            } elseif ($words) {
                $text = implode(' ', $words);
            } else {
                $text = '';
            }
            return $text;
        }
    }

    if (!function_exists('mysql2date')) {
        function mysql2date($format, $date, $translate = true) {
            if (empty($date) || $date === '0000-00-00 00:00:00') {
                return false;
            }
            if ($format === 'G' || $format === 'U') {
                return strtotime($date . ' GMT');
            }
            return gmdate($format, strtotime($date . ' GMT'));
        }
    }

    if (!function_exists('wp_get_attachment_image')) {
        function wp_get_attachment_image($attachment_id, $size = 'thumbnail', $icon = false, $attr = '') {
            return '<img src="http://example.com/wp-content/uploads/image-' . $attachment_id . '.jpg" alt="" />';
        }
    }

    if (!function_exists('add_rewrite_rule')) {
        function add_rewrite_rule($regex, $query, $after = 'bottom') {
            global $registered_rules;
            $registered_rules[] = ['regex' => $regex, 'query' => $query, 'after' => $after];
        }
    }

    if (!function_exists('wp_count_posts')) {
        function wp_count_posts($type) {
            global $mock_counts;
            return (object) ($mock_counts[$type] ?? ['publish' => 0]);
        }
    }

    if (!function_exists('get_query_var')) {
        function get_query_var($var_name, $default = '') {
            global $mock_query_vars;
            return $mock_query_vars[$var_name] ?? $default;
        }
    }

    if (!function_exists('locate_template')) {
        function locate_template($template_names, $load = false, $require_once = true, $args = []) {
            global $mock_located_templates;
            if (!empty($mock_located_templates)) {
                $template_names = (array) $template_names;
                foreach ($template_names as $template_name) {
                    if (isset($mock_located_templates[$template_name])) {
                        return $mock_located_templates[$template_name];
                    }
                }
            }
            return '';
        }
    }

    if (!function_exists('wp_register_script')) {
        function wp_register_script($handle, $src, $deps = [], $ver = false, $in_footer = false) {
            global $mock_registered_scripts;
            $mock_registered_scripts[$handle] = [
                'src' => $src,
                'deps' => $deps,
                'ver' => $ver,
            ];
            return true;
        }
    }

    if (!function_exists('plugins_url')) {
        function plugins_url($path = '', $plugin = '') {
            return 'http://example.com/wp-content/plugins/virtual-page-generator/' . ltrim($path, '/');
        }
    }

    if (!function_exists('register_block_type')) {
        function register_block_type($block_type, $args = []) {
            global $mock_registered_blocks;
            $mock_registered_blocks[$block_type] = $args;
            return true;
        }
    }

    if (!function_exists('register_post_type')) {
        function register_post_type($post_type, $args = []) {
            global $mock_registered_post_types;
            $mock_registered_post_types[$post_type] = $args;
            return true;
        }
    }
}

mock_wp_functions();
