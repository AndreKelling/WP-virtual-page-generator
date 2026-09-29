<?php
/**
 * Plugin Name: Virtual Page Generator
 * Description: Generates virtual pages based on the cartesian product of services and locations.
 * Version: 1.0
 * Author: André Kelling
 * Author URI: https://andrekelling.de
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include classes
require_once plugin_dir_path( __FILE__ ) . 'includes/class-virtual-page-generator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-vpg-sitemap-provider.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-vpg-search-handler.php';

// Initialize the plugin
VirtualPageGenerator::get_instance( __FILE__ );
