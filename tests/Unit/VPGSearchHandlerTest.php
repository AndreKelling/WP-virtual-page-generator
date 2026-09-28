<?php

use VirtualPageGenerator as Generator;

require_once __DIR__ . '/../../virtual-page-generator.php';
require_once __DIR__ . '/../Pest.php';

beforeEach(function () {
    mock_wp_functions();
    global $mock_posts;
    $mock_posts = [];
});

test('search handler returns correct results', function () {
    global $mock_posts;

    $template = new WP_Post();
    $template->ID = 10;
    $template->post_title = '{{service}} in {{location}}';
    $template->post_type = 'vpg_template';
    $template->post_status = 'publish';

    $service = new WP_Post();
    $service->ID = 20;
    $service->post_title = 'Baumfaellung';
    $service->post_name = 'baumfaellung';
    $service->post_type = 'vpg_service';

    $location = new WP_Post();
    $location->ID = 30;
    $location->post_title = 'Mittweida';
    $location->post_name = 'mittweida';
    $location->post_type = 'vpg_location';

    $mock_posts = [$template, $service, $location];

    $handler = new VPG_Search_Handler();
    $request = new WP_REST_Request('GET', '/wp/v2/search');
    $request->set_param('search', 'Baumfaellung');
    $request->set_param('per_page', 10);
    $request->set_param('page', 1);

    $results = $handler->search_items($request);

    expect($results[WP_REST_Search_Handler::RESULT_TOTAL])->toBe(1);
    $id = $results[WP_REST_Search_Handler::RESULT_IDS][0];
    expect($id)->toBe('vpg:10:20:30');

    $item = $handler->prepare_item($id, []);
    expect($item['title'])->toBe('Baumfaellung in Mittweida');
    expect($item['url'])->toBe('http://example.com/baumfaellung-in-mittweida/');
});
