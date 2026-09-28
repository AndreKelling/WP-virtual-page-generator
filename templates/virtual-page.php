<?php
/**
 * Template for virtual pages.
 */

$service_slug  = get_query_var( 'vpg_service_slug' );
$location_slug = get_query_var( 'vpg_location_slug' );

// Find the service post
$service_posts = get_posts( [
	'post_type'      => 'vpg_service',
	'name'           => $service_slug,
	'posts_per_page' => 1,
] );

// Find the location post
$location_posts = get_posts( [
	'post_type'      => 'vpg_location',
	'name'           => $location_slug,
	'posts_per_page' => 1,
] );

if ( empty( $service_posts ) || empty( $location_posts ) ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	get_template_part( 404 );
	exit;
}

$service  = $service_posts[0];
$location = $location_posts[0];

// Get the specific text for this service at this location
$text = get_post_meta( $location->ID, '_vpg_service_text_' . $service->ID, true );

// Find the page template
$template_id = get_query_var( 'vpg_template_id' );
$vpg_template = null;

if ( $template_id ) {
	$vpg_template = get_post( $template_id );
	if ( ! $vpg_template || 'vpg_template' !== $vpg_template->post_type || 'publish' !== $vpg_template->post_status ) {
		$vpg_template = null;
	}
}

if ( ! $vpg_template ) {
	$template_posts = get_posts( [
		'post_type'      => 'vpg_template',
		'posts_per_page' => 1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'post_status'    => 'publish',
	] );

	if ( empty( $template_posts ) ) {
		echo 'Please create a Page Template in the admin.';
		exit;
	}
	$vpg_template = $template_posts[0];
}

$content = $vpg_template->post_content;

// Replace placeholders
$content = str_replace( '{{service}}', $service->post_title, $content );
$content = str_replace( '{{location}}', $location->post_title, $content );
$content = str_replace( '{{text}}', nl2br( esc_html( $text ) ), $content );

// Apply filters to content
$content = apply_filters( 'the_content', $content );

// Output the content using the theme's header and footer
get_header();
?>
<div id="primary" class="content-area">
	<main id="main" class="site-main site-main--ptop">
		<article class="page type-page status-publish hentry">
			<header class="entry-header">
				<h1 class="entry-title"><?php echo esc_html( str_replace( [ '{{service}}', '{{location}}' ], [ $service->post_title, $location->post_title ], $vpg_template->post_title ) ); ?></h1>
			</header>
			<div class="entry-content">
				<?php echo $content; ?>
			</div>
		</article>
	</main>
</div>
<?php
get_footer();
