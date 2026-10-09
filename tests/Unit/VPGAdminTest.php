<?php

require_once __DIR__ . '/../Pest.php';
require_once __DIR__ . '/../../includes/class-vpg-admin.php';

beforeEach(function () {
    mock_wp_functions();
    global $mock_post_meta, $mock_posts;
    $mock_post_meta = [];
    $mock_posts = [];
});

test('render_service_text_meta_box renders editor with saved service_text', function () {
    global $mock_post_meta;

    $service = new WP_Post();
    $service->ID = 55;
    $service->post_title = 'Tree Care';

    $mock_post_meta = [
        55 => ['_vpg_service_text' => 'Quality tree care services.'],
    ];

    $admin = new VPG_Admin();
    ob_start();
    $admin->render_service_text_meta_box($service);
    $output = ob_get_clean();

    expect($output)->toContain('name="service_text"')
                   ->toContain('Quality tree care services.');
});

test('render_location_text_meta_box renders editor with saved location_text', function () {
    global $mock_post_meta;

    $location = new WP_Post();
    $location->ID = 20;
    $location->post_title = 'Berlin';

    $mock_post_meta = [
        20 => ['_vpg_location_text' => '<p>Welcome to Berlin.</p>'],
    ];

    $admin = new VPG_Admin();
    ob_start();
    $admin->render_location_text_meta_box($location);
    $output = ob_get_clean();

    expect($output)->toContain('name="location_text"')
                   ->toContain('Welcome to Berlin.');
});

test('save_service_meta saves service_text and service_image_id', function () {
    global $mock_post_meta;

    $_POST = [
        'vpg_service_nonce' => 'valid_nonce',
        'vpg_service_image_id' => '105',
        'service_text' => '<p>New lawn trimming text</p>',
    ];

    $admin = new VPG_Admin();
    $admin->save_service_meta(42);

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

    $admin = new VPG_Admin();
    $admin->save_location_meta(20);

    expect($mock_post_meta[20]['_vpg_location_text'])->toBe('<p>Welcome to Berlin.</p>');
    expect($mock_post_meta[20]['location_text'])->toBe('<p>Welcome to Berlin.</p>');
    expect($mock_post_meta[20]['_vpg_location_for_service_text_30'])->toBe('<p>Best mowing service in Berlin.</p>');
    expect($mock_post_meta[20]['_vpg_location_for_service_text_31'])->toBe('<p>Expert pruning in Berlin.</p>');

    $_POST = [];
});
