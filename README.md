# Virtual Page Generator

Virtual Page Generator is a WordPress plugin that dynamically generates virtual pages based on the Cartesian product of **Services** and **Locations** with **Page Templates**. It allows you to create thousands of unique, SEO-friendly pages without cluttering your WordPress database with physical posts.

## Inspired by

Tested these plugins but none was sufficient for my use-case:

- https://wordpress.org/plugins/multiple-pages-generator-by-porthas/
- https://wordpress.org/plugins/page-generator/
- https://wordpress.org/plugins/lpagery/

## Created with

Junie (JetBrains) and Gemini 3.7 Flash Model

## Features

- **Virtual Page Generation**: Automatically creates pages for every combination of Service, Location, and Template.
- **Flexible URL Structures**: The URL of each virtual page is determined by the title of its Page Template.
- **Gutenberg Support**: Use the WordPress Block Editor to design your Page Templates.
- **Dynamic Content**: Use placeholders to inject service and location data into your templates.
- **WYSIWYG Text Fields**: Add rich formatted text to Services, Locations, and service-specific Location entries.
- **Service Images**: Upload images for each service and display them using the `{{service_image}}` placeholder.
- **Sitemap Integration**: Full support for WordPress Core Sitemaps and The SEO Framework (TSF).
- **Admin Overview**: A dedicated dashboard to view all generated URLs.
- **Link Integration**: Search and add virtual pages directly in the Gutenberg block editor's link picker.
- **Service List Block**: A Gutenberg block to list all services as links for a selected location.

## How It Works

The plugin uses the "Cartesian Product" logic.
If you have:
- 3 Services (e.g., *Mowing*, *Pruning*, *Planting*)
- 2 Locations (e.g., *Berlin*, *Munich*)
- 1 Page Template (e.g., *{{service}} in {{location}}*)

The plugin will generate **6 virtual pages**:
1. `/mowing-in-berlin/`
2. `/mowing-in-munich/`
3. `/pruning-in-berlin/`
4. `/pruning-in-munich/`
5. `/planting-in-berlin/`
6. `/planting-in-munich/`

## Configuration

### 1. Services
Go to **Page Generator > Services** to add your services. These represent the primary topics of your virtual pages.
- **Service Text**: WYSIWYG editor for a general description of the service. Available in templates as `{{service_text}}`.
- **Service Image**: Upload an image for the service. Available in templates as `{{service_image}}`.

### 2. Locations
Go to **Page Generator > Locations** to add your target areas.
- **Location Text**: General WYSIWYG editor for a description of the location. Available in templates as `{{location_text}}`.
- **Service Specific Texts**: A WYSIWYG editor for each Service, allowing you to define location-specific content per service. Available in templates as `{{location_for_service_text}}`.

### 3. Page Templates
Go to **Page Generator > Page Templates** to design the layout of your virtual pages.
- **Title**: The title defines the URL. You can use `{{service}}` and `{{location}}` in the title (e.g., `{{service}}/in/{{location}}`).
- **Content**: Use the Gutenberg editor to build your page. Use placeholders to make the content dynamic.

## Placeholders

You can use the following placeholders in your **Template Content**. The `{{service}}` and `{{location}}` placeholders can also be used in the **Template Title** to define the URL structure.

### Basic Placeholders

| Placeholder | Description |
|---|---|
| `{{service}}` | The name of the current Service. |
| `{{location}}` | The name of the current Location. |

### Service Fields

Configured on the **Service** edit screen.

| Placeholder | Field | Description |
|---|---|---|
| `{{service_text}}` | Service Text | Rich text description of the service (WYSIWYG). |
| `{{service_image}}` | Service Image | The service's uploaded image. Use in PHP templates to generate the image HTML. |

### Location Fields

Configured on the **Location** edit screen.

| Placeholder | Field | Description |
|---|---|---|
| `{{location_text}}` | Location Text | General rich text description of the location (WYSIWYG). |
| `{{location_for_service_text}}` | Service Specific Texts | Rich text for the current Service within this Location (WYSIWYG, one per Service). |

