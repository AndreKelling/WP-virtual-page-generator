<?php

if (!defined("ABSPATH")) {
    exit();
}

class VirtualPageGenerator
{
    private static ?VirtualPageGenerator $instance = null;
    private string $plugin_file;
    private string $plugin_path;

    public static function get_instance(
        string $plugin_file = "",
    ): ?VirtualPageGenerator {
        if (null === self::$instance) {
            self::$instance = new self($plugin_file);
        }
        return self::$instance;
    }

    private function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_path = plugin_dir_path($plugin_file);

        add_action("init", [$this, "register_post_types"]);
        add_action("add_meta_boxes", [$this, "add_location_meta_boxes"]);
        add_action("add_meta_boxes", [$this, "add_service_meta_boxes"]);
        add_action("save_post", [$this, "save_location_meta"]);
        add_action("save_post_vpg_service", [$this, "save_service_meta"]);
        add_action("save_post_vpg_template", [$this, "flush_rules_on_save"]);
        add_action("save_post_vpg_template", [$this, "refresh_tsf_sitemaps"]);
        add_action("save_post_vpg_service", [$this, "refresh_tsf_sitemaps"]);
        add_action("save_post_vpg_location", [$this, "refresh_tsf_sitemaps"]);
        add_action("deleted_post", [$this, "refresh_tsf_sitemaps_on_deletion"]);
        add_action("init", [$this, "add_rewrite_rules"]);
        add_filter("query_vars", [$this, "add_query_vars"]);
        add_filter("template_include", [$this, "handle_virtual_page"]);
        add_action("wp_sitemaps_init", [$this, "init_sitemaps"]);
        add_filter(
            "the_seo_framework_sitemap_additional_urls",
            [$this, "add_to_tsf_sitemap"],
            10,
            2,
        );
        add_action("admin_notices", [$this, "add_admin_docs"]);
        add_action("admin_menu", [$this, "add_admin_menu"]);
        add_filter("rest_search_handlers", [$this, "register_search_handler"]);
        add_action("init", [$this, "register_blocks"]);

