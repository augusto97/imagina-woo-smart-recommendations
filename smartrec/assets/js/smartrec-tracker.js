/**
 * SmartRec Frontend Behavior Tracker
 *
 * Lightweight vanilla JS tracker for user behavior events.
 * Uses navigator.sendBeacon() with fetch fallback.
 * Batches events and sends max every 5 seconds or on page unload.
 *
 * @package SmartRec
 */
(function () {
	'use strict';

	if (typeof smartrecTracker === 'undefined') {
		return;
	}

	// Respect Do Not Track.
	if (navigator.doNotTrack === '1' || window.doNotTrack === '1') {
		return;
	}

	var config = smartrecTracker;
	var eventQueue = [];
	var flushTimer = null;
	var FLUSH_INTERVAL = 5000;
	var enabled = true;
	var viewTracked = false;

	/**
	 * Send events to the server.
	 */
	function flushEvents() {
		if (!enabled || eventQueue.length === 0) {
			return;
		}

		var events = eventQueue.splice(0, 20);
		var payload = JSON.stringify({ events: events });

		// Try sendBeacon first for reliability.
		if (navigator.sendBeacon) {
			var blob = new Blob([payload], { type: 'application/json' });
			var sent = navigator.sendBeacon(
				config.ajaxUrl + '?_wpnonce=' + encodeURIComponent(config.nonce),
				blob
			);
			if (sent) {
				return;
			}
		}

		// Fallback to fetch.
		try {
			fetch(config.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: payload,
				keepalive: true
			});
		} catch (e) {
			if (config.debug) {
				console.error('SmartRec: Failed to send events', e);
			}
		}
	}

	/**
	 * Queue an event for sending.
	 *
	 * @param {string} eventType Event type.
	 * @param {number} productId Product ID.
	 * @param {Object} extra     Extra data.
	 */
	function trackEvent(eventType, productId, extra) {
		if (!enabled || !productId) {
			return;
		}

		var event = {
			event_type: eventType,
			product_id: parseInt(productId, 10),
			source_product_id: extra && extra.sourceProductId ? parseInt(extra.sourceProductId, 10) : 0,
			context: extra && extra.context ? extra.context : config.context,
			timestamp: Date.now()
		};

		eventQueue.push(event);

		if (config.debug) {
			console.log('SmartRec: Event queued', event);
		}

		// Reset flush timer.
		if (flushTimer) {
			clearTimeout(flushTimer);
		}
		flushTimer = setTimeout(flushEvents, FLUSH_INTERVAL);
	}

	/**
	 * Track product view with 2-second debounce.
	 */
	function trackProductView() {
		if (!config.productId || viewTracked) {
			return;
		}

		setTimeout(function () {
			if (document.visibilityState !== 'hidden') {
				trackEvent('view', config.productId, { context: 'product' });
				viewTracked = true;
			}
		}, 2000);
	}

	/**
	 * Track recommendation click.
	 *
	 * @param {Event} e Click event.
	 */
	function trackRecommendationClick(e) {
		var link = e.target.closest('.smartrec-widget__item a[data-product-id]');
		if (!link) {
			return;
		}

		var productId = link.getAttribute('data-product-id');
		if (productId) {
			trackEvent('click', productId, {
				sourceProductId: config.productId,
				context: 'recommendation'
			});
		}
	}

	/**
	 * Initialize WooCommerce event hooks.
	 */
	function initWooCommerceHooks() {
		// jQuery-based add to cart (classic WC).
		if (typeof jQuery !== 'undefined') {
			jQuery(document.body).on('added_to_cart', function (e, fragments, hash, button) {
				var productId = button ? button.data('product_id') : 0;
				if (productId) {
					trackEvent('cart_add', productId, { context: 'cart' });
				}
			});

			jQuery(document.body).on('removed_from_cart', function (e, fragments, hash, button) {
				var productId = button ? button.data('product_id') : 0;
				if (productId) {
					trackEvent('cart_remove', productId, { context: 'cart' });
				}
			});
		}
	}

	/**
	 * Initialize tracker.
	 */
	function init() {
		// Track product view on product pages.
		if (config.productId) {
			trackProductView();
		}

		// Track recommendation clicks.
		document.addEventListener('click', trackRecommendationClick);

		// WooCommerce hooks.
		initWooCommerceHooks();

		// Flush on page unload.
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'hidden') {
				flushEvents();
			}
		});

		window.addEventListener('beforeunload', flushEvents);
	}

	// Public API.
	window.smartrecTracker = {
		track: trackEvent,
		flush: flushEvents,
		disable: function () {
			enabled = false;
			eventQueue = [];
		},
		enable: function () {
			enabled = true;
		}
	};

	// Boot.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
