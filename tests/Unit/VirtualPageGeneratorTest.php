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
    global $registered_rules, $mock_posts, $mock_counts, $mock_query_vars, $mock_located_templates, $mock_post_meta, $mock_registered_blocks, $mock_registered_scripts, $mock_registered_post_types;
    $registered_rules = [];
    $mock_posts = [];
    $mock_counts = [];
    $mock_query_vars = [];
    $mock_located_templates = [];
    $mock_post_meta = [];
    $mock_registered_blocks = [];
    $mock_registered_scripts = [];
    $mock_registered_post_types = [];
    Generator::reset_instance();
});

test('register_post_types hides all VPG post types from navigation menus', function () {
    global $mock_registered_post_types;

    $generator = Generator::get_instance();
    $generator->register_post_types();

    expect($mock_registered_post_types)->toHaveKey('vpg_service');
    expect($mock_registered_post_types['vpg_service']['show_in_nav_menus'])->toBeFalse();

    expect($mock_registered_post_types)->toHaveKey('vpg_location');
    expect($mock_registered_post_types['vpg_location']['show_in_nav_menus'])->toBeFalse();

    expect($mock_registered_post_types)->toHaveKey('vpg_template');
    expect($mock_registered_post_types['vpg_template']['show_in_nav_menus'])->toBeFalse();
    expect($mock_registered_post_types['vpg_template']['supports'])->toContain('excerpt');
});

test('register_blocks registers both blocks with api_version 3 and correct script dependencies', function () {
    global $mock_registered_blocks, $mock_registered_scripts;

    $generator = Generator::get_instance();
    $generator->register_blocks();

    expect($mock_registered_scripts)->toHaveKey('vpg-block-js');
    expect($mock_registered_scripts['vpg-block-js']['deps'])->toContain('wp-block-editor');

    expect($mock_registered_blocks)->toHaveKey('vpg/services-by-location');
    expect($mock_registered_blocks['vpg/services-by-location']['api_version'])->toBe(3);
    expect($mock_registered_blocks['vpg/services-by-location']['attributes'])->toHaveKeys(['locationId', 'templateId']);

    expect($mock_registered_blocks)->toHaveKey('vpg/locations-by-service');
    expect($mock_registered_blocks['vpg/locations-by-service']['api_version'])->toBe(3);
    expect($mock_registered_blocks['vpg/locations-by-service']['attributes'])->toHaveKeys(['serviceId', 'templateId']);
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

test('render_service_list_block resolves location by dynamic replaced title in locationId', function () {
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
        'locationId' => 'Berlin',
        'templateId' => '10',
    ]);

    expect($html)->toBe('<ul class="vpg-service-list"><li><a href="http://example.com/mowing-in-berlin/">Mowing in Berlin</a></li></ul>');
});

test('render_service_list_block returns not found message when locationId is placeholder or non-existent', function () {
    global $mock_posts;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_type = 'vpg_template';

    $service = new WP_Post();
    $service->ID = 30;
    $service->post_title = 'Mowing';
    $service->post_name = 'mowing';
    $service->post_type = 'vpg_service';

    $mock_posts = [$template, $service];

    $generator = Generator::get_instance();
    $htmlPlaceholder = $generator->render_service_list_block([
        'locationId' => '{{location}}',
        'templateId' => '10',
    ]);
    expect($htmlPlaceholder)->toBe('<p>Selected location or template not found.</p>');

    $htmlNonExistent = $generator->render_service_list_block([
        'locationId' => 'NonExistentCity',
        'templateId' => '10',
    ]);
    expect($htmlNonExistent)->toBe('<p>Selected location or template not found.</p>');
});

test('render_service_list_block returns message when location and template are missing', function () {
    $generator = Generator::get_instance();
    $html = $generator->render_service_list_block([
        'locationId' => '',
        'templateId' => '',
    ]);

    expect($html)->toBe('<p>Please select a location and template in the block settings.</p>');
});

