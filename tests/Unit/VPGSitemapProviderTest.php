<?php

use VirtualPageGenerator as Generator;

require_once __DIR__ . '/../../virtual-page-generator.php';
require_once __DIR__ . '/../Pest.php';

beforeEach(function () {
    mock_wp_functions();
    global $mock_posts, $mock_counts;
    $mock_posts = [];
    $mock_counts = [];
});

test('sitemap provider calculates max pages correctly', function () {
    global $mock_counts;
    $mock_counts = [
        'vpg_service' => ['publish' => 10],
        'vpg_location' => ['publish' => 20],
        'vpg_template' => ['publish' => 2],
    ];

    // Total pages = 10 * 20 * 2 = 400
    // Limit is 2000 per page, so 1 sitemap page

    $provider = new VPG_Sitemap_Provider();
    expect($provider->get_max_num_pages())->toBe(1.0);

    $mock_counts = [
        'vpg_service' => ['publish' => 100],
        'vpg_location' => ['publish' => 50],
        'vpg_template' => ['publish' => 1],
    ];
    // Total = 5000. 5000 / 2000 = 2.5 -> 3 pages
    expect($provider->get_max_num_pages())->toBe(3.0);
});

test('sitemap provider returns correct url list', function () {
    global $mock_posts;

    $template = new WP_Post();
    $template->ID = 1;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_name = 't';
    $template->post_type = 'vpg_template';
    $template->post_status = 'publish';
    $template->post_modified_gmt = '2023-01-01 10:00:00';

    $service = new WP_Post();
    $service->ID = 2;
    $service->post_title = 'S';
    $service->post_name = 's';
    $service->post_type = 'vpg_service';
    $service->post_modified_gmt = '2023-01-02 10:00:00';

    $location = new WP_Post();
    $location->ID = 3;
    $location->post_title = 'L';
    $location->post_name = 'l';
    $location->post_type = 'vpg_location';
    $location->post_modified_gmt = '2023-01-03 10:00:00';

    $mock_posts = [$template, $service, $location];

    $provider = new VPG_Sitemap_Provider();
    $list = $provider->get_url_list(1);

    expect($list)->toHaveCount(1);
    expect($list[0]['loc'])->toBe('http://example.com/s-in-l/');
    expect($list[0]['lastmod'])->toBe('2023-01-03 10:00:00');
});
