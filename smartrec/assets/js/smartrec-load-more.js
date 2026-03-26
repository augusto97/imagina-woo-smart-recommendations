/**
 * SmartRec — Load More button handler.
 *
 * Loaded inline via wp_add_inline_script with smartrecLoadMore config.
 * Fetches additional products from the REST API and appends them to the grid/list.
 *
 * @package SmartRec
 * @since 1.5.0
 */
(function () {
	'use strict';

	if (typeof smartrecLoadMore === 'undefined') {
		return;
	}

	var restUrl = smartrecLoadMore.restUrl;
	var nonce = smartrecLoadMore.nonce;

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.smartrec-load-more__btn');
		if (!btn) { return; }

		e.preventDefault();

		// Prevent double-click.
		if (btn.classList.contains('smartrec-load-more__btn--loading')) {
			return;
		}

		var location  = btn.getAttribute('data-location') || '';
		var productId = btn.getAttribute('data-product-id') || '0';
		var engine    = btn.getAttribute('data-engine') || '';
		var offset    = parseInt(btn.getAttribute('data-offset') || '0', 10);
		var limit     = parseInt(btn.getAttribute('data-limit') || '4', 10);
		var layout    = btn.getAttribute('data-layout') || 'grid';
		var useWc     = btn.getAttribute('data-use-wc') === '1';
		var columns   = btn.getAttribute('data-columns') || '4';

		// Loading state.
		btn.classList.add('smartrec-load-more__btn--loading');
		var originalText = btn.textContent;
		btn.textContent = '...';

		// Build URL.
		var params = new URLSearchParams({
			location: location,
			product_id: productId,
			limit: limit.toString(),
			offset: offset.toString(),
			format: 'json'
		});
		if (engine) {
			params.set('engine', engine);
		}

		var url = restUrl + '?' + params.toString();

		fetch(url, {
			method: 'GET',
			headers: { 'X-WP-Nonce': nonce }
		})
		.then(function (res) { return res.json(); })
		.then(function (data) {
			var products = data.products || [];

			if (products.length === 0) {
				// No more products — hide button.
				btn.closest('.smartrec-load-more').style.display = 'none';
				return;
			}

			// Find the product container (grid, list, or WC products ul).
			var widget = btn.closest('.smartrec-widget');
			var container;

			if (useWc) {
				container = widget.querySelector('ul.products');
			} else if (layout === 'list') {
				container = widget.querySelector('.smartrec-widget__list');
			} else {
				container = widget.querySelector('.smartrec-widget__grid');
			}

			if (!container) { return; }

			// Append products.
			products.forEach(function (product) {
				var html;

				if (useWc) {
					// Build a basic WC product card.
					html = '<li class="product type-product">'
						+ '<a href="' + escAttr(product.permalink) + '">'
						+ '<img src="' + escAttr(product.image_url) + '" alt="' + escAttr(product.title) + '" loading="lazy" />'
						+ '<h2 class="woocommerce-loop-product__title">' + escHtml(product.title) + '</h2>'
						+ '</a>'
						+ '<span class="price">' + product.price_html + '</span>'
						+ '</li>';
				} else if (layout === 'list') {
					html = '<div class="smartrec-widget__item" data-product-id="' + product.id + '">'
						+ '<a href="' + escAttr(product.permalink) + '" class="smartrec-widget__link">'
						+ '<div class="smartrec-widget__item-image"><img src="' + escAttr(product.image_url) + '" alt="' + escAttr(product.title) + '" loading="lazy" /></div>'
						+ '</a>'
						+ '<div class="smartrec-widget__item-content">'
						+ '<a href="' + escAttr(product.permalink) + '" class="smartrec-widget__link">'
						+ '<h3 class="smartrec-widget__item-title">' + escHtml(product.title) + '</h3></a>'
						+ '<div class="smartrec-widget__price">' + product.price_html + '</div>'
						+ '</div></div>';
				} else if (layout === 'minimal') {
					html = '<div class="smartrec-widget__item" data-product-id="' + product.id + '">'
						+ '<a href="' + escAttr(product.permalink) + '" class="smartrec-widget__link">'
						+ '<div class="smartrec-widget__image"><img src="' + escAttr(product.image_url) + '" alt="' + escAttr(product.title) + '" loading="lazy" /></div>'
						+ '<h3 class="smartrec-widget__item-title">' + escHtml(product.title) + '</h3>'
						+ '</a></div>';
				} else {
					// Grid (default).
					html = '<div class="smartrec-widget__item" data-product-id="' + product.id + '">'
						+ '<a href="' + escAttr(product.permalink) + '" class="smartrec-widget__link">'
						+ '<div class="smartrec-widget__image"><img src="' + escAttr(product.image_url) + '" alt="' + escAttr(product.title) + '" loading="lazy" /></div>'
						+ '<h3 class="smartrec-widget__item-title">' + escHtml(product.title) + '</h3></a>'
						+ '<div class="smartrec-widget__price">' + product.price_html + '</div>'
						+ '</div>';
				}

				var temp = document.createElement('div');
				temp.innerHTML = html;
				while (temp.firstChild) {
					container.appendChild(temp.firstChild);
				}
			});

			// Update offset for next load.
			btn.setAttribute('data-offset', (offset + products.length).toString());

			// If fewer products returned than requested, no more to load.
			if (products.length < limit) {
				btn.closest('.smartrec-load-more').style.display = 'none';
			}
		})
		.catch(function () {
			// On error, just reset the button.
		})
		.finally(function () {
			btn.classList.remove('smartrec-load-more__btn--loading');
			btn.textContent = originalText;
		});
	});

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	function escAttr(str) {
		return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}
})();
