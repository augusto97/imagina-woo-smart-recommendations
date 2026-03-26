/**
 * SmartRec — Load More button handler.
 *
 * Fetches server-rendered product HTML (partial) via REST API
 * and appends to the existing product container.
 *
 * @package SmartRec
 * @since 1.6.3
 */
(function () {
	'use strict';

	if (typeof smartrecLoadMore === 'undefined') { return; }

	var restUrl = smartrecLoadMore.restUrl;
	var nonce   = smartrecLoadMore.nonce;

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.smartrec-load-more__btn');
		if (!btn || btn.classList.contains('smartrec-load-more__btn--loading')) { return; }

		e.preventDefault();

		var location  = btn.getAttribute('data-location') || '';
		var productId = btn.getAttribute('data-product-id') || '0';
		var engine    = btn.getAttribute('data-engine') || '';
		var limit     = parseInt(btn.getAttribute('data-limit') || '4', 10);
		var useWc     = btn.getAttribute('data-use-wc') === '1';

		// Find the product container.
		var widget    = btn.closest('.smartrec-widget');
		var container;

		if (useWc) {
			container = widget.querySelector('.smartrec-wc-products ul.products')
					 || widget.querySelector('ul.products');
		} else {
			container = widget.querySelector('.smartrec-widget__grid')
					 || widget.querySelector('.smartrec-widget__list')
					 || widget.querySelector('.smartrec-widget__minimal');
		}

		if (!container) { return; }

		// Collect IDs of already-shown products to exclude.
		var shownIds = [];

		if (useWc) {
			container.querySelectorAll('li.product').forEach(function (li) {
				// Try data-product_id on add-to-cart button.
				var atcBtn = li.querySelector('[data-product_id]');
				if (atcBtn) {
					shownIds.push(atcBtn.getAttribute('data-product_id'));
					return;
				}
				// Fallback: extract from post-{id} class.
				var classes = li.className || '';
				var match = classes.match(/post-(\d+)/);
				if (match) { shownIds.push(match[1]); }
			});
		} else {
			container.querySelectorAll('[data-product-id]').forEach(function (el) {
				shownIds.push(el.getAttribute('data-product-id'));
			});
		}

		// Loading state.
		btn.classList.add('smartrec-load-more__btn--loading');
		var originalText = btn.textContent;
		btn.textContent = '...';

		// Build request.
		var params = new URLSearchParams({
			location:   location,
			product_id: productId,
			limit:      limit.toString(),
			format:     'html',
			partial:    '1',
			exclude:    shownIds.join(',')
		});
		if (engine) { params.set('engine', engine); }

		fetch(restUrl + '?' + params.toString(), {
			method: 'GET',
			headers: { 'X-WP-Nonce': nonce }
		})
		.then(function (res) { return res.json(); })
		.then(function (data) {
			var html  = (data.html || '').trim();
			var count = data.count || 0;

			if (!html || count === 0) {
				btn.closest('.smartrec-load-more').style.display = 'none';
				return;
			}

			// Parse the server-rendered HTML and append items.
			var temp = document.createElement('div');
			temp.innerHTML = html;

			while (temp.firstElementChild) {
				container.appendChild(temp.firstElementChild);
			}

			// Hide button if fewer products returned than requested.
			if (count < limit) {
				btn.closest('.smartrec-load-more').style.display = 'none';
			}
		})
		.catch(function () {
			// Silent fail — reset button.
		})
		.finally(function () {
			btn.classList.remove('smartrec-load-more__btn--loading');
			btn.textContent = originalText;
		});
	});
})();
