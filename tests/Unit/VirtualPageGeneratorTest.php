<?php

use VirtualPageGenerator as Generator;

require_once __DIR__ . '/../Pest.php';
require_once __DIR__ . '/../../virtual-page-generator.php';

beforeEach(function () {
    mock_wp_functions();
    global $registered_rules, $mock_posts, $mock_counts;
    $registered_rules = [];
    $mock_posts = [];
    $mock_counts = [];
});

test('get_virtual_url generates correct URL with placeholders', function () {
    $generator = Generator::get_instance();

    $template = new WP_Post();
    $template->post_title = '{{service}} in {{location}}';

    $url = $generator->get_virtual_url($template, 'mowing', 'berlin');

    expect($url)->toBe('http://example.com/mowing-in-berlin/');
});

test('get_virtual_url appends missing placeholders', function () {
    $generator = Generator::get_instance();

    $template = new WP_Post();
    $template->post_title = 'Special Offer';

    $url = $generator->get_virtual_url($template, 'mowing', 'berlin');

    expect($url)->toBe('http://example.com/special-offer/mowing/berlin/');
});

test('get_virtual_url handles slashes in title', function () {
    $generator = Generator::get_instance();

    $template = new WP_Post();
    $template->post_title = 'services/{{service}}/at/{{location}}';

    $url = $generator->get_virtual_url($template, 'mowing', 'berlin');

    expect($url)->toBe('http://example.com/services/mowing/at/berlin/');
});

test('rewrite rules are generated correctly', function () {
    global $mock_posts, $registered_rules;

    $template = new WP_Post();
    $template->ID = 123;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_type = 'vpg_template';
    $template->post_status = 'publish';

    $mock_posts = [$template];

    $generator = Generator::get_instance();
    $generator->add_rewrite_rules();

    expect($registered_rules)->toHaveCount(1);
    expect($registered_rules[0]['regex'])->toBe('^([^/]+)\-in\-([^/]+)/?$');
    expect($registered_rules[0]['query'])->toContain('vpg_template_id=123')
                                         ->toContain('vpg_service_slug=$matches[1]')
                                         ->toContain('vpg_location_slug=$matches[2]');
});

test('rewrite rules handle reverse order of placeholders', function () {
    global $mock_posts, $registered_rules;

    $template = new WP_Post();
    $template->ID = 456;
    $template->post_title = '{{location}} offers {{service}}';
    $template->post_type = 'vpg_template';
    $template->post_status = 'publish';

    $mock_posts = [$template];

    $generator = Generator::get_instance();
    $generator->add_rewrite_rules();

    expect($registered_rules)->toHaveCount(1);
    expect($registered_rules[0]['regex'])->toBe('^([^/]+)\-offers\-([^/]+)/?$');
    // Location is first, Service is second
    expect($registered_rules[0]['query'])->toContain('vpg_location_slug=$matches[1]')
                                         ->toContain('vpg_service_slug=$matches[2]');
});

test('query vars are added correctly', function () {
    $generator = Generator::get_instance();
    $vars = $generator->add_query_vars([]);

    expect($vars)->toContain('vpg_service_slug')
                 ->toContain('vpg_location_slug')
                 ->toContain('vpg_template_id');
});

test('render_service_list_block generates links with replaced template title', function () {
    global $mock_posts;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_type = 'vpg_template';

    $location = new WP_Post();
    $location->ID = 20;
    $location->post_title = 'Berlin';
    $location->post_name = 'berlin';
    $location->post_type = 'vpg_location';

    $service = new WP_Post();
    $service->ID = 30;
    $service->post_title = 'Mowing';
    $service->post_name = 'mowing';
    $service->post_type = 'vpg_service';

    $mock_posts = [$template, $location, $service];

    $generator = Generator::get_instance();
    $html = $generator->render_service_list_block([
        'locationId' => '20',
        'templateId' => '10',
    ]);

    expect($html)->toBe('<ul class="vpg-service-list"><li><a href="http://example.com/mowing-in-berlin/">Mowing in Berlin</a></li></ul>');
});

test('add_to_tsf_sitemap adds virtual urls with correct lastmod', function () {
    global $mock_posts;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_type = 'vpg_template';
    $template->post_status = 'publish';
    $template->post_modified_gmt = '2023-01-01 10:00:00';

    $location = new WP_Post();
    $location->ID = 20;
    $location->post_title = 'Berlin';
    $location->post_name = 'berlin';
    $location->post_type = 'vpg_location';
    $location->post_modified_gmt = '2023-01-05 12:00:00';

    $service = new WP_Post();
    $service->ID = 30;
    $service->post_title = 'Mowing';
    $service->post_name = 'mowing';
    $service->post_type = 'vpg_service';
    $service->post_modified_gmt = '2023-01-02 08:00:00';

    $mock_posts = [$template, $location, $service];

    $generator = Generator::get_instance();
    $sitemap = $generator->add_to_tsf_sitemap([], []);

    expect($sitemap)->toHaveKey('http://example.com/mowing-in-berlin/');
    expect($sitemap['http://example.com/mowing-in-berlin/']['lastmod'])->toBe('2023-01-05 12:00:00');
});
