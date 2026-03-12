<?php
/**
 * Plugin Name: Image Hotspots
 * Plugin URI:  http://www.phildesigns.com
 * Description: Add clickable hotspot markers to images using ACF Pro repeater fields with customisable tooltips and marker styles.
 * Version:     1.0.0
 * Author:      phil.designs | Phillip De Vita
 * Author URI:  http://www.phildesigns.com
 * License:     GPL2
 * Text Domain: image-hotspots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IH_VERSION',     '1.0.0' );
define( 'IH_PLUGIN_FILE', __FILE__ );
define( 'IH_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'IH_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// ACF Pro dependency check
// ---------------------------------------------------------------------------
add_action( 'admin_notices', 'ih_acf_missing_notice' );
function ih_acf_missing_notice() {
	if ( ih_acf_is_active() ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'Image Hotspots requires Advanced Custom Fields PRO to be installed and activated.', 'image-hotspots' );
	echo '</p></div>';
}

function ih_acf_is_active() {
	return class_exists( 'ACF' ) && defined( 'ACF_PRO' );
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
add_action( 'plugins_loaded', 'ih_init' );
function ih_init() {
	if ( ! ih_acf_is_active() ) {
		return; // ACF Pro not present — stop here (notice already shown)
	}

	require_once IH_PLUGIN_DIR . 'includes/class-post-type.php';
	require_once IH_PLUGIN_DIR . 'includes/class-acf-fields.php';
	require_once IH_PLUGIN_DIR . 'includes/class-settings.php';
	require_once IH_PLUGIN_DIR . 'includes/class-frontend.php';

	IH_Post_Type::init();
	IH_ACF_Fields::init();
	IH_Settings::init();
	IH_Frontend::init();
}
