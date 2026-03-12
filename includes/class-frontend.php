<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Shortcode: [image_hotspot id="123"]
 */
class IH_Frontend {

	private static $enqueued = false;

	public static function init() {
		add_shortcode( 'image_hotspot', [ __CLASS__, 'shortcode' ] );
		add_action( 'wp_head', [ __CLASS__, 'output_dynamic_css' ] );
	}

	// ── Dynamic CSS vars from settings ────────────────────────────────────────
	public static function output_dynamic_css() {
		$opts       = IH_Settings::get();
		$custom_css = trim( $opts['custom_css'] );
		?>
		<style id="ih-dynamic-styles">
		:root {
			--ih-marker-color: <?php echo esc_attr( $opts['marker_color'] ); ?>;
			--ih-marker-hover: <?php echo esc_attr( $opts['marker_hover'] ); ?>;
			--ih-marker-size:  <?php echo esc_attr( $opts['marker_size'] ); ?>px;
			--ih-tooltip-bg:   <?php echo esc_attr( $opts['tooltip_bg']   ); ?>;
			--ih-tooltip-text: <?php echo esc_attr( $opts['tooltip_text'] ); ?>;
		}
		<?php if ( $custom_css ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $custom_css;
		} ?>
		</style>
		<?php
	}

	// ── Enqueue front-end assets ──────────────────────────────────────────────
	private static function enqueue( $marker_type ) {
		if ( self::$enqueued ) return;
		self::$enqueued = true;

		// Only load FontAwesome when FA icons are in use
		if ( $marker_type === 'fa_icon' ) {
			wp_enqueue_style(
				'font-awesome-5',
				'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
				[],
				'5.15.4'
			);
		}

		wp_enqueue_style(
			'ih-frontend',
			IH_PLUGIN_URL . 'assets/css/hotspots.css',
			$marker_type === 'fa_icon' ? [ 'font-awesome-5' ] : [],
			IH_VERSION
		);

		wp_enqueue_script(
			'ih-frontend',
			IH_PLUGIN_URL . 'assets/js/hotspots.js',
			[ 'jquery' ],
			IH_VERSION,
			true
		);
	}

	// ── Shortcode ─────────────────────────────────────────────────────────────
	public static function shortcode( $atts ) {
		$atts    = shortcode_atts( [ 'id' => 0 ], $atts, 'image_hotspot' );
		$post_id = absint( $atts['id'] );

		if ( ! $post_id || get_post_type( $post_id ) !== 'ih_hotspot' ) {
			return '<!-- image_hotspot: invalid id -->';
		}

		$image        = get_field( 'ih_image', $post_id );
		$hotspots     = get_field( 'ih_hotspots', $post_id );
		$show_numbers = (bool) get_field( 'ih_show_numbers', $post_id );
		$opts         = IH_Settings::get();
		$marker_type  = $opts['marker_type'];

		if ( ! $image ) {
			return '<!-- image_hotspot: no image set -->';
		}

		self::enqueue( $marker_type );

		$wrap_id = 'hotspot-' . sanitize_title( get_the_title( $post_id ) );

		ob_start();
		?>
		<div id="<?php echo esc_attr( $wrap_id ); ?>" class="ih-hotspot-wrap" data-id="<?php echo esc_attr( $post_id ); ?>">
			<div class="ih-image-container">
				<img src="<?php echo esc_url( $image['url'] ); ?>"
				     alt="<?php echo esc_attr( $image['alt'] ); ?>"
				     class="ih-base-image"
				     draggable="false">

				<?php if ( ! empty( $hotspots ) ) :
					foreach ( $hotspots as $index => $spot ) :
						$x     = floatval( $spot['ih_pos_x'] );
						$y     = floatval( $spot['ih_pos_y'] );
						$label = wp_kses_post( $spot['ih_marker_label'] );
						$desc  = wp_kses_post( $spot['ih_description'] );
						$thumb = ! empty( $spot['ih_marker_thumb'] ) ? $spot['ih_marker_thumb'] : null;
						$num   = $index + 1;
				?>
				<div class="ih-marker"
				     style="left:<?php echo esc_attr( $x ); ?>%;top:<?php echo esc_attr( $y ); ?>%;"
				     data-index="<?php echo esc_attr( $index ); ?>"
				     role="button"
				     tabindex="0"
				     aria-expanded="false"
				     aria-label="<?php echo esc_attr( strip_tags( $label ) ); ?>">

					<span class="ih-marker-visual">
						<?php if ( $marker_type === 'custom_image' && $opts['custom_image_url'] ) : ?>
							<img class="ih-marker-img"
							     src="<?php echo esc_url( $opts['custom_image_url'] ); ?>"
							     alt="" aria-hidden="true">
						<?php else : ?>
							<span class="ih-marker-icon fas <?php echo esc_attr( sanitize_html_class( $opts['fa_icon'] ) ); ?>"
							      aria-hidden="true"></span>
						<?php endif; ?>
						<?php if ( $show_numbers ) : ?>
							<span class="ih-marker-number" aria-hidden="true"><?php echo esc_html( $num ); ?></span>
						<?php endif; ?>
					</span>

					<span class="ih-pulse" aria-hidden="true"></span>

					<div class="ih-tooltip" role="tooltip">
						<button class="ih-tooltip-close" aria-label="<?php esc_attr_e( 'Close tooltip', 'image-hotspots' ); ?>">&times;</button>
						<div class="ih-tooltip-arrow"></div>
						<div class="ih-tooltip-inner">
							<?php if ( $label || $thumb ) : ?>
							<div class="ih-tooltip-header<?php echo $thumb ? ' ih-tooltip-header--has-img' : ''; ?>">
								<?php if ( $thumb ) : ?>
								<img class="ih-tooltip-thumb"
								     src="<?php echo esc_url( $thumb['url'] ); ?>"
								     alt="<?php echo esc_attr( $thumb['alt'] ); ?>">
								<?php endif; ?>
								<?php if ( $label ) : ?>
								<h4 class="ih-tooltip-title"><?php echo $label; ?></h4>
								<?php endif; ?>
							</div>
							<?php endif; ?>
							<?php if ( $desc ) : ?>
							<div class="ih-tooltip-body"><?php echo $desc; ?></div>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php endforeach; endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