## Overriding the Template in Your Theme

You can override the default rendering of virtual pages by providing a custom template file in your active theme or child theme.

The plugin checks for custom templates using WordPress `locate_template()` in the following order:

1. `virtual-page.php` (theme root)
2. `templates/virtual-page.php`
3. `virtual-page-generator/virtual-page.php`

If any of these files exist in your theme, it will be loaded instead of the plugin's default `templates/virtual-page.php`.

### Custom Template Implementation Example

You can use the helper function `vpg_get_page_data()` to access the pre-processed virtual page context (with all placeholders already replaced and metadata queried):

```php
<?php
/**
 * Custom Virtual Page Template (e.g. virtual-page.php)
 */

$data = function_exists('vpg_get_page_data') ? vpg_get_page_data() : null;
if (empty($data)) {
    return;
}

get_header();
?>

<main id="primary" class="site-main">
    <article class="virtual-page">
        <header class="entry-header">
            <?php if (!empty($data['image_id'])) : ?>
                <div class="entry-header__image-wrapper">
                    <?php echo wp_get_attachment_image($data['image_id'], 'full'); ?>
                </div>
            <?php endif; ?>
            <h1 class="entry-title"><?php echo esc_html($data['title']); ?></h1>
        </header>

        <div class="entry-content">
            <?php echo apply_filters('the_content', $data['content']); ?>
        </div>
    </article>
</main>

<?php
get_footer();
```

#### Available Data Keys in `vpg_get_page_data()`:

| Key | Type | Description |
|---|---|---|
| `service` | `WP_Post` | The current Service post object. |
| `location` | `WP_Post` | The current Location post object. |
| `template` / `vpg_template` | `WP_Post` | The Page Template post object. |
| `title` | `string` | Dynamic page title with `{{service}}` and `{{location}}` substituted. |
| `content` | `string` | Dynamic page content with all placeholders substituted. |
| `location_text` | `string` | General rich text from the Location's `location_text` field. |
| `location_for_service_text` | `string` | Rich text from the Location for the current Service. |
| `service_text` | `string` | Rich text from the Service's `service_text` field. |
| `service_image_id` | `int\|null` | Service attachment image ID (or null). |
| `service_image_html` | `string` | Rendered HTML image tag for the service image. |

## URL Routing

The plugin automatically registers rewrite rules based on your Page Templates.
- If a template title contains placeholders, the URL will match that format.
- If placeholders are missing from the title, the plugin will append them to the end of the slug to ensure every combination has a unique URL.
- **Important**: Whenever you create or update a Page Template, the rewrite rules are automatically flushed to apply the new URL structure.

## Sitemap Integration

Virtual pages are automatically indexed for search engines:
- **WordPress Core Sitemaps**: Registered under the `vpgpages` provider.
- **The SEO Framework (TSF)**: Automatically integrated into the main sitemap.
- **Cache Refresh**: The sitemap cache is automatically cleared when you add, edit, or delete Services, Locations, or Templates.

## Admin Dashboard

Visit **Page Generator > Generated Pages** to see a full list of all active virtual URLs, grouped by template. This view helps you verify that your routing and placeholders are working as expected.

## Link Search Integration

You can easily link to virtual pages within the Gutenberg editor. When you use the link picker, simply type the name of a Service or Location. The generated virtual pages will appear in the search results, allowing you to select them just like any regular post or page.

## Gutenberg Block: Services by Location

The plugin provides a custom Gutenberg block called **VPG Services by Location**.
- You can find it in the block inserter under the **Widgets** category.
- In the block settings (sidebar), you can select a **Location** and a **Page Template**.
- The block will automatically render a list of links to all Services for that specific Location, using the selected Template's URL structure.

## Requirements

- **WordPress**: 5.5 or higher (for Core Sitemaps support).
- **PHP**: 7.4 or higher.
- **The SEO Framework (Optional)**: If installed, the plugin will automatically add virtual pages to its sitemap.
