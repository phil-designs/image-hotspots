/**
 * Image HotSpots — Frontend Interactions
 */
(function ($) {
	'use strict';

	var ACTIVE_CLASS = 'is-active';

	function positionTooltip($marker) {
		var $tooltip = $marker.find('.ih-tooltip');
		var $wrap    = $marker.closest('.ih-image-container');

		// Reset flip classes
		$marker.removeClass('flip-down flip-left flip-right');

		var markerOffset  = $marker.offset();
		var wrapOffset    = $wrap.offset();
		var tooltipWidth  = $tooltip.outerWidth(true);
		var tooltipHeight = $tooltip.outerHeight(true);
		var wrapWidth     = $wrap.outerWidth();

		// Relative position of marker inside wrap
		var relLeft = markerOffset.left - wrapOffset.left;
		var relTop  = markerOffset.top  - wrapOffset.top;

		// Flip down if tooltip would go above viewport/wrap
		if (relTop - tooltipHeight < 0) {
			$marker.addClass('flip-down');
		}

		// Flip horizontal if near edges
		if (relLeft + tooltipWidth / 2 > wrapWidth) {
			$marker.addClass('flip-left');
		} else if (relLeft - tooltipWidth / 2 < 0) {
			$marker.addClass('flip-right');
		}
	}

	function openMarker($marker) {
		// Close any other open markers in the same wrap
		$marker.closest('.ih-hotspot-wrap')
		       .find('.' + ACTIVE_CLASS)
		       .not($marker)
		       .removeClass(ACTIVE_CLASS)
		       .attr('aria-expanded', 'false');

		$marker.addClass(ACTIVE_CLASS).attr('aria-expanded', 'true');
		positionTooltip($marker);
	}

	function closeMarker($marker) {
		$marker.removeClass(ACTIVE_CLASS)
		       .attr('aria-expanded', 'false')
		       .removeClass('flip-down flip-left flip-right');
	}

	function toggleMarker($marker) {
		if ($marker.hasClass(ACTIVE_CLASS)) {
			closeMarker($marker);
		} else {
			openMarker($marker);
		}
	}

	function init() {
		// Click / tap on marker icon or pulse (but not the tooltip itself)
		$(document).on('click', '.ih-marker', function (e) {
			// If the click is on or inside the tooltip, don't toggle
			if ($(e.target).closest('.ih-tooltip').length) return;
			e.stopPropagation();
			toggleMarker($(this));
		});

		// Close button inside tooltip
		$(document).on('click', '.ih-tooltip-close', function (e) {
			e.stopPropagation();
			closeMarker($(this).closest('.ih-marker'));
		});

		// Keyboard: Enter / Space to open; Escape to close
		$(document).on('keydown', '.ih-marker', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				toggleMarker($(this));
			}
			if (e.key === 'Escape') {
				closeMarker($(this));
				$(this).focus();
			}
		});

		// Click outside closes all markers
		$(document).on('click', function () {
			$('.' + ACTIVE_CLASS).each(function () {
				closeMarker($(this));
			});
		});

		// Reposition on window resize
		$(window).on('resize', function () {
			$('.' + ACTIVE_CLASS).each(function () {
				positionTooltip($(this));
			});
		});
	}

	$(document).ready(init);

}(jQuery));
