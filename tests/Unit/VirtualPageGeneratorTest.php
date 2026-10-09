<?php

use VirtualPageGenerator as Generator;

require_once __DIR__ . '/../Pest.php';

// Include classes in dependency order (without WordPress bootstrap)
require_once __DIR__ . '/../../includes/class-vpg-admin.php';
require_once __DIR__ . '/../../includes/class-vpg-rewrite.php';
require_once __DIR__ . '/../../includes/class-vpg-page-data.php';
require_once __DIR__ . '/../../includes/class-virtual-page-generator.php';

beforeEach(function () {
    mock_wp_functions();
    global $registered_rules, $mock_posts, $mock_counts, $mock_query_vars, $mock_located_templates, $mock_post_meta;
    $registered_rules = [];
    $mock_posts = [];
    $mock_counts = [];
    $mock_query_vars = [];
    $mock_located_templates = [];
    $mock_post_meta = [];
    Generator::reset_instance();
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

test('handle_virtual_page returns theme template if located', function () {
    global $mock_query_vars, $mock_located_templates;

    $mock_query_vars = [
        'vpg_template_id' => '123',
    ];
    $mock_located_templates = [
        'virtual-page.php' => '/path/to/theme/virtual-page.php',
    ];

    $generator = Generator::get_instance();
    $template = $generator->handle_virtual_page('/original/template.php');

    expect($template)->toBe('/path/to/theme/virtual-page.php');
});

test('handle_virtual_page falls back to plugin template if no theme template', function () {
    global $mock_query_vars;

    $mock_query_vars = [
        'vpg_service_slug' => 'mowing',
        'vpg_location_slug' => 'berlin',
    ];

    $generator = Generator::get_instance();
    $template = $generator->handle_virtual_page('/original/template.php');

    expect($template)->toContain('templates/virtual-page.php');
});

test('handle_virtual_page returns original template if query vars are missing', function () {
    $generator = Generator::get_instance();
    $template = $generator->handle_virtual_page('/original/template.php');

    expect($template)->toBe('/original/template.php');
});

test('get_page_data delegates to VPG_Page_Data and exposes helper functions', function () {
    global $mock_posts, $mock_query_vars, $mock_post_meta;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_content = 'We offer {{service}} in {{location}}.';
    $template->post_type = 'vpg_template';
    $template->post_status = 'publish';

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
    $mock_query_vars = [
        'vpg_service_slug' => 'mowing',
        'vpg_location_slug' => 'berlin',
        'vpg_template_id' => '10',
    ];
    $mock_post_meta = [];

    $generator = Generator::get_instance();
    $data = $generator->get_page_data();

    expect($data)->not->toBeNull();
    expect($data['title'])->toBe('Mowing in Berlin');

    // Test helper functions delegate correctly
    expect(vpg_get_page_data())->toBe($data);
    expect(vpg_get_virtual_page_data())->toBe($data);
});

test('set_page_data allows manual override', function () {
    $generator = Generator::get_instance();
    $customData = ['custom' => 'value'];
    
    $generator->set_page_data($customData);
    expect($generator->get_page_data())->toBe($customData);
});
