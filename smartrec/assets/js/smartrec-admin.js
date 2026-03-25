/**
 * SmartRec Admin Scripts
 *
 * @package SmartRec
 * @since 1.0.0
 */
(function ($) {
	'use strict';

	if (typeof $ === 'undefined') { return; }

	/* =========================================================
	 * Settings Sub-Tabs
	 * ========================================================= */

	function switchSubTab(tabId) {
		$('.smartrec-subtabs__link').removeClass('smartrec-subtabs__link--active');
		$('.smartrec-subtabs__link[data-tab="' + tabId + '"]').addClass('smartrec-subtabs__link--active');
		$('.smartrec-subtabs__panel').removeClass('smartrec-subtabs__panel--active');
		$('#smartrec-tab-' + tabId).addClass('smartrec-subtabs__panel--active');
	}

	$(document).on('click', '.smartrec-subtabs__link', function (e) {
		e.preventDefault();
		var tabId = $(this).data('tab');
		switchSubTab(tabId);
		window.location.hash = tabId;
	});

	/* =========================================================
	 * Card Style Radio — visual selection
	 * ========================================================= */

	$(document).on('change', '.smartrec-card-style-option input[type="radio"]', function () {
		$('.smartrec-card-style-option').removeClass('smartrec-card-style-option--selected');
		$(this).closest('.smartrec-card-style-option').addClass('smartrec-card-style-option--selected');
	});

	/* =========================================================
	 * Display Locations — Accordion
	 * ========================================================= */

	$(document).on('click', '.smartrec-loc__header', function () {
		$(this).closest('.smartrec-loc').toggleClass('smartrec-loc--open');
	});

	$(document).on('change', '.smartrec-location-toggle', function () {
		var $loc = $(this).closest('.smartrec-loc');
		$loc.toggleClass('smartrec-loc--active', $(this).is(':checked'));
	});

	/* =========================================================
	 * Complementary Rules
	 * ========================================================= */

	$(document).on('click', '#smartrec-add-rule', function (e) {
		e.preventDefault();
		var $container = $('#smartrec-rules-container');

		/* Remove empty state message */
		$container.find('.smartrec-rules__empty').remove();

		var $rows = $container.find('.smartrec-rule-row');
		var idx   = $rows.length;

		if ($rows.length) {
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
			/* Build from template */
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
			var $row = $(this).closest('.smartrec-rule-row');
			$row.find('select').prop('selectedIndex', 0).find('option').prop('selected', false);
			$row.find('input[type="number"]').val('0.5');
		}
	});

	/* =========================================================
	 * Confirm Dialogs
	 * ========================================================= */

	$(document).on('click', '.smartrec-confirm', function (e) {
		var msg = $(this).data('confirm') || 'Are you sure?';
		if (!window.confirm(msg)) { e.preventDefault(); }
	});

	/* =========================================================
	 * Color Picker
	 * ========================================================= */

	function initColorPickers() {
		if (typeof $.fn.wpColorPicker !== 'undefined') {
			$('.smartrec-color-picker').each(function () {
				if (!$(this).closest('.wp-picker-container').length) {
					$(this).wpColorPicker({
						defaultColor: $(this).data('default-color') || ''
					});
				}
			});
		}
	}

	/* =========================================================
	 * Init
	 * ========================================================= */

	$(function () {
		/* Restore sub-tab from hash */
		var hash = window.location.hash.replace('#', '');
		if (hash && $('#smartrec-tab-' + hash).length) {
			switchSubTab(hash);
		}

		initColorPickers();
	});

})(jQuery);
