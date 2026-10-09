<?php

require_once __DIR__ . '/../Pest.php';
require_once __DIR__ . '/../../includes/class-vpg-rewrite.php';
require_once __DIR__ . '/../../includes/class-vpg-page-data.php';

beforeEach(function () {
    mock_wp_functions();
    global $mock_posts, $mock_query_vars, $mock_post_meta;
    $mock_posts = [];
    $mock_query_vars = [];
    $mock_post_meta = [];
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

    $pageData = new VPG_Page_Data();
    $data = $pageData->get_data();

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
});

test('get_page_data returns null when query vars are absent', function () {
    global $mock_query_vars;
    $mock_query_vars = [];

    $pageData = new VPG_Page_Data();
    expect($pageData->get_data())->toBeNull();
});

test('get_page_data caches data and returns cached result on second call', function () {
    global $mock_posts, $mock_query_vars, $mock_post_meta;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_content = 'Content';
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

    $pageData = new VPG_Page_Data();
    $data1 = $pageData->get_data();
    
    // Change query vars - should not affect cached data
    $mock_query_vars = [];
    $data2 = $pageData->get_data();

    expect($data1)->toBe($data2);
    expect($data1['title'])->toBe('Mowing in Berlin');
});

test('reset clears cached data', function () {
    global $mock_posts, $mock_query_vars, $mock_post_meta;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_content = 'Content';
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

    $pageData = new VPG_Page_Data();
    $data1 = $pageData->get_data();
    
    $pageData->reset();
    $mock_query_vars = [];
    $data2 = $pageData->get_data();

    expect($data1)->not->toBeNull();
    expect($data2)->toBeNull();
});
