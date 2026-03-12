<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugin settings page.
 *
 * Options stored in wp_options as 'ih_settings':
 *   marker_type      – 'fa_icon' | 'custom_image'
 *   fa_icon          – FontAwesome icon class, e.g. "fa-map-marker-alt"
 *   marker_color     – hex colour for the FA icon
 *   marker_hover     – hex colour on hover/active (FA icon only)
 *   marker_size      – integer px size for the marker (both types)
 *   custom_image_id  – attachment ID of the custom marker image
 *   custom_image_url – URL of the custom marker image
 *   tooltip_bg       – hex colour for the tooltip background
 *   tooltip_text     – hex colour for the tooltip text
 *   custom_css       – freeform CSS string
 */
class IH_Settings {

	const OPTION_KEY = 'ih_settings';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( IH_PLUGIN_FILE ), [ __CLASS__, 'action_links' ] );
	}

	public static function action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=ih_hotspot&page=ih-settings' ) ) . '">'
		                 . esc_html__( 'Settings', 'image-hotspots' )
		                 . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	// ── Defaults ──────────────────────────────────────────────────────────────
	public static function defaults() {
		return [
			'marker_type'      => 'fa_icon',
			'fa_icon'          => 'fa-map-marker-alt',
			'marker_color'     => '#e74c3c',
			'marker_hover'     => '#c0392b',
			'marker_size'      => 36,
			'custom_image_id'  => 0,
			'custom_image_url' => '',
			'tooltip_bg'       => '#ffffff',
			'tooltip_text'     => '#333333',
			'custom_css'       => '',
		];
	}

	public static function get( $key = null ) {
		$options = wp_parse_args( get_option( self::OPTION_KEY, [] ), self::defaults() );
		return $key ? ( $options[ $key ] ?? null ) : $options;
	}

	// ── Menu ──────────────────────────────────────────────────────────────────
	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=ih_hotspot',
			__( 'HotSpots Settings', 'image-hotspots' ),
			__( 'Settings', 'image-hotspots' ),
			'manage_options',
			'ih-settings',
			[ __CLASS__, 'render_page' ]
		);
	}

	// ── Settings API ──────────────────────────────────────────────────────────
	public static function register_settings() {
		register_setting(
			'ih_settings_group',
			self::OPTION_KEY,
			[ 'sanitize_callback' => [ __CLASS__, 'sanitize' ] ]
		);
	}

	public static function sanitize( $input ) {
		$clean = self::defaults();

		$clean['marker_type']      = in_array( $input['marker_type'] ?? '', [ 'fa_icon', 'custom_image' ], true )
		                             ? $input['marker_type'] : 'fa_icon';
		$clean['fa_icon']          = sanitize_text_field( $input['fa_icon'] ?? $clean['fa_icon'] );
		$clean['marker_color']     = sanitize_hex_color( $input['marker_color'] ?? '' ) ?: $clean['marker_color'];
		$clean['marker_hover']     = sanitize_hex_color( $input['marker_hover'] ?? '' ) ?: $clean['marker_hover'];
		$clean['marker_size']      = max( 16, min( 120, absint( $input['marker_size'] ?? $clean['marker_size'] ) ) );
		$clean['custom_image_id']  = absint( $input['custom_image_id'] ?? 0 );
		$clean['custom_image_url'] = esc_url_raw( $input['custom_image_url'] ?? '' );
		$clean['tooltip_bg']       = sanitize_hex_color( $input['tooltip_bg']   ?? '' ) ?: $clean['tooltip_bg'];
		$clean['tooltip_text']     = sanitize_hex_color( $input['tooltip_text'] ?? '' ) ?: $clean['tooltip_text'];
		$clean['custom_css']       = wp_strip_all_tags( $input['custom_css'] ?? '' );

		return $clean;
	}

	// ── Assets ────────────────────────────────────────────────────────────────
	public static function enqueue_assets( $hook ) {
		if ( $hook !== 'ih_hotspot_page_ih-settings' ) return;

		// FontAwesome 5 — needed so the icon preview actually renders
		wp_enqueue_style(
			'font-awesome-5',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
			[],
			'5.15.4'
		);

		// WP colour picker
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// WP media uploader (for custom marker image)
		wp_enqueue_media();

		wp_add_inline_script( 'wp-color-picker', self::settings_js() );
	}

	private static function settings_js() {
		return <<<'JS'
jQuery(function($){

    // Colour pickers
    $('.ih-color-picker').wpColorPicker({
        change: function(event, ui){
            // live-update the FA icon preview colour
            if ($(this).attr('id') === 'ih_marker_color') {
                $('#ih-icon-preview').css('color', ui.color.toString());
            }
        }
    });

    // FA icon select live preview
    $('#ih_fa_icon').on('change', function(){
        $('#ih-icon-preview').removeClass().addClass('fas ' + this.value);
    });

    // Marker type toggle
    function toggleMarkerType() {
        var type = $('input[name="ih_settings[marker_type]"]:checked').val();
        if (type === 'custom_image') {
            $('#ih-fa-section').hide();
            $('#ih-custom-image-section').show();
        } else {
            $('#ih-fa-section').show();
            $('#ih-custom-image-section').hide();
        }
    }
    $('input[name="ih_settings[marker_type]"]').on('change', toggleMarkerType);
    toggleMarkerType();

    // WP media uploader for custom marker image
    var mediaFrame;
    $('#ih-upload-custom-marker').on('click', function(e){
        e.preventDefault();
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({
            title:    'Choose Marker Image',
            button:   { text: 'Use as Marker' },
            multiple: false
        });
        mediaFrame.on('select', function(){
            var attachment = mediaFrame.state().get('selection').first().toJSON();
            $('#ih_custom_image_id').val(attachment.id);
            $('#ih_custom_image_url').val(attachment.url);
            $('#ih-custom-image-preview').html(
                '<img src="' + attachment.url + '" style="max-height:60px;max-width:120px;display:block;margin-bottom:6px;">'
            );
            $('#ih-remove-custom-marker').show();
        });
        mediaFrame.open();
    });

    $('#ih-remove-custom-marker').on('click', function(e){
        e.preventDefault();
        $('#ih_custom_image_id').val('');
        $('#ih_custom_image_url').val('');
        $('#ih-custom-image-preview').empty();
        $(this).hide();
    });

});
JS;
	}

	// ── Page render ───────────────────────────────────────────────────────────
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$opts = self::get();

		$icon_options = [
			'fa-map-marker-alt' => 'Map Marker Alt',
			'fa-map-marker'     => 'Map Marker',
			'fa-map-pin'        => 'Map Pin',
			'fa-circle'         => 'Circle',
			'fa-dot-circle'     => 'Dot Circle',
			'fa-bullseye'       => 'Bullseye',
			'fa-crosshairs'     => 'Crosshairs',
			'fa-info-circle'    => 'Info Circle',
			'fa-plus-circle'    => 'Plus Circle',
			'fa-star'           => 'Star',
			'fa-heart'          => 'Heart',
			'fa-flag'           => 'Flag',
			'fa-thumbtack'      => 'Thumbtack / Pin',
			'fa-hand-point-up'  => 'Hand Point Up',
		];

		$custom_image_url = $opts['custom_image_url'];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Image HotSpots — Settings', 'image-hotspots' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'ih_settings_group' ); ?>

				<table class="form-table" role="presentation">

					<!-- ── Marker Type ── -->
					<tr>
						<th scope="row"><?php esc_html_e( 'Marker Type', 'image-hotspots' ); ?></th>
						<td>
							<label style="margin-right:20px;">
								<input type="radio"
								       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[marker_type]"
								       value="fa_icon"
								       <?php checked( $opts['marker_type'], 'fa_icon' ); ?>>
								<?php esc_html_e( 'FontAwesome Icon', 'image-hotspots' ); ?>
							</label>
							<label>
								<input type="radio"
								       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[marker_type]"
								       value="custom_image"
								       <?php checked( $opts['marker_type'], 'custom_image' ); ?>>
								<?php esc_html_e( 'Custom Image', 'image-hotspots' ); ?>
							</label>
						</td>
					</tr>

					<!-- ── FA Icon section (shown when marker_type = fa_icon) ── -->
					<tr id="ih-fa-section">
						<th scope="row">
							<label for="ih_fa_icon"><?php esc_html_e( 'Icon', 'image-hotspots' ); ?></label>
						</th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fa_icon]" id="ih_fa_icon">
								<?php foreach ( $icon_options as $class => $label ) : ?>
									<option value="<?php echo esc_attr( $class ); ?>" <?php selected( $opts['fa_icon'], $class ); ?>>
										<?php echo esc_html( $label ); ?> (<?php echo esc_html( $class ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'FontAwesome 5 Free icons.', 'image-hotspots' ); ?></p>
							<!-- Live icon preview -->
							<p style="margin-top:10px;">
								<i id="ih-icon-preview"
								   class="fas <?php echo esc_attr( $opts['fa_icon'] ); ?>"
								   style="font-size:<?php echo esc_attr( $opts['marker_size'] ); ?>px;color:<?php echo esc_attr( $opts['marker_color'] ); ?>;"></i>
								<span style="font-size:12px;color:#666;margin-left:8px;"><?php esc_html_e( 'Live preview', 'image-hotspots' ); ?></span>
							</p>
						</td>
					</tr>

					<!-- ── Custom image section (shown when marker_type = custom_image) ── -->
					<tr id="ih-custom-image-section">
						<th scope="row"><?php esc_html_e( 'Marker Image', 'image-hotspots' ); ?></th>
						<td>
							<input type="hidden" id="ih_custom_image_id"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_image_id]"
							       value="<?php echo esc_attr( $opts['custom_image_id'] ); ?>">
							<input type="hidden" id="ih_custom_image_url"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_image_url]"
							       value="<?php echo esc_attr( $custom_image_url ); ?>">

							<div id="ih-custom-image-preview">
								<?php if ( $custom_image_url ) : ?>
									<img src="<?php echo esc_url( $custom_image_url ); ?>"
									     style="max-height:60px;max-width:120px;display:block;margin-bottom:6px;">
								<?php endif; ?>
							</div>

							<button type="button" id="ih-upload-custom-marker" class="button">
								<?php esc_html_e( 'Choose Image', 'image-hotspots' ); ?>
							</button>
							<button type="button" id="ih-remove-custom-marker" class="button"
							        style="<?php echo $custom_image_url ? '' : 'display:none;'; ?>margin-left:4px;">
								<?php esc_html_e( 'Remove', 'image-hotspots' ); ?>
							</button>

							<p class="description" style="margin-top:6px;">
								<?php esc_html_e( 'Upload a PNG, SVG, or GIF to use as the marker. Transparent PNGs and SVGs work best. Use the size field below to control its dimensions.', 'image-hotspots' ); ?>
							</p>
						</td>
					</tr>

					<!-- ── Marker size (applies to both types) ── -->
					<tr>
						<th scope="row">
							<label for="ih_marker_size"><?php esc_html_e( 'Marker Size (px)', 'image-hotspots' ); ?></label>
						</th>
						<td>
							<input type="number" id="ih_marker_size"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[marker_size]"
							       value="<?php echo esc_attr( $opts['marker_size'] ); ?>"
							       min="16" max="120" step="1" style="width:80px;">
							<p class="description"><?php esc_html_e( 'Width/font-size of the marker in pixels (16–120).', 'image-hotspots' ); ?></p>
						</td>
					</tr>

					<!-- ── Marker colour (FA only) ── -->
					<tr>
						<th scope="row">
							<label for="ih_marker_color"><?php esc_html_e( 'Marker Colour', 'image-hotspots' ); ?></label>
						</th>
						<td>
							<input type="text" class="ih-color-picker"
							       id="ih_marker_color"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[marker_color]"
							       value="<?php echo esc_attr( $opts['marker_color'] ); ?>">
							<p class="description"><?php esc_html_e( 'Applied to FontAwesome icons. For custom images, use a transparent PNG/SVG instead.', 'image-hotspots' ); ?></p>
						</td>
					</tr>

					<!-- ── Marker hover colour ── -->
					<tr>
						<th scope="row">
							<label for="ih_marker_hover"><?php esc_html_e( 'Marker Hover / Active Colour', 'image-hotspots' ); ?></label>
						</th>
						<td>
							<input type="text" class="ih-color-picker"
							       id="ih_marker_hover"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[marker_hover]"
							       value="<?php echo esc_attr( $opts['marker_hover'] ); ?>">
						</td>
					</tr>

					<!-- ── Tooltip background ── -->
					<tr>
						<th scope="row">
							<label for="ih_tooltip_bg"><?php esc_html_e( 'Tooltip Background Colour', 'image-hotspots' ); ?></label>
						</th>
						<td>
							<input type="text" class="ih-color-picker"
							       id="ih_tooltip_bg"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[tooltip_bg]"
							       value="<?php echo esc_attr( $opts['tooltip_bg'] ); ?>">
						</td>
					</tr>

					<!-- ── Tooltip text colour ── -->
					<tr>
						<th scope="row">
							<label for="ih_tooltip_text"><?php esc_html_e( 'Tooltip Text Colour', 'image-hotspots' ); ?></label>
						</th>
						<td>
							<input type="text" class="ih-color-picker"
							       id="ih_tooltip_text"
							       name="<?php echo esc_attr( self::OPTION_KEY ); ?>[tooltip_text]"
							       value="<?php echo esc_attr( $opts['tooltip_text'] ); ?>">
						</td>
					</tr>

					<!-- ── Custom CSS ── -->
					<tr>
						<th scope="row">
							<label for="ih_custom_css"><?php esc_html_e( 'Custom CSS', 'image-hotspots' ); ?></label>
						</th>
						<td>
							<textarea id="ih_custom_css"
							          name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_css]"
							          rows="12" cols="60"
							          class="large-text code"
							          spellcheck="false"
							          placeholder="/* Add your custom CSS overrides here */"><?php echo esc_textarea( $opts['custom_css'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Output in a &lt;style&gt; tag on every page that contains a hotspot shortcode.', 'image-hotspots' ); ?>
							</p>
						</td>
					</tr>

				</table>

				<?php submit_button( __( 'Save Settings', 'image-hotspots' ) ); ?>
			</form>
		</div>
		<?php
	}
}
