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
            return array_values(array_filter($mock_posts, function($post) use ($type) {
                return ($post->post_type ?? 'post') === $type;
            }));
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
}

mock_wp_functions();
