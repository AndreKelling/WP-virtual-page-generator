<?php

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Resolves and caches virtual page data for the current request.
 *
 * Responsibilities:
 * - Reading query variables for service, location, and template
 * - Fetching service, location, and template posts
 * - Reading post meta (texts, images)
 * - Replacing placeholders in template content
 * - Caching the resolved data to avoid repeated queries
 */
class VPG_Page_Data
{
    /** @var array<string, mixed>|null */
    private ?array $cached_data = null;

    /** @var VPG_Rewrite|null */
    private ?VPG_Rewrite $rewrite = null;

    public function __construct(?VPG_Rewrite $rewrite = null)
    {
        $this->rewrite = $rewrite;
    }

    /**
     * Reset the cached page data (useful for testing).
     */
    public function reset(): void
    {
        $this->cached_data = null;
    }

    /**
     * Set page data manually (useful for testing or manual overrides).
     *
     * @param array<string, mixed>|null $data
     */
    public function set_data(?array $data): void
    {
        $this->cached_data = $data;
    }

    /**
     * Get the resolved virtual page data for the current request.
     *
     * @return array<string, mixed>|null
     */
    public function get_data(): ?array
    {
        if ($this->cached_data !== null) {
            return $this->cached_data;
        }

        $service_slug = get_query_var("vpg_service_slug");
        $location_slug = get_query_var("vpg_location_slug");

        if (empty($service_slug) && empty($location_slug)) {
            return null;
        }

        $service = $this->find_post_by_slug("vpg_service", (string) $service_slug);
        $location = $this->find_post_by_slug("vpg_location", (string) $location_slug);

        if (empty($service) || empty($location)) {
            $this->handle_not_found();
            return null;
        }

        $template = $this->resolve_template();
        if (!$template) {
            return null;
        }

        $data = $this->build_page_data($template, $service, $location);
        $this->cached_data = $data;
        return $data;
    }

    /**
     * @return WP_Post|null
     */
    private function find_post_by_slug(string $post_type, string $slug): ?WP_Post
    {
        $posts = get_posts([
            "post_type" => $post_type,
            "name" => $slug,
            "posts_per_page" => 1,
        ]);

        return $posts[0] ?? null;
    }

    /**
     * @return WP_Post|null
     */
    private function resolve_template(): ?WP_Post
    {
        $template_id = get_query_var("vpg_template_id");

        if ($template_id) {
            $template = get_post((int) $template_id);
            if (
                $template &&
                "vpg_template" === $template->post_type &&
                "publish" === $template->post_status
            ) {
                return $template;
            }
        }

        // Fallback to the latest published template
        $templates = get_posts([
            "post_type" => "vpg_template",
            "posts_per_page" => 1,
            "orderby" => "date",
            "order" => "DESC",
            "post_status" => "publish",
        ]);

        return $templates[0] ?? null;
    }

    /**
     * @param WP_Post $template
     * @param WP_Post $service
     * @param WP_Post $location
     * @return array<string, mixed>
     */
    private function build_page_data(
        WP_Post $template,
        WP_Post $service,
        WP_Post $location
    ): array {
        $location_text = $this->get_meta_with_fallback(
            $location->ID,
            "_vpg_location_text",
            "location_text"
        );

        $location_for_service_text = get_post_meta(
            $location->ID,
            "_vpg_location_for_service_text_" . $service->ID,
            true,
        );

        $service_text = $this->get_meta_with_fallback(
            $service->ID,
            "_vpg_service_text",
            "service_text"
        );

        $service_image_id = get_post_meta($service->ID, "_vpg_service_image", true);
        $service_image_id = $service_image_id ? (int) $service_image_id : null;
        $service_image_html = $service_image_id && function_exists("wp_get_attachment_image")
            ? wp_get_attachment_image($service_image_id, "full")
            : "";

        $title = str_replace(
            ["{{service}}", "{{location}}"],
            [$service->post_title, $location->post_title],
            $template->post_title,
        );

        $content = $this->replace_placeholders(
            $template->post_content,
            $service,
            $location,
            $service_text,
            $location_text,
            $location_for_service_text,
            $service_image_html
        );

        return [
            "service" => $service,
            "location" => $location,
            "template" => $template,
            "vpg_template" => $template,
            "title" => $title,
            "content" => $content,
            "location_text" => $location_text,
            "location_for_service_text" => $location_for_service_text,
            "service_text" => $service_text,
            "service_image_id" => $service_image_id,
            "service_image_html" => $service_image_html,
        ];
    }

    /**
     * Get post meta with fallback to legacy key.
     */
    private function get_meta_with_fallback(
        int $post_id,
        string $primary_key,
        string $fallback_key
    ): string {
        $value = get_post_meta($post_id, $primary_key, true);
        if (empty($value)) {
            $value = get_post_meta($post_id, $fallback_key, true);
        }
        return (string) $value;
    }

    /**
     * Replace all placeholders in template content.
     */
    private function replace_placeholders(
        string $content,
        WP_Post $service,
        WP_Post $location,
        string $service_text,
        string $location_text,
        string $location_for_service_text,
        string $service_image_html
    ): string {
        $content = str_replace("{{service}}", $service->post_title, $content);
        $content = str_replace("{{location}}", $location->post_title, $content);
        $content = str_replace("{{location_text}}", $location_text, $content);
        $content = str_replace("{{location_for_service_text}}", $location_for_service_text, $content);
        $content = str_replace("{{service_text}}", $service_text, $content);
        $content = str_replace("{{service_image}}", $service_image_html, $content);
        return $content;
    }

    /**
     * Handle 404 when service or location is not found.
     */
    private function handle_not_found(): void
    {
        global $wp_query;
        if (isset($wp_query) && is_object($wp_query) && method_exists($wp_query, "set_404")) {
            $wp_query->set_404();
        }
        if (function_exists("status_header")) {
            status_header(404);
        }
        if (function_exists("get_template_part")) {
            get_template_part(404);
        }
        if (
            !defined("PHPUNIT_RUNNING") &&
            !defined("PEST_RUNNING") &&
            !defined("WP_TESTS_DOMAIN")
        ) {
            exit();
        }
    }
}
