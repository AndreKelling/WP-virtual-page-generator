<?php

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Handles rewrite rules, URL generation, and query variables for virtual pages.
 *
 * Responsibilities:
 * - Adding rewrite rules based on page templates
 * - Generating virtual URLs from template, service, and location
 * - Registering custom query variables
 * - Flushing rewrite rules on template save
 */
class VPG_Rewrite
{
    public function __construct()
    {
        add_action("init", [$this, "add_rewrite_rules"]);
        add_filter("query_vars", [$this, "add_query_vars"]);
        add_action("save_post_vpg_template", [$this, "flush_rules_on_save"]);
    }

    /**
     * Generate a virtual URL from a template, service slug, and location slug.
     *
     * @param WP_Post $template
     * @param string $service_slug
     * @param string $location_slug
     * @return string|null
     */
    public function get_virtual_url(
        WP_Post $template,
        string $service_slug,
        string $location_slug,
    ): ?string {
        $title = $template->post_title;

        $has_service = str_contains($title, "{{service}}");
        $has_location = str_contains($title, "{{location}}");

        $url_part = str_replace(
            ["{{service}}", "{{location}}"],
            [$service_slug, $location_slug],
            $title,
        );

        if (!$has_service) {
            $url_part .= "/" . $service_slug;
        }
        if (!$has_location) {
            $url_part .= "/" . $location_slug;
        }

        $parts = explode("/", $url_part);
        $slug_parts = array_map("sanitize_title", $parts);
        return home_url("/" . implode("/", $slug_parts) . "/");
    }

    /**
     * Add rewrite rules for all published page templates.
     */
    public function add_rewrite_rules(): void
    {
        $templates = get_posts([
            "post_type" => "vpg_template",
            "posts_per_page" => -1,
            "post_status" => "publish",
        ]);

        foreach ($templates as $template) {
            $title = $template->post_title;

            $has_service = str_contains($title, "{{service}}");
            $has_location = str_contains($title, "{{location}}");

            $temp_title = $title;
            if (!$has_service) {
                $temp_title .= "/{{service}}";
            }
            if (!$has_location) {
                $temp_title .= "/{{location}}";
            }

            $temp_title = str_replace(
                ["{{service}}", "{{location}}"],
                ["VPGSVC", "VPGLOC"],
                $temp_title,
            );

            $parts = explode("/", $temp_title);
            $slug_parts = array_map("sanitize_title", $parts);
            $slug = implode("/", $slug_parts);

            $svc_pos = strpos($slug, "vpgsvc");
            $loc_pos = strpos($slug, "vpgloc");

            $regex = preg_quote($slug, "#");
            $regex = str_replace("vpgsvc", "([^/]+)", $regex);
            $regex = str_replace("vpgloc", "([^/]+)", $regex);

            $query = "index.php?vpg_template_id=" . $template->ID;

            if ($svc_pos < $loc_pos) {
                $order = ["service", "location"];
            } else {
                $order = ["location", "service"];
            }

            foreach ($order as $i => $type) {
                $query .= "&vpg_" . $type . '_slug=$matches[' . ($i + 1) . "]";
            }

            add_rewrite_rule("^" . $regex . '/?$', $query, "top");
        }
    }

    /**
     * Register custom query variables for virtual pages.
     *
     * @param string[] $vars
     * @return string[]
     */
    public function add_query_vars($vars)
    {
        $vars[] = "vpg_service_slug";
        $vars[] = "vpg_location_slug";
        $vars[] = "vpg_template_id";
        return $vars;
    }

    /**
     * Flush rewrite rules when a template is saved.
     */
    public function flush_rules_on_save(): void
    {
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
}
