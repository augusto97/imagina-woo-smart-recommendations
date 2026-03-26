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

	/* =========================================================
	 * Shortcode Builder
	 * ========================================================= */

	function initShortcodeBuilder() {
		var $type     = $('#smartrec-builder-type');
		var $title    = $('#smartrec-builder-title');
		var $limit    = $('#smartrec-builder-limit');
		var $columns  = $('#smartrec-builder-columns');
		var $tablet   = $('#smartrec-builder-columns-tablet');
		var $mobile   = $('#smartrec-builder-columns-mobile');
		var $layout   = $('#smartrec-builder-layout');
		var $loadmore = $('#smartrec-builder-loadmore');
		var $price    = $('#smartrec-builder-price');
		var $rating   = $('#smartrec-builder-rating');
		var $cart     = $('#smartrec-builder-cart');
		var $reason   = $('#smartrec-builder-reason');
		var $result   = $('#smartrec-builder-result');
		var $desc     = $('#smartrec-builder-desc');
		var $copy     = $('#smartrec-builder-copy');

		if (!$type.length) { return; }

		function updateShortcode() {
			var tag     = $type.val();
			var selected = $type.find(':selected');
			var title   = $title.val() || '';
			var defTitle = selected.data('title') || '';
			var limit   = parseInt($limit.val(), 10) || 8;
			var cols    = parseInt($columns.val(), 10) || 4;
			var tabCols = parseInt($tablet.val(), 10) || 2;
			var mobCols = parseInt($mobile.val(), 10) || 1;
			var layout  = $layout.val() || 'grid';
			var lm      = parseInt($loadmore.val(), 10) || 0;

			var attrs = [];

			/* Only add attrs that differ from defaults */
			if (title && title !== defTitle) { attrs.push('title="' + title + '"'); }
			if (limit !== 8)    { attrs.push('limit="' + limit + '"'); }
			if (cols !== 4)     { attrs.push('columns="' + cols + '"'); }
			if (tabCols !== 2)  { attrs.push('columns_tablet="' + tabCols + '"'); }
			if (mobCols !== 1)  { attrs.push('columns_mobile="' + mobCols + '"'); }
			if (layout !== 'grid') { attrs.push('layout="' + layout + '"'); }
			if (lm > 0)        { attrs.push('load_more="' + lm + '"'); }
			if (!$price.is(':checked'))  { attrs.push('show_price="no"'); }
			if (!$rating.is(':checked')) { attrs.push('show_rating="no"'); }
			if (!$cart.is(':checked'))   { attrs.push('show_add_to_cart="no"'); }
			if ($reason.is(':checked'))  { attrs.push('show_reason="yes"'); }

			var shortcode = '[' + tag + (attrs.length ? ' ' + attrs.join(' ') : '') + ']';
			$result.text(shortcode);

			/* Update description */
			var descText = '';
			$('#smartrec-builder-type option').each(function () {
				/* find matching preset desc from the presets section */
			});
		}

		/* Update on any change */
		$type.add($title).add($limit).add($columns).add($tablet).add($mobile)
			 .add($layout).add($loadmore).add($price).add($rating).add($cart).add($reason)
			 .on('input change', updateShortcode);

		/* When type changes, update the title placeholder */
		$type.on('change', function () {
			var defTitle = $(this).find(':selected').data('title') || '';
			$title.attr('placeholder', defTitle).val('');
			updateShortcode();
		});

		/* Copy button */
		$copy.on('click', function () {
			var text = $result.text();
			if (navigator.clipboard) {
				navigator.clipboard.writeText(text).then(function () {
					$copy.text('Copied!');
					setTimeout(function () { $copy.text('Copy'); }, 1500);
				});
			} else {
				/* Fallback */
				var ta = document.createElement('textarea');
				ta.value = text;
				document.body.appendChild(ta);
				ta.select();
				document.execCommand('copy');
				document.body.removeChild(ta);
				$copy.text('Copied!');
				setTimeout(function () { $copy.text('Copy'); }, 1500);
			}
		});

		/* Initial render */
		updateShortcode();
	}

	$(function () {
		/* Restore sub-tab from hash */
		var hash = window.location.hash.replace('#', '');
		if (hash && $('#smartrec-tab-' + hash).length) {
			switchSubTab(hash);
		}

		initColorPickers();
		initShortcodeBuilder();
	});

})(jQuery);
