<?php

use VirtualPageGenerator as Generator;

require_once __DIR__ . '/../Pest.php';
require_once __DIR__ . '/../../virtual-page-generator.php';

beforeEach(function () {
    mock_wp_functions();
    global $registered_rules, $mock_posts, $mock_counts, $mock_query_vars, $mock_located_templates, $mock_post_meta;
    $registered_rules = [];
    $mock_posts = [];
    $mock_counts = [];
    $mock_query_vars = [];
    $mock_located_templates = [];
    $mock_post_meta = [];
    Generator::get_instance()->set_page_data(null);
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

test('get_page_data returns structured data with replaced placeholders including location_text, location_for_service_text, and service_text', function () {
    global $mock_posts, $mock_query_vars, $mock_post_meta;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_content = 'We offer {{service}} in {{location}}. Location info: {{location_text}}. Location details: {{location_for_service_text}}. Service info: {{service_text}}. Image: {{service_image}}';
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
    $mock_post_meta = [
        20 => [
            '_vpg_location_text' => '<p>Welcome to Berlin.</p>',
            '_vpg_location_for_service_text_30' => '<p>Best mowing team in Berlin.</p>',
        ],
        30 => [
            '_vpg_service_image' => '99',
            '_vpg_service_text' => '<p>Comprehensive professional mowing services.</p>',
        ],
    ];

    $generator = Generator::get_instance();
    $data = $generator->get_page_data();

    expect($data)->not->toBeNull();
    expect($data['service'])->toBe($service);
    expect($data['location'])->toBe($location);
    expect($data['template'])->toBe($template);
    expect($data['vpg_template'])->toBe($template);
    expect($data['title'])->toBe('Mowing in Berlin');
    expect($data['location_text'])->toBe('<p>Welcome to Berlin.</p>');
    expect($data['location_for_service_text'])->toBe('<p>Best mowing team in Berlin.</p>');
    expect($data['service_text'])->toBe('<p>Comprehensive professional mowing services.</p>');
    expect($data['service_image_id'])->toBe(99);
    expect($data['content'])->toContain('We offer Mowing in Berlin.')
                            ->toContain('<p>Welcome to Berlin.</p>')
                            ->toContain('<p>Best mowing team in Berlin.</p>')
                            ->toContain('<p>Comprehensive professional mowing services.</p>')
                            ->toContain('http://example.com/wp-content/uploads/image-99.jpg');

    // Test helper function and caching
    expect(vpg_get_page_data())->toBe($data);
    expect(vpg_get_virtual_page_data())->toBe($data);
});

test('render_service_text_meta_box renders editor with saved service_text', function () {
    global $mock_post_meta;

    $service = new WP_Post();
    $service->ID = 55;
    $service->post_title = 'Tree Care';

    $mock_post_meta = [
        55 => ['_vpg_service_text' => 'Quality tree care services.'],
    ];

    $generator = Generator::get_instance();
    ob_start();
    $generator->render_service_text_meta_box($service);
    $output = ob_get_clean();

    expect($output)->toContain('name="service_text"')
                   ->toContain('Quality tree care services.');
});

test('save_service_meta saves service_text and service_image_id', function () {
    global $mock_post_meta;

    $_POST = [
        'vpg_service_nonce' => 'valid_nonce',
        'vpg_service_image_id' => '105',
        'service_text' => '<p>New lawn trimming text</p>',
    ];

    $generator = Generator::get_instance();
    $generator->save_service_meta(42);

    expect($mock_post_meta[42]['_vpg_service_image'])->toBe(105);
    expect($mock_post_meta[42]['_vpg_service_text'])->toBe('<p>New lawn trimming text</p>');
    expect($mock_post_meta[42]['service_text'])->toBe('<p>New lawn trimming text</p>');

    $_POST = [];
});

test('save_location_meta saves location_text and location_for_service_text', function () {
    global $mock_post_meta;

    $_POST = [
        'vpg_location_nonce' => 'valid_nonce',
        'location_text' => '<p>Welcome to Berlin.</p>',
        'vpg_location_for_service_text' => [
            30 => '<p>Best mowing service in Berlin.</p>',
            31 => '<p>Expert pruning in Berlin.</p>',
        ],
    ];

    $generator = Generator::get_instance();
    $generator->save_location_meta(20);

    expect($mock_post_meta[20]['_vpg_location_text'])->toBe('<p>Welcome to Berlin.</p>');
    expect($mock_post_meta[20]['location_text'])->toBe('<p>Welcome to Berlin.</p>');
    expect($mock_post_meta[20]['_vpg_location_for_service_text_30'])->toBe('<p>Best mowing service in Berlin.</p>');
    expect($mock_post_meta[20]['_vpg_location_for_service_text_31'])->toBe('<p>Expert pruning in Berlin.</p>');

    $_POST = [];
});

test('render_location_text_meta_box renders editor with saved location_text', function () {
    global $mock_post_meta;

    $location = new WP_Post();
    $location->ID = 20;
    $location->post_title = 'Berlin';

    $mock_post_meta = [
        20 => ['_vpg_location_text' => '<p>Welcome to Berlin.</p>'],
    ];

    $generator = Generator::get_instance();
    ob_start();
    $generator->render_location_text_meta_box($location);
    $output = ob_get_clean();

    expect($output)->toContain('name="location_text"')
                   ->toContain('Welcome to Berlin.');
});

test('get_page_data returns null when query vars are absent', function () {
    global $mock_query_vars;
    $mock_query_vars = [];

    $generator = Generator::get_instance();
    expect($generator->get_page_data())->toBeNull();
    expect(vpg_get_page_data())->toBeNull();
});