        register_activation_hook($this->plugin_file, [$this, "activate"]);
        register_deactivation_hook($this->plugin_file, [$this, "deactivate"]);
    }

    public function activate(): void
    {
        $this->register_post_types();
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function register_post_types(): void
    {
        register_post_type("vpg_service", [
            "labels" => ["name" => "Services", "singular_name" => "Service"],
            "public" => true,
            "publicly_queryable" => false,
            "query_var" => false,
            "rewrite" => false,
            "show_in_menu" => "vpg-main",
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
            "supports" => ["title", "editor"],
            "has_archive" => false,
            "show_in_rest" => true,
        ]);
    }

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
                    $url = $this->get_virtual_url(
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

    public function add_location_meta_boxes(): void
    {
        add_meta_box(
            "vpg_location_service_text",
            "Service Specific Texts",
            [$this, "render_location_meta_box"],
            "vpg_location",
            "normal",
            "high",
        );
    }

    public function add_service_meta_boxes(): void
    {
        add_meta_box(
            "vpg_service_image",
            "Service Image",
            [$this, "render_service_meta_box"],
            "vpg_service",
            "normal",
            "high",
        );
    }

    public function render_location_meta_box($post): void
    {
        $services = get_posts([
            "post_type" => "vpg_service",
            "posts_per_page" => -1,
            "orderby" => "title",
            "order" => "ASC",
        ]);

        wp_nonce_field("vpg_save_location_meta", "vpg_location_nonce");

        if (empty($services)) {
            echo "<p>Please add some services first.</p>";
            return;
        }

        foreach ($services as $service) {
            $text = get_post_meta(
                $post->ID,
                "_vpg_service_text_" . $service->ID,
                true,
            );
            echo '<div style="margin-bottom: 20px;">';
            echo '<label for="vpg_service_text_' .
                $service->ID .
                '"><strong>Text for ' .
                esc_html($service->post_title) .
                ":</strong></label><br>";
            echo '<textarea id="vpg_service_text_' .
                $service->ID .
                '" name="vpg_service_text[' .
                $service->ID .
                ']" rows="4" style="width: 100%;">' .
                esc_textarea($text) .
                "</textarea>";
            echo "</div>";
        }
    }

    public function render_service_meta_box($post): void
    {
        wp_nonce_field("vpg_save_service_meta", "vpg_service_nonce");
        $image_id = get_post_meta($post->ID, "_vpg_service_image", true);
        $image_url = $image_id
            ? wp_get_attachment_image_url($image_id, "full")
            : "";

        echo '<div style="margin-bottom: 20px;">';
        echo '<label for="vpg_service_image"><strong>Service Image:</strong></label><br>';
        echo '<input type="hidden" name="vpg_service_image_id" id="vpg_service_image_id" value="' .
            esc_attr($image_id) .
            '">';
        echo '<input type="url" name="vpg_service_image_url" id="vpg_service_image_url" value="' .
            esc_url($image_url) .
            '" style="width: 100%; margin-bottom: 10px;" placeholder="Enter image URL or upload using the button below">';
        echo '<button type="button" id="vpg_upload_image_button" class="button">Upload Image</button>';
        if ($image_url) {
            echo '<div style="margin-top: 10px;">';
            echo '<img src="' .
                esc_url($image_url) .
                '" style="max-width: 300px; max-height: 200px;" alt="Service Image Preview">';
            echo "</div>";
        }
        echo "</div>";

        // Add media uploader script
        echo "<script>";
        echo 'jQuery(document).ready(function($) {';
        echo "    var frame;";
        echo '    $("#vpg_upload_image_button").on("click", function(e) {';
        echo "        e.preventDefault();";
        echo "        if (frame) {";
        echo "            frame.open();";
        echo "            return;";
        echo "        }";
        echo "        frame = wp.media({";
        echo '            title: "Select Service Image",';
        echo '            button: { text: "Use this image" },';
        echo "            multiple: false";
        echo "        });";
        echo '        frame.on("select", function() {';
        echo '            var attachment = frame.state().get("selection").first().toJSON();';
        echo '            $("#vpg_service_image_id").val(attachment.id);';
        echo '            $("#vpg_service_image_url").val(attachment.url);';
        echo '            $("img").attr("src", attachment.url);';
        echo "        });";
        echo "        frame.open();";
        echo "    });";
        echo "});";
        echo "</script>";
    }

    public function save_location_meta($post_id): void
    {
        if (
            !isset($_POST["vpg_location_nonce"]) ||
            !wp_verify_nonce(
                $_POST["vpg_location_nonce"],
                "vpg_save_location_meta",
            )
        ) {
            return;
        }

        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }

        if (
            isset($_POST["vpg_service_text"]) &&
            is_array($_POST["vpg_service_text"])
        ) {
            foreach ($_POST["vpg_service_text"] as $service_id => $text) {
                update_post_meta(
                    $post_id,
                    "_vpg_service_text_" . intval($service_id),
                    sanitize_textarea_field($text),
                );
            }
        }
    }

    public function save_service_meta($post_id): void
    {
        if (
            !isset($_POST["vpg_service_nonce"]) ||
            !wp_verify_nonce(
                $_POST["vpg_service_nonce"],
                "vpg_save_service_meta",
            )
        ) {
            return;
        }

        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST["vpg_service_image_id"])) {
            $image_id = intval($_POST["vpg_service_image_id"]);
            update_post_meta($post_id, "_vpg_service_image", $image_id);
        }
    }

    public function flush_rules_on_save(): void
    {
        if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
            return;
        }
        $this->add_rewrite_rules();
        flush_rewrite_rules();
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
                    $url = $this->get_virtual_url(
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

    public function add_admin_docs(): void
    {
        $screen = get_current_screen();
        if (!$screen || "edit" !== $screen->base) {
            return;
        }

        if ("vpg_service" === $screen->post_type) {
            echo '<div class="notice notice-info"><p><strong>Services:</strong> Add the services you want to generate pages for. Use these in your templates with <code>{{service}}</code>. You can also upload an image for each service to use with <code>{{service_image}}</code>.</p></div>';
        } elseif ("vpg_location" === $screen->post_type) {
            echo '<div class="notice notice-info"><p><strong>Locations:</strong> Add the locations you want to cover. You can define specific text for each service within each location. Use <code>{{location}}</code> and <code>{{text}}</code> in your templates.</p></div>';
        } elseif ("vpg_template" === $screen->post_type) {
            echo '<div class="notice notice-info"><p><strong>Page Templates:</strong> Create a template for your virtual pages. The title of the template defines the URL structure (e.g., <code>{{service}} in {{location}}</code>). Use placeholders: <code>{{service}}</code>, <code>{{location}}</code>, <code>{{text}}</code>, <code>{{service_image}}</code>.</p></div>';
        }
    }

    public function add_query_vars($vars)
    {
        $vars[] = "vpg_service_slug";
        $vars[] = "vpg_location_slug";
        $vars[] = "vpg_template_id";
        return $vars;
    }

    public function handle_virtual_page($template)
    {
        $template_id = get_query_var("vpg_template_id");
        $service_slug = get_query_var("vpg_service_slug");
        $location_slug = get_query_var("vpg_location_slug");

        if ($template_id || ($service_slug && $location_slug)) {
            return $this->plugin_path . "templates/virtual-page.php";
        }

        return $template;
    }

    public function init_sitemaps(): void
    {
        if (class_exists("WP_Sitemaps_Provider")) {
            $provider = new VPG_Sitemap_Provider();
            wp_register_sitemap_provider("vpgpages", $provider);
        }
    }

    public function register_search_handler($handlers)
    {
        if (class_exists("WP_REST_Search_Handler")) {
            $handlers[] = new VPG_Search_Handler();
        }
        return $handlers;
    }

    public function register_blocks(): void
    {
        wp_register_script(
            "vpg-block-js",
            plugins_url("assets/js/block.js", $this->plugin_file),
            ["wp-blocks", "wp-element", "wp-components", "wp-data"],
            filemtime($this->plugin_path . "assets/js/block.js"),
        );

        register_block_type("vpg/service-list", [
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
    }

    public function render_service_list_block($attributes): string
    {
        $location_id = (int) $attributes["locationId"];
        $template_id = (int) $attributes["templateId"];

        if (!$location_id || !$template_id) {
            return "<p>Please select a location and template in the block settings.</p>";
        }

        $location = get_post($location_id);
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

        $output = '<ul class="vpg-service-list">';
        foreach ($services as $service) {
            $url = $this->get_virtual_url(
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
}
