<?php

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Main plugin orchestrator for Virtual Page Generator.
 *
 * Responsibilities:
 * - Singleton instance management
 * - CPT registration
 * - Admin menu
 * - Wiring sub-components (Admin, Rewrite, PageData, Sitemaps, Blocks)
 * - Template resolution for virtual pages
 * - Block rendering
 */
class VirtualPageGenerator
{
    private static ?VirtualPageGenerator $instance = null;
    private string $plugin_file;
    private string $plugin_path;

    /** @var VPG_Admin */
    private $admin;

    /** @var VPG_Rewrite */
    private $rewrite;

    /** @var VPG_Page_Data */
    private $pageData;

    public static function get_instance(
        string $plugin_file = "",
    ): ?VirtualPageGenerator {
        if (null === self::$instance) {
            self::$instance = new self($plugin_file);
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance (primarily for testing).
     */
    public static function reset_instance(): void
    {
        self::$instance = null;
    }

    private function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_path = plugin_dir_path($plugin_file);

        // Initialize sub-components
        $this->admin = new VPG_Admin();
        $this->rewrite = new VPG_Rewrite();
        $this->pageData = new VPG_Page_Data($this->rewrite);

        // Core hooks
        add_action("init", [$this, "register_post_types"]);
        add_action("admin_menu", [$this, "add_admin_menu"]);
        add_filter("template_include", [$this, "handle_virtual_page"]);
        add_action("wp_head", [$this, "output_canonical_url"]);
        add_filter("get_canonical_url", [$this, "filter_canonical_url"], 10, 2);
        add_action("wp_sitemaps_init", [$this, "init_sitemaps"]);
        add_filter(
            "the_seo_framework_sitemap_additional_urls",
            [$this, "add_to_tsf_sitemap"],
            10,
            2,
        );
        add_filter(
            "the_seo_framework_canonical_url",
            [$this, "filter_tsf_canonical_url"],
            10,
            2,
        );
        add_action("save_post_vpg_template", [$this, "refresh_tsf_sitemaps"]);
        add_action("save_post_vpg_service", [$this, "refresh_tsf_sitemaps"]);
        add_action("save_post_vpg_location", [$this, "refresh_tsf_sitemaps"]);
        add_action("deleted_post", [$this, "refresh_tsf_sitemaps_on_deletion"]);
        add_filter("rest_search_handlers", [$this, "register_search_handler"]);
        add_action("init", [$this, "register_blocks"]);

        register_activation_hook($this->plugin_file, [$this, "activate"]);
        register_deactivation_hook($this->plugin_file, [$this, "deactivate"]);
    }

    // ========================================================================
    // Lifecycle
    // ========================================================================

    public function activate(): void
    {
        $this->register_post_types();
        $this->rewrite->add_rewrite_rules();
        flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    // ========================================================================
    // CPT Registration
    // ========================================================================

    public function register_post_types(): void
    {
        register_post_type("vpg_service", [
            "labels" => ["name" => "Services", "singular_name" => "Service"],
            "public" => true,
            "publicly_queryable" => false,
            "query_var" => false,
            "rewrite" => false,
            "show_in_menu" => "vpg-main",
            "show_in_nav_menus" => false,
            "supports" => ["title"],
            "has_archive" => false,
            "show_in_rest" => true,
        ]);

        register_post_type("vpg_location", [
            "labels" => ["name" => "Locations", "singular_name" => "Location"],
            "public" => true,
            "publicly_queryable" => false,
            "query_var" => false,
            "rewrite" => false,
            "show_in_menu" => "vpg-main",
            "show_in_nav_menus" => false,
            "supports" => ["title"],
            "has_archive" => false,
            "show_in_rest" => true,
        ]);

        register_post_type("vpg_template", [
            "labels" => [
                "name" => "Page Templates",
                "singular_name" => "Page Template",
            ],
            "public" => true,
            "publicly_queryable" => false,
            "query_var" => false,
            "rewrite" => false,
            "show_in_menu" => "vpg-main",
            "show_in_nav_menus" => false,
            "supports" => ["title", "editor"],
            "has_archive" => false,
            "show_in_rest" => true,
        ]);
    }

    // ========================================================================
    // Admin Menu
    // ========================================================================

    public function add_admin_menu(): void
    {
        add_menu_page(
            "Page Generator",
            "Page Generator",
            "edit_posts",
            "vpg-main",
            [$this, "render_generated_pages"],
            "dashicons-layout",
            25,
        );

        add_submenu_page(
            "vpg-main",
            "Generated Pages",
            "Generated Pages",
            "edit_posts",
            "vpg-main",
            [$this, "render_generated_pages"],
        );
    }

    public function render_generated_pages(): void
    {
        $services = get_posts([
            "post_type" => "vpg_service",
            "posts_per_page" => -1,
            "orderby" => "title",
            "order" => "ASC",
        ]);

        $locations = get_posts([
            "post_type" => "vpg_location",
            "posts_per_page" => -1,
            "orderby" => "title",
            "order" => "ASC",
        ]);

        $templates = get_posts([
            "post_type" => "vpg_template",
            "posts_per_page" => -1,
            "post_status" => "publish",
        ]);

        echo '<div class="wrap">';
        echo "<h1>Generated Virtual Pages</h1>";

        if (empty($services) || empty($locations)) {
            echo "<p>Please add at least one Service and one Location to generate pages.</p>";
            echo "</div>";
            return;
        }

        if (empty($templates)) {
            echo "<p>Please add at least one Page Template to generate pages.</p>";
            echo "</div>";
            return;
        }

        foreach ($templates as $template) {
            echo "<h2>Template: " . esc_html($template->post_title) . "</h2>";
            echo '<table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">';
            echo "<thead>";
            echo "<tr>";
            echo "<th>Service</th>";
            echo "<th>Location</th>";
            echo "<th>URL</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";

            foreach ($services as $service) {
                foreach ($locations as $location) {
                    $url = $this->rewrite->get_virtual_url(
                        $template,
                        $service->post_name,
                        $location->post_name,
                    );
                    echo "<tr>";
                    echo "<td>" . esc_html($service->post_title) . "</td>";
                    echo "<td>" . esc_html($location->post_title) . "</td>";
                    echo '<td><a href="' .
                        esc_url($url) .
                        '" target="_blank">' .
                        esc_html($url) .
                        "</a></td>";
                    echo "</tr>";
                }
            }

            echo "</tbody>";
            echo "</table>";
        }
        echo "</div>";
    }

    // ========================================================================
    // Template Resolution
    // ========================================================================

    public function handle_virtual_page($template)
    {
        $template_id = get_query_var("vpg_template_id");
        $service_slug = get_query_var("vpg_service_slug");
        $location_slug = get_query_var("vpg_location_slug");

        if ($template_id || ($service_slug && $location_slug)) {
            $theme_template = locate_template([
                "virtual-page.php",
                "templates/virtual-page.php",
                "virtual-page-generator/virtual-page.php",
            ]);

            if ($theme_template) {
                return $theme_template;
            }

            return $this->plugin_path . "templates/virtual-page.php";
        }

        return $template;
    }

    // ========================================================================
    // Canonical URL & SEO
    // ========================================================================

    /**
     * Output canonical link tag in wp_head for virtual pages.
     */
    public function output_canonical_url(): void
    {
        $data = $this->get_page_data();
        if (empty($data) || empty($data["canonical_url"])) {
            return;
        }

        echo '<link rel="canonical" href="' . esc_url($data["canonical_url"]) . '" />' . "\n";
    }

    /**
     * Filter the canonical URL for virtual pages.
     *
     * @param string $canonical_url
     * @param WP_Post|int|null $post
     * @return string
     */
    public function filter_canonical_url($canonical_url, $post = null)
    {
        $data = $this->get_page_data();
        if (!empty($data) && !empty($data["canonical_url"])) {
            return $data["canonical_url"];
        }
        return $canonical_url;
    }

    /**
     * Filter The SEO Framework canonical URL for virtual pages.
     *
     * @param string $url
     * @param int $id
     * @return string
     */
    public function filter_tsf_canonical_url($url = "", $id = 0)
    {
        $data = $this->get_page_data();
        if (!empty($data) && !empty($data["canonical_url"])) {
            return $data["canonical_url"];
        }
        return $url;
    }

    // ========================================================================
    // Sitemaps
    // ========================================================================

    public function init_sitemaps(): void
    {
        if (class_exists("WP_Sitemaps_Provider")) {
            $provider = new VPG_Sitemap_Provider();
            wp_register_sitemap_provider("vpgpages", $provider);
        }
    }

    public function add_to_tsf_sitemap($custom_urls, $args)
    {
        $services = get_posts([
            "post_type" => "vpg_service",
            "posts_per_page" => -1,
        ]);

        $locations = get_posts([
            "post_type" => "vpg_location",
            "posts_per_page" => -1,
        ]);

        $templates = get_posts([
            "post_type" => "vpg_template",
            "posts_per_page" => -1,
            "post_status" => "publish",
        ]);

        foreach ($templates as $template) {
            foreach ($services as $service) {
                foreach ($locations as $location) {
                    $url = $this->rewrite->get_virtual_url(
                        $template,
                        $service->post_name,
                        $location->post_name,
                    );
                    $custom_urls[$url] = [
                        "lastmod" => max(
                            $template->post_modified_gmt,
                            $service->post_modified_gmt,
                            $location->post_modified_gmt,
                        ),
                    ];
                }
            }
        }

        return $custom_urls;
    }

    public function refresh_tsf_sitemaps(): void
    {
        if (class_exists("The_SEO_Framework\Sitemap\Registry")) {
            \The_SEO_Framework\Sitemap\Registry::refresh_sitemaps();
        }
    }

    public function refresh_tsf_sitemaps_on_deletion($post_id): void
    {
        $post_type = get_post_type($post_id);
        if (
            in_array($post_type, [
                "vpg_service",
                "vpg_location",
                "vpg_template",
            ])
        ) {
            $this->refresh_tsf_sitemaps();
        }
    }

    // ========================================================================
    // REST & Blocks
    // ========================================================================

    public function register_search_handler($handlers)
    {
        if (class_exists("WP_REST_Search_Handler")) {
            $handlers[] = new VPG_Search_Handler();
        }
        return $handlers;
    }

    public function register_blocks(): void
    {
        $block_file = $this->plugin_path . "assets/js/block.js";
        $version = file_exists($block_file) ? filemtime($block_file) : false;

        wp_register_script(
            "vpg-block-js",
            plugins_url("assets/js/block.js", $this->plugin_file),
            ["wp-blocks", "wp-element", "wp-components", "wp-data", "wp-block-editor"],
            $version,
        );

        register_block_type("vpg/services-by-location", [
            "api_version" => 3,
            "editor_script" => "vpg-block-js",
            "render_callback" => [$this, "render_service_list_block"],
            "attributes" => [
                "locationId" => [
                    "type" => "string",
                    "default" => "",
                ],
                "templateId" => [
                    "type" => "string",
                    "default" => "",
                ],
            ],
        ]);

        register_block_type("vpg/locations-by-service", [
            "api_version" => 3,
            "editor_script" => "vpg-block-js",
            "render_callback" => [$this, "render_location_list_block"],
            "attributes" => [
                "serviceId" => [
                    "type" => "string",
                    "default" => "",
                ],
                "templateId" => [
                    "type" => "string",
                    "default" => "",
                ],
            ],
        ]);
    }

    public function render_service_list_block($attributes): string
    {
        $location_val = !empty($attributes["locationId"]) ? trim((string) $attributes["locationId"]) : "";
        $template_id = !empty($attributes["templateId"]) ? (int) $attributes["templateId"] : 0;

        if ($location_val === "" || !$template_id) {
            return "<p>Please select a location and template in the block settings.</p>";
        }

        $location = null;
        if (is_numeric($location_val)) {
            $loc = get_post((int) $location_val);
            if ($loc && $loc->post_type === "vpg_location") {
                $location = $loc;
            }
        } elseif ($location_val !== "{{location}}") {
            $matching_locations = get_posts([
                "post_type" => "vpg_location",
                "title" => $location_val,
                "posts_per_page" => 1,
            ]);
            if (!empty($matching_locations)) {
                $location = $matching_locations[0];
            }
            if (empty($location)) {
                $matching_locations = get_posts([
                    "post_type" => "vpg_location",
                    "name" => sanitize_title($location_val),
                    "posts_per_page" => 1,
                ]);
                if (!empty($matching_locations)) {
                    $location = $matching_locations[0];
                }
            }
        }

        $template = get_post($template_id);

        if (!$location || !$template) {
            return "<p>Selected location or template not found.</p>";
        }

        $services = get_posts([
            "post_type" => "vpg_service",
            "posts_per_page" => -1,
            "orderby" => "title",
            "order" => "ASC",
        ]);

        if (empty($services)) {
            return "<p>No services found.</p>";
        }

        $wrapper_attributes = function_exists("get_block_wrapper_attributes")
            ? get_block_wrapper_attributes(["class" => "vpg-service-list"])
            : 'class="vpg-service-list"';

        $output = '<ul ' . $wrapper_attributes . '>';
        foreach ($services as $service) {
            $url = $this->rewrite->get_virtual_url(
                $template,
                $service->post_name,
                $location->post_name,
            );
            $title = str_replace(
                ["{{service}}", "{{location}}"],
                [$service->post_title, $location->post_title],
                $template->post_title,
            );
            $output .=
                '<li><a href="' .
                esc_url($url) .
                '">' .
                esc_html($title) .
                "</a></li>";
        }
        $output .= "</ul>";

        return $output;
    }

    public function render_location_list_block($attributes): string
    {
        $service_val = !empty($attributes["serviceId"]) ? trim((string) $attributes["serviceId"]) : "";
        $template_id = !empty($attributes["templateId"]) ? (int) $attributes["templateId"] : 0;

        if ($service_val === "" || !$template_id) {
            return "<p>Please select a service and template in the block settings.</p>";
        }

        $service = null;
        if (is_numeric($service_val)) {
            $svc = get_post((int) $service_val);
            if ($svc && $svc->post_type === "vpg_service") {
                $service = $svc;
            }
        } elseif ($service_val !== "{{service}}") {
            $matching_services = get_posts([
                "post_type" => "vpg_service",
                "title" => $service_val,
                "posts_per_page" => 1,
            ]);
            if (!empty($matching_services)) {
                $service = $matching_services[0];
            }
            if (empty($service)) {
                $matching_services = get_posts([
                    "post_type" => "vpg_service",
                    "name" => sanitize_title($service_val),
                    "posts_per_page" => 1,
                ]);
                if (!empty($matching_services)) {
                    $service = $matching_services[0];
                }
            }
        }

        $template = get_post($template_id);

        if (!$service || !$template) {
            return "<p>Selected service or template not found.</p>";
        }

        $locations = get_posts([
            "post_type" => "vpg_location",
            "posts_per_page" => -1,
            "orderby" => "title",
            "order" => "ASC",
        ]);

        if (empty($locations)) {
            return "<p>No locations found.</p>";
        }

        $wrapper_attributes = function_exists("get_block_wrapper_attributes")
            ? get_block_wrapper_attributes(["class" => "vpg-location-list"])
            : 'class="vpg-location-list"';

        $links = [];
        foreach ($locations as $location) {
            $url = $this->rewrite->get_virtual_url(
                $template,
                $service->post_name,
                $location->post_name,
            );
            $links[] =
                '<a href="' .
                esc_url($url) .
                '">' .
                esc_html($location->post_title) .
                '</a>';
        }

        if (count($links) === 1) {
            $list_content = $links[0];
        } else {
            $last_link = array_pop($links);
            $list_content = implode(", ", $links) . " und " . $last_link;
        }

        return '<div ' . $wrapper_attributes . '>' . $list_content . '</div>';
    }

    // ========================================================================
    // Public API — delegates to sub-components
    // ========================================================================

    /**
     * Get the rewrite component (for URL generation).
     */
    public function get_rewrite(): VPG_Rewrite
    {
        return $this->rewrite;
    }

    /**
     * Alias for backward compatibility: generates a virtual URL.
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
        return $this->rewrite->get_virtual_url($template, $service_slug, $location_slug);
    }

    /**
     * Set or reset current virtual page data (useful for testing or manual overrides).
     *
     * @param array<string, mixed>|null $data
     */
    public function set_page_data(?array $data): void
    {
        $this->pageData->set_data($data);
    }

    /**
     * Get data for current virtual page context.
     *
     * @return array<string, mixed>|null
     */
    public function get_page_data(): ?array
    {
        return $this->pageData->get_data();
    }

    /**
     * Reset the page data cache (useful for testing).
     */
    public function reset_page_data(): void
    {
        $this->pageData->reset();
    }
}

// ========================================================================
// Global helper functions
// ========================================================================

if (!function_exists("vpg_get_page_data")) {
    /**
     * Retrieve virtual page data for the current request.
     *
     * @return array<string, mixed>|null
     */
    function vpg_get_page_data(): ?array
    {
        return VirtualPageGenerator::get_instance()?->get_page_data();
    }
}

if (!function_exists("vpg_get_virtual_page_data")) {
    /**
     * Alias of vpg_get_page_data.
     *
     * @return array<string, mixed>|null
     */
    function vpg_get_virtual_page_data(): ?array
    {
        return VirtualPageGenerator::get_instance()?->get_page_data();
    }
}

if (!function_exists("vpg_get_canonical_url")) {
    /**
     * Retrieve canonical URL for the current virtual page.
     *
     * @return string|null
     */
    function vpg_get_canonical_url(): ?string
    {
        $data = vpg_get_page_data();
        return $data["canonical_url"] ?? null;
    }
}
