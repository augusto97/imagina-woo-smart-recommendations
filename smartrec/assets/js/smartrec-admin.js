/**
 * SmartRec Admin Scripts
 *
 * Handles complementary rules, confirm dialogs,
 * and settings tab switching.
 *
 * @package SmartRec
 * @since 1.0.0
 */
(function ($) {
	'use strict';

	if (typeof $ === 'undefined') {
		return;
	}

	/* === Complementary Rules === */

	$(document).on('click', '.smartrec-rules__add', function (e) {
		e.preventDefault();
		var $container = $(this).closest('.smartrec-rules');
		var $rows = $container.find('.smartrec-rule-row');
		var $template = $rows.last();

		if (!$template.length) {
			return;
		}

		var $clone = $template.clone();
		var idx = $rows.length;

		/* Update name attributes with new index */
		$clone.find('select, input').each(function () {
			var name = $(this).attr('name') || '';
			$(this).attr('name', name.replace(/\[\d+\]/, '[' + idx + ']'));
			$(this).val('');
		});

		$clone.insertBefore($(this));
	});

	$(document).on('click', '.smartrec-rule-row__remove', function (e) {
		e.preventDefault();
		var $container = $(this).closest('.smartrec-rules');

		if ($container.find('.smartrec-rule-row').length > 1) {
			$(this).closest('.smartrec-rule-row').remove();
		}
	});

	/* === Confirm Dialogs === */

	$(document).on('click', '.smartrec-confirm', function (e) {
		var msg = $(this).data('confirm') || smartrecAdmin.confirmText || 'Are you sure?';

		if (!window.confirm(msg)) {
			e.preventDefault();
		}
	});

	/* === Settings Sub-Tab Switching === */

	function activateTab(hash) {
		var $nav = $('.smartrec-tabs__nav');
		var $panels = $('.smartrec-tabs__panel');

		if (!$nav.length) {
			return;
		}

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

	/* Activate tab from URL hash on load */
	$(function () {
		if ($('.smartrec-tabs__nav').length) {
			activateTab(window.location.hash || '');
		}
	});

})(jQuery);
