<?php

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Handles all admin meta box registration, rendering, and saving for VPG post types.
 *
 * Responsibilities:
 * - Location meta boxes (location text, service-specific texts)
 * - Service meta boxes (service text, service image)
 * - Template meta boxes (placeholder reference)
 * - Saving meta for all post types
 * - Admin notices
 */
class VPG_Admin
{
    public function __construct()
    {
        add_action("add_meta_boxes", [$this, "add_location_meta_boxes"]);
        add_action("add_meta_boxes", [$this, "add_service_meta_boxes"]);
        add_action("add_meta_boxes", [$this, "add_template_meta_boxes"]);
        add_action("save_post", [$this, "save_location_meta"]);
        add_action("save_post_vpg_service", [$this, "save_service_meta"]);
        add_action("admin_notices", [$this, "add_admin_docs"]);
    }

    // ========================================================================
    // Location Meta Boxes
    // ========================================================================

    public function add_location_meta_boxes(): void
    {
        add_meta_box(
            "vpg_location_text",
            "Location Text",
            [$this, "render_location_text_meta_box"],
            "vpg_location",
            "normal",
            "high",
        );

        add_meta_box(
            "vpg_location_service_text",
            "Service Specific Texts",
            [$this, "render_location_meta_box"],
            "vpg_location",
            "normal",
            "high",
        );
    }