test('render_location_list_block generates comma separated links with und for last item', function () {
    global $mock_posts;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_type = 'vpg_template';

    $location1 = new WP_Post();
    $location1->ID = 20;
    $location1->post_title = 'Berlin';
    $location1->post_name = 'berlin';
    $location1->post_type = 'vpg_location';

    $location2 = new WP_Post();
    $location2->ID = 21;
    $location2->post_title = 'Munich';
    $location2->post_name = 'munich';
    $location2->post_type = 'vpg_location';

    $location3 = new WP_Post();
    $location3->ID = 22;
    $location3->post_title = 'Hamburg';
    $location3->post_name = 'hamburg';
    $location3->post_type = 'vpg_location';

    $service = new WP_Post();
    $service->ID = 30;
    $service->post_title = 'Mowing';
    $service->post_name = 'mowing';
    $service->post_type = 'vpg_service';

    // 1 location
    $mock_posts = [$template, $location1, $service];
    $generator = Generator::get_instance();
    $html1 = $generator->render_location_list_block([
        'serviceId' => '30',
        'templateId' => '10',
    ]);
    expect($html1)->toBe('<div class="vpg-location-list"><a href="http://example.com/mowing-in-berlin/">Berlin</a></div>');

    // 2 locations
    $mock_posts = [$template, $location1, $location2, $service];
    $html2 = $generator->render_location_list_block([
        'serviceId' => '30',
        'templateId' => '10',
    ]);
    $expected2 = '<div class="vpg-location-list"><a href="http://example.com/mowing-in-berlin/">Berlin</a> '
        . 'und <a href="http://example.com/mowing-in-munich/">Munich</a></div>';
    expect($html2)->toBe($expected2);

    // 3 locations
    $mock_posts = [$template, $location1, $location2, $location3, $service];
    $html3 = $generator->render_location_list_block([
        'serviceId' => '30',
        'templateId' => '10',
    ]);
    $expected3 = '<div class="vpg-location-list"><a href="http://example.com/mowing-in-berlin/">Berlin</a>, '
        . '<a href="http://example.com/mowing-in-munich/">Munich</a> '
        . 'und <a href="http://example.com/mowing-in-hamburg/">Hamburg</a></div>';
    expect($html3)->toBe($expected3);
});

test('render_location_list_block resolves service by dynamic replaced title in serviceId', function () {
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
    $html = $generator->render_location_list_block([
        'serviceId' => 'Mowing',
        'templateId' => '10',
    ]);

    expect($html)->toBe('<div class="vpg-location-list"><a href="http://example.com/mowing-in-berlin/">Berlin</a></div>');
});

test('render_location_list_block returns message when service or template is missing', function () {
    $generator = Generator::get_instance();
    $html = $generator->render_location_list_block([
        'serviceId' => '',
        'templateId' => '',
    ]);

    expect($html)->toBe('<p>Please select a service and template in the block settings.</p>');
});

test('render_location_list_block returns message when no locations found', function () {
    global $mock_posts;
    $mock_posts = [];

    $service = new WP_Post();
    $service->ID = 30;
    $service->post_title = 'Mowing';
    $service->post_name = 'mowing';
    $service->post_type = 'vpg_service';

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_type = 'vpg_template';

    $mock_posts = [$service, $template];

    $generator = Generator::get_instance();
    $html = $generator->render_location_list_block([
        'serviceId' => '30',
        'templateId' => '10',
    ]);

    expect($html)->toBe('<p>No locations found.</p>');
});

test('add_to_tsf_sitemap adds virtual urls with correct w3c lastmod', function () {
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
    expect($sitemap['http://example.com/mowing-in-berlin/']['lastmod'])->toBe('2023-01-05T12:00:00+00:00');
});

test('add_to_tsf_sitemap handles empty or missing post_modified_gmt gracefully', function () {
    global $mock_posts;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_type = 'vpg_template';
    $template->post_status = 'publish';
    $template->post_modified_gmt = '';

    $location = new WP_Post();
    $location->ID = 20;
    $location->post_title = 'Berlin';
    $location->post_name = 'berlin';
    $location->post_type = 'vpg_location';
    $location->post_modified_gmt = null;

    $service = new WP_Post();
    $service->ID = 30;
    $service->post_title = 'Mowing';
    $service->post_name = 'mowing';
    $service->post_type = 'vpg_service';
    $service->post_modified_gmt = '0000-00-00 00:00:00';

    $mock_posts = [$template, $location, $service];

    $generator = Generator::get_instance();
    $sitemap = $generator->add_to_tsf_sitemap([], []);

    expect($sitemap)->toHaveKey('http://example.com/mowing-in-berlin/');
    expect($sitemap['http://example.com/mowing-in-berlin/']['lastmod'])->toBe('');
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
    expect(vpg_get_title())->toBe('Mowing in Berlin');
    expect(vpg_get_description())->toBe('We offer Mowing in Berlin.');
    expect(vpg_get_meta_description())->toBe('We offer Mowing in Berlin.');
    expect(vpg_get_canonical_url())->toBe('http://example.com/mowing-in-berlin/');
});

test('output_title_tag echoes title tag for virtual pages when theme does not support title-tag', function () {
    global $mock_theme_supports;
    $mock_theme_supports = ['title-tag' => false];

    $generator = Generator::get_instance();
    $generator->set_page_data([
        'title' => 'Mowing in Berlin',
    ]);

    ob_start();
    $generator->output_title_tag();
    $output = ob_get_clean();

    expect($output)->toBe('<title>Mowing in Berlin</title>' . "\n");
});

test('output_title_tag outputs nothing when theme supports title-tag', function () {
    global $mock_theme_supports;
    $mock_theme_supports = ['title-tag' => true];

    $generator = Generator::get_instance();
    $generator->set_page_data([
        'title' => 'Mowing in Berlin',
    ]);

    ob_start();
    $generator->output_title_tag();
    $output = ob_get_clean();

    expect($output)->toBe('');
});

