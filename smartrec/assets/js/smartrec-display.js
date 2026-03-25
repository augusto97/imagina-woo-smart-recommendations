/**
 * SmartRec AJAX Display Loader
 *
 * Lazy-loads recommendation widgets via REST API
 * when they scroll into the viewport.
 *
 * @package SmartRec
 * @since 1.0.0
 */
(function () {
	'use strict';

	var config = window.smartrecDisplay || {};

	if (!config.ajaxUrl || !config.nonce) {
		return;
	}

	/**
	 * Fetch recommendations for a placeholder element.
	 *
	 * @param {HTMLElement} el The .smartrec-loading element.
	 */
	function loadWidget(el) {
		var location = el.getAttribute('data-location') || '';
		var productId = el.getAttribute('data-product-id') || '0';
		var limit = el.getAttribute('data-limit') || '';
		var layout = el.getAttribute('data-layout') || '';
		var engine = el.getAttribute('data-engine') || '';

		var params = new URLSearchParams();
		params.append('location', location);
		params.append('product_id', productId);
		params.append('format', 'html');

		if (limit) {
			params.append('limit', limit);
		}
		if (layout) {
			params.append('layout', layout);
		}
		if (engine) {
			params.append('engine', engine);
		}

		var url = config.ajaxUrl + '?' + params.toString();

		fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': config.nonce
			}
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error(response.statusText);
				}
				return response.text();
			})
			.then(function (html) {
				if (html) {
					el.innerHTML = html;
					el.classList.remove('smartrec-loading');
				} else {
					el.style.display = 'none';
				}
			})
			.catch(function () {
				el.style.display = 'none';
			});
	}

	/**
	 * Initialize lazy loading with IntersectionObserver.
	 */
	function init() {
		var placeholders = document.querySelectorAll('.smartrec-loading');

		if (!placeholders.length) {
			return;
		}

		if ('IntersectionObserver' in window) {
			var observer = new IntersectionObserver(
				function (entries) {
					entries.forEach(function (entry) {
						if (entry.isIntersecting) {
							observer.unobserve(entry.target);
							loadWidget(entry.target);
						}
					});
				},
				{ rootMargin: '200px' }
			);

			placeholders.forEach(function (el) {
				observer.observe(el);
			});
		} else {
			/* Fallback: load all immediately */
			placeholders.forEach(loadWidget);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
