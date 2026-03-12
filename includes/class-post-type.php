<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IH_Post_Type {

	public static function init() {
		add_action( 'init',       [ __CLASS__, 'register' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_shortcode_metabox' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_metabox_assets' ] );
	}

	public static function register() {
		$labels = [
			'name'               => _x( 'HotSpots', 'Post type general name', 'image-hotspots' ),
			'singular_name'      => _x( 'HotSpot', 'Post type singular name', 'image-hotspots' ),
			'add_new'            => __( 'Add New HotSpot', 'image-hotspots' ),
			'add_new_item'       => __( 'Add New HotSpot', 'image-hotspots' ),
			'edit_item'          => __( 'Edit HotSpot', 'image-hotspots' ),
			'new_item'           => __( 'New HotSpot', 'image-hotspots' ),
			'view_item'          => __( 'View HotSpot', 'image-hotspots' ),
			'search_items'       => __( 'Search HotSpots', 'image-hotspots' ),
			'not_found'          => __( 'No HotSpots found', 'image-hotspots' ),
			'not_found_in_trash' => __( 'No HotSpots found in trash', 'image-hotspots' ),
			'menu_name'          => __( 'HotSpots', 'image-hotspots' ),
		];

		register_post_type( 'ih_hotspot', [
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-location-alt',
			'supports'           => [ 'title' ],
			'has_archive'        => false,
			'rewrite'            => false,
			'show_in_rest'       => false,
		] );
	}

	// ── Shortcode meta box ────────────────────────────────────────────────────

	public static function add_shortcode_metabox() {
		add_meta_box(
			'ih_shortcode_box',
			__( 'Use This HotSpot', 'image-hotspots' ),
			[ __CLASS__, 'render_shortcode_metabox' ],
			'ih_hotspot',
			'side',
			'high'
		);
	}

	public static function render_shortcode_metabox( $post ) {
		// Only show after the post has been saved at least once (has a real ID)
		if ( ! $post->ID || $post->post_status === 'auto-draft' ) {
			echo '<p class="description">' . esc_html__( 'Save the post first to get your shortcode.', 'image-hotspots' ) . '</p>';
			return;
		}

		$shortcode = '[image_hotspot id="' . $post->ID . '"]';
		?>
		<p class="description" style="margin-bottom:8px;">
			<?php esc_html_e( 'Paste this shortcode into any page or post to display this hotspot.', 'image-hotspots' ); ?>
		</p>

		<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
			<input
				id="ih-shortcode-value"
				type="text"
				readonly
				value="<?php echo esc_attr( $shortcode ); ?>"
				style="flex:1;min-width:0;font-family:monospace;font-size:13px;background:#f6f7f7;"
				onclick="this.select();"
			>
			<button
				type="button"
				id="ih-copy-shortcode"
				class="button button-primary"
				data-shortcode="<?php echo esc_attr( $shortcode ); ?>"
				style="white-space:nowrap;"
			>
				<span class="dashicons dashicons-clipboard" style="margin-top:3px;"></span>
				<?php esc_html_e( 'Copy', 'image-hotspots' ); ?>
			</button>
		</div>

		<p id="ih-copy-notice" style="display:none;color:#2e7d32;margin-top:6px;font-size:12px;">
			<?php esc_html_e( 'Shortcode copied to clipboard!', 'image-hotspots' ); ?>
		</p>

		<hr style="margin:12px 0;">
		<p class="description" style="font-size:11px;">
			<?php esc_html_e( 'You can also use:', 'image-hotspots' ); ?>
			<code>&lt;?php echo do_shortcode( \'<?php echo esc_html( $shortcode ); ?>\' ); ?&gt;</code>
		</p>
		<?php
	}

	public static function enqueue_metabox_assets( $hook ) {
		global $post_type;
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || $post_type !== 'ih_hotspot' ) {
			return;
		}
		// Inline script — tiny, no need for a separate file
		wp_add_inline_script( 'jquery', self::copy_button_script() );
	}

	private static function copy_button_script() {
		return <<<'JS'
jQuery(function($){
    $('#ih-copy-shortcode').on('click', function(){
        var shortcode = $(this).data('shortcode');
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(shortcode).then(function(){
                showCopyNotice();
            });
        } else {
            // Fallback for non-HTTPS / older browsers
            var $input = $('#ih-shortcode-value');
            $input[0].select();
            document.execCommand('copy');
            showCopyNotice();
        }
    });

    function showCopyNotice(){
        var $notice = $('#ih-copy-notice');
        $notice.stop(true).fadeIn(150);
        setTimeout(function(){ $notice.fadeOut(600); }, 2500);
        var $btn = $('#ih-copy-shortcode');
        $btn.text(' Copied!').prepend('<span class="dashicons dashicons-yes" style="margin-top:3px;"></span> ');
        setTimeout(function(){
            $btn.html('<span class="dashicons dashicons-clipboard" style="margin-top:3px;"></span> Copy');
        }, 2500);
    }
});
JS;
	}
}
