/**
 * SmartRec Admin Scripts
 *
 * Handles complementary rules, location accordion,
 * confirm dialogs, color pickers, and settings tabs.
 *
 * @package SmartRec
 * @since 1.0.0
 */
(function ($) {
	'use strict';

	if (typeof $ === 'undefined') {
		return;
	}

	/* =========================================================
	 * Complementary Rules
	 * ========================================================= */

	$(document).on('click', '#smartrec-add-rule', function (e) {
		e.preventDefault();
		var $container = $('#smartrec-rules-container');
		var $rows      = $container.find('.smartrec-rule-row');
		var idx        = $rows.length;

		/* Remove empty-state message if present */
		$container.find('.smartrec-rules__empty').remove();

		if ($rows.length) {
			/* Clone the last existing row */
			var $clone = $rows.last().clone();

			$clone.find('select, input').each(function () {
				var name = $(this).attr('name') || '';
				$(this).attr('name', name.replace(/\[\d+\]/, '[' + idx + ']'));
				if ($(this).is('select')) {
					$(this).prop('selectedIndex', 0);
					$(this).find('option').prop('selected', false);
				} else if ($(this).is('[type="number"]')) {
					$(this).val('0.5');
				} else {
					$(this).val('');
				}
			});

			$container.append($clone);
		} else {
			/* No rows yet — build from the template data attribute */
			var tpl = $container.attr('data-template');
			if (tpl) {
				$container.append(tpl.replace(/__INDEX__/g, '0'));
			}
		}
	});

	$(document).on('click', '.smartrec-remove-rule', function (e) {
		e.preventDefault();
		var $container = $('#smartrec-rules-container');

		if ($container.find('.smartrec-rule-row').length > 1) {
			$(this).closest('.smartrec-rule-row').remove();
		} else {
			/* Last row — clear its values instead of removing */
			var $row = $(this).closest('.smartrec-rule-row');
			$row.find('select').prop('selectedIndex', 0).find('option').prop('selected', false);
			$row.find('input[type="number"]').val('0.5');
		}
	});

	/* =========================================================
	 * Display Locations — Accordion
	 * ========================================================= */

	$(document).on('click', '.smartrec-loc__header', function () {
		var $loc = $(this).closest('.smartrec-loc');
		$loc.toggleClass('smartrec-loc--open');
	});

	/* Toggle active styling when checkbox changes */
	$(document).on('change', '.smartrec-location-toggle', function () {
		var $loc = $(this).closest('.smartrec-loc');
		if ($(this).is(':checked')) {
			$loc.addClass('smartrec-loc--active');
		} else {
			$loc.removeClass('smartrec-loc--active');
		}
	});

	/* =========================================================
	 * Confirm Dialogs
	 * ========================================================= */

	$(document).on('click', '.smartrec-confirm', function (e) {
		var msg = $(this).data('confirm') ||
			(typeof smartrecAdmin !== 'undefined' && smartrecAdmin.confirmText) ||
			'Are you sure?';
		if (!window.confirm(msg)) {
			e.preventDefault();
		}
	});

	/* =========================================================
	 * Settings Sub-Tab Switching
	 * ========================================================= */

	function activateTab(hash) {
		var $nav    = $('.smartrec-tabs__nav');
		var $panels = $('.smartrec-tabs__panel');
		if (!$nav.length) { return; }

		var target = hash || $nav.find('a').first().attr('href') || '';
		$nav.find('a').removeClass('is-active');
		$nav.find('a[href="' + target + '"]').addClass('is-active');
		$panels.removeClass('is-active');
		$(target).addClass('is-active');
	}

	$(document).on('click', '.smartrec-tabs__nav a', function (e) {
		e.preventDefault();
		var hash = $(this).attr('href');
		window.location.hash = hash;
		activateTab(hash);
	});

	/* =========================================================
	 * Color Picker Init
	 * ========================================================= */

	function initColorPickers() {
		if (typeof $.fn.wpColorPicker !== 'undefined') {
			$('.smartrec-color-picker').each(function () {
				if (!$(this).closest('.wp-picker-container').length) {
					$(this).wpColorPicker({
						change: function () {},
						clear: function () {},
						defaultColor: $(this).data('default-color') || ''
					});
				}
			});
		}
	}

	/* =========================================================
	 * Init on DOM ready
	 * ========================================================= */

	$(function () {
		if ($('.smartrec-tabs__nav').length) {
			activateTab(window.location.hash || '');
		}
		initColorPickers();
	});

})(jQuery);