test('output_title_tag outputs nothing when not on a virtual page', function () {
    global $mock_theme_supports;
    $mock_theme_supports = ['title-tag' => false];

    $generator = Generator::get_instance();
    $generator->set_page_data(null);

    ob_start();
    $generator->output_title_tag();
    $output = ob_get_clean();

    expect($output)->toBe('');
});

test('filter_document_title returns virtual page title when active', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data([
        'title' => 'Mowing in Berlin',
    ]);

    $title = $generator->filter_document_title('Original Title');
    expect($title)->toBe('Mowing in Berlin');

    $generator->set_page_data(null);
    $titleFallback = $generator->filter_document_title('Original Title');
    expect($titleFallback)->toBe('Original Title');
});

test('filter_document_title_parts modifies title part for virtual pages', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data([
        'title' => 'Mowing in Berlin',
    ]);

    $parts = $generator->filter_document_title_parts(['title' => 'Original', 'site' => 'Gartenzebra']);
    expect($parts)->toBe(['title' => 'Mowing in Berlin', 'site' => 'Gartenzebra']);

    $generator->set_page_data(null);
    $partsFallback = $generator->filter_document_title_parts(['title' => 'Original', 'site' => 'Gartenzebra']);
    expect($partsFallback)->toBe(['title' => 'Original', 'site' => 'Gartenzebra']);
});

test('filter_wp_title returns virtual page title when active', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data([
        'title' => 'Mowing in Berlin',
    ]);

    $title = $generator->filter_wp_title('Original Title');
    expect($title)->toBe('Mowing in Berlin');

    $generator->set_page_data(null);
    $titleFallback = $generator->filter_wp_title('Original Title');
    expect($titleFallback)->toBe('Original Title');
});

test('filter_tsf_title returns virtual page title when active', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data([
        'title' => 'Mowing in Berlin',
    ]);

    $title = $generator->filter_tsf_title('Original TSF Title');
    expect($title)->toBe('Mowing in Berlin');

    $generator->set_page_data(null);
    $titleFallback = $generator->filter_tsf_title('Original TSF Title');
    expect($titleFallback)->toBe('Original TSF Title');
});

test('output_meta_description echoes meta description tag for virtual pages', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data([
        'description' => 'Find professional mowing services in Berlin.',
    ]);

    ob_start();
    $generator->output_meta_description();
    $output = ob_get_clean();

    expect($output)->toBe('<meta name="description" content="Find professional mowing services in Berlin." />' . "\n");
});

test('output_meta_description outputs nothing when not on a virtual page', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data(null);

    ob_start();
    $generator->output_meta_description();
    $output = ob_get_clean();

    expect($output)->toBe('');
});

test('filter_tsf_description returns virtual page description when active', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data([
        'description' => 'Find professional mowing services in Berlin.',
    ]);

    $desc = $generator->filter_tsf_description('Original TSF Description');
    expect($desc)->toBe('Find professional mowing services in Berlin.');

    $generator->set_page_data(null);
    $descFallback = $generator->filter_tsf_description('Original TSF Description');
    expect($descFallback)->toBe('Original TSF Description');
});

test('output_canonical_url echoes canonical link tag for virtual pages', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data([
        'canonical_url' => 'http://example.com/mowing-in-berlin/',
    ]);

    ob_start();
    $generator->output_canonical_url();
    $output = ob_get_clean();

    expect($output)->toBe('<link rel="canonical" href="http://example.com/mowing-in-berlin/" />' . "\n");
});

test('output_canonical_url outputs nothing when not on a virtual page', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data(null);

    ob_start();
    $generator->output_canonical_url();
    $output = ob_get_clean();

    expect($output)->toBe('');
});

test('filter_canonical_url returns virtual page canonical URL when active', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data([
        'canonical_url' => 'http://example.com/mowing-in-berlin/',
    ]);

    $url = $generator->filter_canonical_url('http://example.com/original-url/');
    expect($url)->toBe('http://example.com/mowing-in-berlin/');

    $generator->set_page_data(null);
    $urlFallback = $generator->filter_canonical_url('http://example.com/original-url/');
    expect($urlFallback)->toBe('http://example.com/original-url/');
});

test('filter_tsf_canonical_url returns virtual page canonical URL when active', function () {
    $generator = Generator::get_instance();
    $generator->set_page_data([
        'canonical_url' => 'http://example.com/mowing-in-berlin/',
    ]);

    $url = $generator->filter_tsf_canonical_url('http://example.com/tsf-url/');
    expect($url)->toBe('http://example.com/mowing-in-berlin/');

    $generator->set_page_data(null);
    $urlFallback = $generator->filter_tsf_canonical_url('http://example.com/tsf-url/');
    expect($urlFallback)->toBe('http://example.com/tsf-url/');
});

test('set_page_data allows manual override', function () {
    $generator = Generator::get_instance();
    $customData = ['custom' => 'value'];
    
    $generator->set_page_data($customData);
    expect($generator->get_page_data())->toBe($customData);
});