    public function render_location_text_meta_box($post): void
    {
        wp_nonce_field("vpg_save_location_meta", "vpg_location_nonce");
        $location_text = get_post_meta($post->ID, "_vpg_location_text", true);
        if (empty($location_text)) {
            $location_text = get_post_meta($post->ID, "location_text", true);
        }

        wp_editor((string) $location_text, "location_text", [
            "textarea_name" => "location_text",
            "textarea_rows" => 10,
            "media_buttons" => true,
            "quicktags" => true,
        ]);
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
            $location_for_service_text = get_post_meta(
                $post->ID,
                "_vpg_location_for_service_text_" . $service->ID,
                true,
            );
            echo '<div style="margin-bottom: 20px;">';
            echo '<strong>Location text for ' .
                esc_html($service->post_title) .
                ":</strong><br>";
            wp_editor((string) $location_for_service_text, "vpg_location_for_service_text_" . $service->ID, [
                "textarea_name" => "vpg_location_for_service_text[" . $service->ID . "]",
                "textarea_rows" => 10,
                "media_buttons" => true,
                "quicktags" => true,
            ]);
            echo "</div>";
        }
    }

    // ========================================================================
    // Service Meta Boxes
    // ========================================================================

    public function add_service_meta_boxes(): void
    {
        add_meta_box(
            "vpg_service_text",
            "Service Text",
            [$this, "render_service_text_meta_box"],
            "vpg_service",
            "normal",
            "high",
        );

        add_meta_box(
            "vpg_service_image",
            "Service Image",
            [$this, "render_service_meta_box"],
            "vpg_service",
            "normal",
            "high",
        );
    }

    public function render_service_text_meta_box($post): void
    {
        wp_nonce_field("vpg_save_service_meta", "vpg_service_nonce");
        $service_text = get_post_meta($post->ID, "_vpg_service_text", true);
        if (empty($service_text)) {
            $service_text = get_post_meta($post->ID, "service_text", true);
        }

        wp_editor((string) $service_text, "service_text", [
            "textarea_name" => "service_text",
            "textarea_rows" => 10,
            "media_buttons" => true,
            "quicktags" => true,
        ]);
    }

    public function render_service_meta_box($post): void
    {
        wp_nonce_field("vpg_save_service_meta", "vpg_service_nonce");
        $service_image_id = get_post_meta($post->ID, "_vpg_service_image", true);
        $image_url = $service_image_id
            ? wp_get_attachment_image_url($service_image_id, "full")
            : "";

        echo '<div style="margin-bottom: 20px;">';
        echo '<label for="vpg_service_image"><strong>Service Image:</strong></label><br>';
        echo '<input type="hidden" name="vpg_service_image_id" id="vpg_service_image_id" value="' .
            esc_attr($service_image_id) .
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

    // ========================================================================
    // Template Meta Boxes
    // ========================================================================

    public function add_template_meta_boxes(): void
    {
        add_meta_box(
            "vpg_template_placeholders",
            "Available Placeholders",
            [$this, "render_template_placeholders_meta_box"],
            "vpg_template",
            "side",
            "high",
        );
    }

    public function render_template_placeholders_meta_box($post): void
    {
        echo '<div class="vpg-placeholders-reference">';
        echo '<p>The following placeholders are available in your template content and will be replaced when virtual pages are generated.</p>';
        $this->render_placeholder_table($this->get_placeholder_data());
        echo '<p><strong>Note:</strong> <code>{{service}}</code> and <code>{{location}}</code> can also be used in the <strong>Template Title</strong> to define the URL structure.</p>';
        echo '</div>';
    }

    private function get_placeholder_data(): array
    {
        return [
            [
                "placeholder" => "{{service}}",
                "description" => "The name of the current service.",
                "source" => "Service",
            ],
            [
                "placeholder" => "{{location}}",
                "description" => "The name of the current location.",
                "source" => "Location",
            ],
            [
                "placeholder" => "{{service_text}}",
                "description" => "Rich text description of the service (WYSIWYG).",
                "source" => "Service",
            ],
            [
                "placeholder" => "{{service_image}}",
                "description" => "The service's uploaded image. Renders as an <code>&lt;img&gt;</code> tag in templates.",
                "source" => "Service",
            ],
            [
                "placeholder" => "{{location_text}}",
                "description" => "General rich text description of the location (WYSIWYG).",
                "source" => "Location",
            ],
            [
                "placeholder" => "{{location_for_service_text}}",
                "description" => "Rich text for the current service within this location (WYSIWYG, one per service).",
                "source" => "Location",
            ],
        ];
    }

    private function render_placeholder_table($placeholders): void
    {
        foreach ($placeholders as $item) {
            echo '<div class="vpg-placeholder-item" style="margin-bottom: 10px; padding: 8px; background: #f6f7f9; border-radius: 4px;">';
            echo '<code style="font-size: 13px;">' . esc_html($item["placeholder"]) . '</code>';
            echo '<p style="margin: 4px 0 0 0; font-size: 12px; color: #646970;">' . $item["description"] . '</p>';
            echo '<span style="font-size: 11px; color: #8c8f94;">From: ' . esc_html($item["source"]) . '</span>';
            echo '</div>';
        }
    }

    // ========================================================================
    // Meta Saving
    // ========================================================================

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

        if (function_exists("current_user_can") && !current_user_can("edit_post", $post_id)) {
            return;
        }

        if (isset($_POST["location_text"])) {
            $location_text = wp_kses_post(wp_unslash($_POST["location_text"]));
            update_post_meta($post_id, "_vpg_location_text", $location_text);
            update_post_meta($post_id, "location_text", $location_text);
        } elseif (isset($_POST["vpg_location_text"]) && is_string($_POST["vpg_location_text"])) {
            $location_text = wp_kses_post(wp_unslash($_POST["vpg_location_text"]));
            update_post_meta($post_id, "_vpg_location_text", $location_text);
            update_post_meta($post_id, "location_text", $location_text);
        }

        if (
            isset($_POST["vpg_location_for_service_text"]) &&
            is_array($_POST["vpg_location_for_service_text"])
        ) {
            foreach ($_POST["vpg_location_for_service_text"] as $service_id => $text) {
                $location_for_service_text = wp_kses_post(wp_unslash($text));
                update_post_meta(
                    $post_id,
                    "_vpg_location_for_service_text_" . intval($service_id),
                    $location_for_service_text,
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

        if (function_exists("current_user_can") && !current_user_can("edit_post", $post_id)) {
            return;
        }

        if (isset($_POST["vpg_service_image_id"])) {
            $service_image_id = intval($_POST["vpg_service_image_id"]);
            update_post_meta($post_id, "_vpg_service_image", $service_image_id);
        }

        if (isset($_POST["service_text"])) {
            $service_text = wp_kses_post(wp_unslash($_POST["service_text"]));
            update_post_meta($post_id, "_vpg_service_text", $service_text);
            update_post_meta($post_id, "service_text", $service_text);
        } elseif (isset($_POST["vpg_service_text"]) && is_string($_POST["vpg_service_text"])) {
            $service_text = wp_kses_post(wp_unslash($_POST["vpg_service_text"]));
            update_post_meta($post_id, "_vpg_service_text", $service_text);
            update_post_meta($post_id, "service_text", $service_text);
        }
    }

    // ========================================================================
    // Admin Notices
    // ========================================================================

    public function add_admin_docs(): void
    {
        $screen = get_current_screen();
        if (!$screen || "edit" !== $screen->base) {
            return;
        }

        if ("vpg_service" === $screen->post_type) {
            echo '<div class="notice notice-info"><p><strong>Services:</strong> Add the services you want to generate pages for. Use these in your templates with <code>{{service}}</code>. You can also upload an image for each service to use with <code>{{service_image}}</code> and add service text to use with <code>{{service_text}}</code>.</p></div>';
        } elseif ("vpg_location" === $screen->post_type) {
            echo '<div class="notice notice-info"><p><strong>Locations:</strong> Add the locations you want to cover. You can add general location text using <code>{{location_text}}</code> and service-specific text for each location using <code>{{location_for_service_text}}</code>.</p></div>';
        } elseif ("vpg_template" === $screen->post_type) {
            echo '<div class="notice notice-info"><p><strong>Page Templates:</strong> Create a template for your virtual pages. The title of the template defines the URL structure (e.g., <code>{{service}} in {{location}}</code>). Use placeholders: <code>{{service}}</code>, <code>{{location}}</code>, <code>{{location_text}}</code>, <code>{{location_for_service_text}}</code>, <code>{{service_text}}</code>, <code>{{service_image}}</code>.</p></div>';
        }
    }
}
