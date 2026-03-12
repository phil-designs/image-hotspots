<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers all ACF field groups for the ih_hotspot post type.
 *
 * Fields:
 *  - ih_image          : Image (main hotspot image)
 *  - ih_hotspots       : Repeater
 *      ├── ih_marker_label   : Text  (tooltip heading)
 *      ├── ih_pos_x          : Number (0–100 %)
 *      ├── ih_pos_y          : Number (0–100 %)
 *      └── ih_description    : WYSIWYG (tooltip body)
 */
class IH_ACF_Fields {

	public static function init() {
		add_action( 'acf/init', [ __CLASS__, 'register_fields' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_ih_get_full_image_url', [ __CLASS__, 'ajax_get_full_image_url' ] );
	}

	/**
	 * AJAX: given an attachment ID, return the full-size (or largest available) URL.
	 * Called by admin.js to avoid displaying the blurry medium-crop thumbnail.
	 */
	public static function ajax_get_full_image_url() {
		check_ajax_referer( 'ih_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Unauthorised', 403 );
		}

		$id = absint( $_POST['attachment_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( 'Invalid ID', 400 );
		}

		// Prefer 'full', fall back to the largest registered size available
		$src = wp_get_attachment_image_url( $id, 'full' );
		if ( ! $src ) {
			wp_send_json_error( 'Attachment not found', 404 );
		}

		wp_send_json_success( [ 'url' => $src ] );
	}

	public static function register_fields() {
		acf_add_local_field_group( [
			'key'      => 'group_ih_hotspot',
			'title'    => 'HotSpot Fields',
			'location' => [
				[ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'ih_hotspot' ] ],
			],
			'position' => 'normal',
			'fields'   => [

				// ── Main image ───────────────────────────────────────────
				[
					'key'           => 'field_ih_image',
					'name'          => 'ih_image',
					'label'         => 'HotSpot Image',
					'type'          => 'image',
					'return_format' => 'array',
					'preview_size'  => 'medium',
					'required'      => 1,
					'instructions'  => 'Upload the image on which hotspot markers will be placed.',
				],

				// ── Interactive placement helper ──────────────────────────
				[
					'key'          => 'field_ih_placement_helper',
					'name'         => 'ih_placement_helper',
					'label'        => 'Marker Placement Preview',
					'type'         => 'message',
					'message'      => '<div id="ih-placement-helper"><p class="description">Save the image above, then reload the page to see the interactive placement preview below. Click on the image to copy X/Y coordinates into the next available hotspot row.</p><div id="ih-preview-wrap"></div></div>',
					'new_lines'    => 'wpautop',
					'esc_html'     => 0,
				],

				// ── Display options ──────────────────────────────────────
				[
					'key'          => 'field_ih_show_numbers',
					'name'         => 'ih_show_numbers',
					'label'        => 'Show Marker Numbers',
					'type'         => 'true_false',
					'default_value'=> 0,
					'ui'           => 1,
					'ui_on_text'   => 'Yes',
					'ui_off_text'  => 'No',
					'instructions' => 'Display a numbered badge on each marker on the frontend so visitors can identify which spot is which.',
					'wrapper'      => [ 'width' => '100' ],
				],

				// ── Repeater ─────────────────────────────────────────────
				[
					'key'          => 'field_ih_hotspots',
					'name'         => 'ih_hotspots',
					'label'        => 'Hotspot Markers',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => 'Add Marker',
					'min'          => 0,
					'sub_fields'   => [

						[
							'key'          => 'field_ih_marker_label',
							'name'         => 'ih_marker_label',
							'label'        => 'Marker Label / Heading',
							'type'         => 'text',
							'required'     => 1,
							'instructions' => 'Shown as the tooltip heading.',
							'wrapper'      => [ 'width' => '100' ],
						],

						[
							'key'           => 'field_ih_marker_thumb',
							'name'          => 'ih_marker_thumb',
							'label'         => 'Heading Image (optional)',
							'type'          => 'image',
							'return_format' => 'array',
							'preview_size'  => 'thumbnail',
							'required'      => 0,
							'instructions'  => 'Small image displayed beside the heading inside the tooltip. Leave blank to show no image.',
							'wrapper'       => [ 'width' => '100' ],
						],

						[
							'key'          => 'field_ih_pos_x',
							'name'         => 'ih_pos_x',
							'label'        => 'X Position (%)',
							'type'         => 'number',
							'required'     => 1,
							'min'          => 0,
							'max'          => 100,
							'step'         => 0.1,
							'default_value'=> 50,
							'instructions' => 'Horizontal position as a percentage from the left edge (0–100).',
							'wrapper'      => [ 'width' => '50' ],
						],

						[
							'key'          => 'field_ih_pos_y',
							'name'         => 'ih_pos_y',
							'label'        => 'Y Position (%)',
							'type'         => 'number',
							'required'     => 1,
							'min'          => 0,
							'max'          => 100,
							'step'         => 0.1,
							'default_value'=> 50,
							'instructions' => 'Vertical position as a percentage from the top edge (0–100).',
							'wrapper'      => [ 'width' => '50' ],
						],

						[
							'key'          => 'field_ih_description',
							'name'         => 'ih_description',
							'label'        => 'Tooltip Description',
							'type'         => 'wysiwyg',
							'tabs'         => 'all',
							'toolbar'      => 'full',
							'media_upload' => 1,
							'instructions' => 'Content displayed inside the tooltip bubble when the marker is clicked.',
							'wrapper'      => [ 'width' => '100' ],
						],

					],
				],

			],
		] );
	}

	public static function enqueue_admin_assets( $hook ) {
		global $post_type;
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || $post_type !== 'ih_hotspot' ) {
			return;
		}
		wp_enqueue_style(
			'ih-admin-css',
			IH_PLUGIN_URL . 'assets/admin/admin.css',
			[],
			IH_VERSION
		);
		wp_enqueue_script(
			'ih-admin-js',
			IH_PLUGIN_URL . 'assets/admin/admin.js',
			[ 'jquery', 'acf-input' ],
			IH_VERSION,
			true
		);
		wp_localize_script( 'ih-admin-js', 'ihAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'ih_admin_nonce' ),
		] );
	}
}
