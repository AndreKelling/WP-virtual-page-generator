<?php
/**
 * Template for virtual pages.
 */

$data = function_exists("vpg_get_page_data") ? vpg_get_page_data() : null;
if (empty($data)) {
    return;
}

get_header();
?>
<div id="primary" class="content-area">
	<main id="main" class="site-main">
		<article class="page type-page status-publish hentry">
			<header class="entry-header">
				<h1 class="entry-title"><?php echo esc_html($data["title"]); ?></h1>
			</header>
			<div class="entry-content">
				<?php echo apply_filters("the_content", $data["content"]); ?>
			</div>
		</article>
	</main>
</div>
<?php get_footer();
