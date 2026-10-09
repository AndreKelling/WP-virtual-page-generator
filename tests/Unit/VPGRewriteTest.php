<?php

require_once __DIR__ . '/../Pest.php';
require_once __DIR__ . '/../../includes/class-vpg-rewrite.php';

beforeEach(function () {
    mock_wp_functions();
    global $registered_rules, $mock_posts;
    $registered_rules = [];
    $mock_posts = [];
});

test('get_virtual_url generates correct URL with placeholders', function () {
    $rewrite = new VPG_Rewrite();

    $template = new WP_Post();
    $template->post_title = '{{service}} in {{location}}';

    $url = $rewrite->get_virtual_url($template, 'mowing', 'berlin');

    expect($url)->toBe('http://example.com/mowing-in-berlin/');
});

test('get_virtual_url appends missing placeholders', function () {
    $rewrite = new VPG_Rewrite();

    $template = new WP_Post();
    $template->post_title = 'Special Offer';

    $url = $rewrite->get_virtual_url($template, 'mowing', 'berlin');

    expect($url)->toBe('http://example.com/special-offer/mowing/berlin/');
});

test('get_virtual_url handles slashes in title', function () {
    $rewrite = new VPG_Rewrite();

    $template = new WP_Post();
    $template->post_title = 'services/{{service}}/at/{{location}}';

    $url = $rewrite->get_virtual_url($template, 'mowing', 'berlin');

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

    $rewrite = new VPG_Rewrite();
    $rewrite->add_rewrite_rules();

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

    $rewrite = new VPG_Rewrite();
    $rewrite->add_query_vars([]);
    $rewrite->add_rewrite_rules();

    expect($registered_rules)->toHaveCount(1);
    expect($registered_rules[0]['regex'])->toBe('^([^/]+)\-offers\-([^/]+)/?$');
    // Location is first, Service is second
    expect($registered_rules[0]['query'])->toContain('vpg_location_slug=$matches[1]')
                                         ->toContain('vpg_service_slug=$matches[2]');
});

test('query vars are added correctly', function () {
    $rewrite = new VPG_Rewrite();
    $vars = $rewrite->add_query_vars([]);

    expect($vars)->toContain('vpg_service_slug')
                 ->toContain('vpg_location_slug')
                 ->toContain('vpg_template_id');
});
