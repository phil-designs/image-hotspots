/**
 * Image HotSpots — Admin Marker Placement Helper
 *
 * Features:
 *  • Renders a full-width preview of the chosen ACF image.
 *  • Click anywhere on the preview to place a new marker (fills the next
 *    empty repeater row or adds a new row automatically).
 *  • Click-and-drag any existing marker to reposition it; the matching
 *    X / Y repeater fields update live as you drag.
 *  • Markers are numbered to match their repeater row order.
 */
(function ($) {
	'use strict';

	// ── State ─────────────────────────────────────────────────────────────────
	var $wrap     = null; // #ih-preview-wrap
	var $hint     = null; // #ih-coord-hint
	var isDragging   = false;
	var dragIndex    = -1;   // which repeater row is being dragged
	var dragHasMoved = false; // distinguishes a click from a real drag

	// ── Utility ───────────────────────────────────────────────────────────────

	/**
	 * Return the attachment ID stored in the ACF image hidden input, or 0.
	 * ACF stores the ID (not a URL) as the field's actual value.
	 */
	function getAttachmentId() {
		// ACF hides the real value in an input[type=hidden] inside the field wrapper
		var val = $('[data-name="ih_image"] input[type="hidden"]').val();
		return parseInt( val, 10 ) || 0;
	}

	/**
	 * Fetch the full-resolution URL for an attachment ID via AJAX, then call
	 * callback(url). Falls back to the ACF thumbnail src if AJAX fails.
	 */
	function fetchFullUrl( attachmentId, callback ) {
		$.post( ihAdmin.ajaxUrl, {
			action:        'ih_get_full_image_url',
			nonce:         ihAdmin.nonce,
			attachment_id: attachmentId
		})
		.done(function ( res ) {
			if ( res.success && res.data && res.data.url ) {
				callback( res.data.url );
			} else {
				callback( getFallbackSrc() );
			}
		})
		.fail(function () {
			callback( getFallbackSrc() );
		});
	}

	/** Last-resort: read whatever src ACF has in its own preview thumbnail. */
	function getFallbackSrc() {
		var $img = $('[data-name="ih_image"] .acf-image-uploader img').first();
		return ( $img.length && $img.attr('src') ) ? $img.attr('src') : null;
	}

	/** Convert a mouse / touch event's client coords to % inside $wrap. */
	function clientToPercent( clientX, clientY ) {
		var rect = $wrap[0].getBoundingClientRect();
		return {
			x: parseFloat( Math.min( 100, Math.max( 0, ( clientX - rect.left ) / rect.width  * 100 ) ).toFixed(1) ),
			y: parseFloat( Math.min( 100, Math.max( 0, ( clientY - rect.top  ) / rect.height * 100 ) ).toFixed(1) )
		};
	}

	/** Get all live (non-clone) repeater rows. */
	function repeaterRows() {
		return $('[data-name="ih_hotspots"] .acf-row:not(.acf-clone)');
	}

	// ── Build preview ─────────────────────────────────────────────────────────

	function buildPreview( src ) {
		if ( ! $wrap || ! $wrap.length ) return;

		$wrap.empty();
		$wrap.append( $('<img>', { src: src, alt: '' }) );
		renderAllMarkers();
		bindWrapEvents();
	}

	// ── Markers ───────────────────────────────────────────────────────────────

	function renderAllMarkers() {
		if ( ! $wrap || ! $wrap.length ) return;
		$wrap.find('.ih-admin-marker').remove();

		repeaterRows().each(function ( idx ) {
			var xVal = $( this ).find('[data-name="ih_pos_x"] input').val();
			var yVal = $( this ).find('[data-name="ih_pos_y"] input').val();
			if ( xVal !== '' && yVal !== '' ) {
				addMarker( parseFloat(xVal), parseFloat(yVal), idx );
			}
		});
	}

	function addMarker( x, y, index ) {
		var $m = $(
			'<div class="ih-admin-marker" data-index="' + index + '">' +
				'<span class="dashicons dashicons-location ih-pin"></span>' +
				'<span class="ih-num">' + ( index + 1 ) + '</span>' +
			'</div>'
		);
		$m.css({ left: x + '%', top: y + '%' });
		$wrap.append( $m );
		bindMarkerDrag( $m );
	}

	// ── Drag-and-drop ─────────────────────────────────────────────────────────

	function bindMarkerDrag( $marker ) {
		$marker.on('mousedown', function ( e ) {
			e.preventDefault();
			e.stopPropagation(); // don't fire the wrap click (add-new logic)

			isDragging   = true;
			dragHasMoved = false;
			dragIndex    = parseInt( $marker.data('index'), 10 );

			$marker.addClass('dragging');
			$('body').addClass('ih-body-grabbing');

			$( document ).on('mousemove.ihdrag', function ( e ) {
				dragHasMoved = true;
				var pct = clientToPercent( e.clientX, e.clientY );

				// Move the marker visually (no transition during drag)
				$marker.css({ left: pct.x + '%', top: pct.y + '%' });
				updateHint( pct.x, pct.y, true );
			});

			$( document ).on('mouseup.ihdrag', function ( e ) {
				$( document ).off('.ihdrag');
				$marker.removeClass('dragging');
				$('body').removeClass('ih-body-grabbing');

				if ( dragHasMoved ) {
					var pct = clientToPercent( e.clientX, e.clientY );
					commitPosition( dragIndex, pct.x, pct.y );
					updateHint( pct.x, pct.y, false );
				}

				isDragging   = false;
				dragHasMoved = false;
				dragIndex    = -1;
			});
		});
	}

	/** Write x/y back into the repeater row's number inputs. */
	function commitPosition( index, x, y ) {
		var $row = repeaterRows().eq( index );
		$row.find('[data-name="ih_pos_x"] input').val( x ).trigger('change');
		$row.find('[data-name="ih_pos_y"] input').val( y ).trigger('change');
	}

	// ── Wrap click — place a new / fill empty marker ──────────────────────────

	function bindWrapEvents() {
		$wrap.off('click.ihplace').on('click.ihplace', function ( e ) {
			// Ignore if a drag just finished
			if ( isDragging || dragHasMoved ) return;
			// Ignore clicks on existing markers
			if ( $( e.target ).closest('.ih-admin-marker').length ) return;

			var pct = clientToPercent( e.clientX, e.clientY );
			updateHint( pct.x, pct.y, false );
			fillNextRow( pct.x, pct.y );
		});
	}

	function fillNextRow( x, y ) {
		var filled = false;

		repeaterRows().each(function ( idx ) {
			if ( filled ) return;
			var $xField = $( this ).find('[data-name="ih_pos_x"] input');
			var $yField = $( this ).find('[data-name="ih_pos_y"] input');

			// Consider a row "empty" if both X and Y are blank or still the default 50
			if ( ( $xField.val() === '' || $xField.val() === '50' ) &&
			     ( $yField.val() === '' || $yField.val() === '50' ) ) {
				$xField.val( x ).trigger('change');
				$yField.val( y ).trigger('change');
				filled = true;
			}
		});

		if ( ! filled ) {
			// All rows full — add a new repeater row, then fill it
			$('[data-name="ih_hotspots"] .acf-repeater > .acf-actions > .acf-button').trigger('click');
			setTimeout(function () {
				fillNextRow( x, y );
			}, 350);
			return;
		}

		renderAllMarkers();
	}

	// ── Status bar ────────────────────────────────────────────────────────────

	function ensureStatusBar() {
		if ( $( '#ih-status-bar' ).length ) return;
		var $bar = $('<div id="ih-status-bar"></div>');
		$bar.append('<span id="ih-coord-hint"></span>');
		$bar.append('<span id="ih-drag-hint">Drag a marker to reposition it &mdash; click the image to add a new one.</span>');
		$wrap.after( $bar );
		$hint = $( '#ih-coord-hint' );
	}

	function updateHint( x, y, isDrag ) {
		if ( ! $hint || ! $hint.length ) {
			$hint = $( '#ih-coord-hint' );
		}
		var action = isDrag ? 'Dragging' : 'Placed';
		$hint.text( action + '  X: ' + x + '%   Y: ' + y + '%' );
	}

	// ── ACF lifecycle hooks ───────────────────────────────────────────────────

	/**
	 * acf.addAction('ready') fires after ACF has initialised all fields —
	 * the safe place to read existing field values on page load.
	 */
	acf.addAction('ready', function () {
		$wrap = $( '#ih-preview-wrap' );
		if ( ! $wrap.length ) return;

		ensureStatusBar();

		var id = getAttachmentId();
		if ( id ) {
			fetchFullUrl( id, function ( src ) {
				if ( src ) buildPreview( src );
				else showEmptyState();
			});
		} else {
			showEmptyState();
		}
	});

	/** Image field value changed (upload / remove / replace). */
	acf.addAction('change', function ( $field ) {
		if ( ! $field ) return;
		var $el = $field instanceof $ ? $field : $( $field );
		if ( ! $el.closest('[data-name="ih_image"]').length ) return;

		// Short delay so ACF finishes writing the attachment ID to the hidden input
		setTimeout(function () {
			$wrap = $( '#ih-preview-wrap' );
			ensureStatusBar();

			var id = getAttachmentId();
			if ( id ) {
				fetchFullUrl( id, function ( src ) {
					if ( src ) buildPreview( src );
					else showEmptyState();
				});
			} else {
				showEmptyState();
			}
		}, 200);
	});

	/** Repeater row added. */
	acf.addAction('append', function () {
		setTimeout(function () { renderAllMarkers(); }, 200);
	});

	/** Repeater row removed. */
	acf.addAction('remove', function () {
		setTimeout(function () { renderAllMarkers(); }, 200);
	});

	/** X or Y fields edited manually — sync the preview marker. */
	$( document ).on('change input', '[data-name="ih_pos_x"] input, [data-name="ih_pos_y"] input', function () {
		renderAllMarkers();
	});

	function showEmptyState() {
		$wrap.html('<div id="ih-preview-empty">Upload and save an image above to enable the interactive placement preview.</div>');
	}

	/** Prevent text-selection cursor leak when dragging outside the wrap. */
	$('<style>.ih-body-grabbing, .ih-body-grabbing * { cursor: grabbing !important; }</style>').appendTo('head');

}( jQuery ));
