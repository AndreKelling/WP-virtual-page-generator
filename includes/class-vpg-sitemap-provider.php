<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WP_Sitemaps_Provider' ) ) {
	class VPG_Sitemap_Provider extends WP_Sitemaps_Provider {
		public function __construct() {
			$this->name        = 'vpgpages';
			$this->object_type = 'vpg_virtual_page';
		}

		public function get_url_list( $page_num, $object_subtype = '' ): array
		{
			$url_list = [];

			$services = get_posts( [
				'post_type'      => 'vpg_service',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );

			$locations = get_posts( [
				'post_type'      => 'vpg_location',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );

			$templates = get_posts( [
				'post_type'      => 'vpg_template',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			] );

			$generator = VirtualPageGenerator::get_instance();
			$limit     = 2000;
			$offset    = ( $page_num - 1 ) * $limit;
			$count     = 0;
			$added     = 0;

			foreach ( $templates as $template ) {
				foreach ( $services as $service ) {
					foreach ( $locations as $location ) {
						if ( $count >= $offset && $added < $limit ) {
							$url_list[] = [
								'loc'     => $generator->get_virtual_url( $template, $service->post_name, $location->post_name ),
								'lastmod' => max( $template->post_modified_gmt, $service->post_modified_gmt, $location->post_modified_gmt ),
							];
							$added++;
						}
						$count++;

						if ( $added >= $limit ) {
							break 3;
						}
					}
				}
			}

			return $url_list;
		}

		public function get_max_num_pages( $object_subtype = '' ): float|int
		{
			$services_count  = (int) wp_count_posts( 'vpg_service' )->publish;
			$locations_count = (int) wp_count_posts( 'vpg_location' )->publish;
			$templates_count = (int) wp_count_posts( 'vpg_template' )->publish;

			$total = $services_count * $locations_count * $templates_count;
			if ( $total === 0 ) {
				return 0;
			}

			$limit = 2000;
			return ceil( $total / $limit );
		}
	}
}
