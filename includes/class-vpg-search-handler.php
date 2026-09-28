<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WP_REST_Search_Handler' ) ) {
	class VPG_Search_Handler extends WP_REST_Search_Handler {
		public function __construct() {
			$this->type     = 'post';
			$this->subtypes = [ 'vpg_virtual_page' ];
		}

		public function search_items( WP_REST_Request $request ): array
		{
			$search = $request['search'];
			$limit  = (int) $request['per_page'];
			$page   = (int) $request['page'];

			if ( empty( $search ) ) {
				return [ self::RESULT_IDS => [], self::RESULT_TOTAL => 0 ];
			}

			// Find matching services
			$matching_services = get_posts( [
				'post_type'      => 'vpg_service',
				's'              => $search,
				'posts_per_page' => -1,
			] );

			// Find matching locations
			$matching_locations = get_posts( [
				'post_type'      => 'vpg_location',
				's'              => $search,
				'posts_per_page' => -1,
			] );

			if ( empty( $matching_services ) && empty( $matching_locations ) ) {
				return [ self::RESULT_IDS => [], self::RESULT_TOTAL => 0 ];
			}

			$all_services  = get_posts( [ 'post_type' => 'vpg_service', 'posts_per_page' => -1 ] );
			$all_locations = get_posts( [ 'post_type' => 'vpg_location', 'posts_per_page' => -1 ] );
			$all_templates = get_posts( [ 'post_type' => 'vpg_template', 'post_status' => 'publish', 'posts_per_page' => -1 ] );

			$results = [];

			// If service matches, add all locations for those services
			foreach ( $matching_services as $service ) {
				foreach ( $all_locations as $location ) {
					foreach ( $all_templates as $template ) {
						$id = "vpg:{$template->ID}:{$service->ID}:{$location->ID}";
						$results[ $id ] = true;
					}
				}
			}

			// If location matches, add all services for those locations
			foreach ( $matching_locations as $location ) {
				foreach ( $all_services as $service ) {
					foreach ( $all_templates as $template ) {
						$id = "vpg:{$template->ID}:{$service->ID}:{$location->ID}";
						$results[ $id ] = true;
					}
				}
			}

			$ids   = array_keys( $results );
			$total = count( $ids );

			// Pagination
			$offset    = ( $page - 1 ) * $limit;
			$paged_ids = array_slice( $ids, $offset, $limit );

			return [
				self::RESULT_IDS   => $paged_ids,
				self::RESULT_TOTAL => $total,
			];
		}

		public function prepare_item( $id, array $fields ): array
		{
			$parts = explode( ':', $id );
			if ( count( $parts ) !== 4 || $parts[0] !== 'vpg' ) {
				return [];
			}

			$template_id = (int) $parts[1];
			$service_id  = (int) $parts[2];
			$location_id = (int) $parts[3];

			$template = get_post( $template_id );
			$service  = get_post( $service_id );
			$location = get_post( $location_id );

			if ( ! $template || ! $service || ! $location ) {
				return [];
			}

			$generator = VirtualPageGenerator::get_instance();
			$url       = $generator->get_virtual_url( $template, $service->post_name, $location->post_name );

			$title = str_replace(
				[ '{{service}}', '{{location}}' ],
				[ $service->post_title, $location->post_title ],
				$template->post_title
			);

			return [
				'id'      => $id,
				'title'   => $title,
				'url'     => $url,
				'type'    => 'post',
				'subtype' => 'vpg_virtual_page',
			];
		}

		public function prepare_item_links( $id ): array
		{
			return [];
		}
	}
}
