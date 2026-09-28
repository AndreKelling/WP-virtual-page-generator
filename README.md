# Virtual Page Generator

Virtual Page Generator is a WordPress plugin that dynamically generates virtual pages based on the Cartesian product of **Services** and **Locations** with **Page Templates**. It allows you to create thousands of unique, SEO-friendly pages without cluttering your WordPress database with physical posts.

## Features

- **Virtual Page Generation**: Automatically creates pages for every combination of Service, Location, and Template.
- **Flexible URL Structures**: The URL of each virtual page is determined by the title of its Page Template.
- **Gutenberg Support**: Use the WordPress Block Editor to design your Page Templates.
- **Dynamic Content**: Use placeholders to inject service and location data into your templates.
- **Service-Specific Location Text**: Define unique descriptions for each service within a specific location.
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

### 2. Locations
Go to **Page Generator > Locations** to add your target areas.
- In the Location editor, you will find a **Service Specific Texts** meta box.
- Here, you can enter unique content for every Service you have created.
- This content can be displayed in the template using the `{{text}}` placeholder.

### 3. Page Templates
Go to **Page Generator > Page Templates** to design the layout of your virtual pages.
- **Title**: The title defines the URL. You can use `{{service}}` and `{{location}}` in the title (e.g., `{{service}}/in/{{location}}`).
- **Content**: Use the Gutenberg editor to build your page. Use placeholders to make the content dynamic.

## Placeholders

You can use the following placeholders in both the **Template Title** and **Template Content**:

- `{{service}}`: The name of the current Service.
- `{{location}}`: The name of the current Location.
- `{{text}}`: The service-specific text defined in the Location settings for the current Service.

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
