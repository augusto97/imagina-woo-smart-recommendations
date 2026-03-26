<?php
/**
 * WP REST API endpoints.
 *
 * @package SmartRec\API
 */

namespace SmartRec\API;

use SmartRec\Core\Settings;
use SmartRec\Engines\RecommendationManager;
use SmartRec\Tracking\Tracker;
use SmartRec\Tracking\EventStore;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestAPI
 *
 * Registers REST API endpoints under smartrec/v1.
 */
class RestAPI {

	/**
	 * Recommendation manager.
	 *
	 * @var RecommendationManager
	 */
	private $manager;

	/**
	 * Tracker.
	 *
	 * @var Tracker
	 */
	private $tracker;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param RecommendationManager $manager  Recommendation manager.
	 * @param Tracker               $tracker  Tracker instance.
	 * @param Settings              $settings Settings instance.
	 */
	public function __construct( RecommendationManager $manager, Tracker $tracker, Settings $settings ) {
		$this->manager  = $manager;
		$this->tracker  = $tracker;
		$this->settings = $settings;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$namespace = 'smartrec/v1';

		// Events endpoint — receives tracking events.
		register_rest_route(
			$namespace,
			'/events',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_events' ),
				'permission_callback' => '__return_true',
			)
		);

		// Recommendations endpoint.
		register_rest_route(
			$namespace,
			'/recommendations',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_recommendations' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'location'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'product_id' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'engine'     => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'limit'      => array(
						'default'           => 8,
						'sanitize_callback' => 'absint',
					),
					'offset'     => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'format'     => array(
						'default'           => 'json',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Trending endpoint.
		register_rest_route(
			$namespace,
			'/trending',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_trending' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'category_id' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'limit'       => array(
						'default'           => 8,
						'sanitize_callback' => 'absint',
					),
					'period'      => array(
						'default'           => '7d',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Analytics endpoint (admin only).
		register_rest_route(
			$namespace,
			'/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_analytics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'metric'    => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_from' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'engine'    => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handle tracking events.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_events( $request ) {
		$events = $request->get_json_params();

		if ( ! isset( $events['events'] ) || ! is_array( $events['events'] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid events data.', 'smartrec' ),
				),
				400
			);
		}

		// Rate limit: max 20 events per request.
		$events_list = array_slice( $events['events'], 0, 20 );

		$session_id = $this->tracker->get_session_manager()->get_session_id();
		$user_id    = get_current_user_id();
		$processed  = 0;

		$event_store = $this->tracker->get_event_store();

		foreach ( $events_list as $event ) {
			if ( empty( $event['event_type'] ) || empty( $event['product_id'] ) ) {
				continue;
			}

			$result = $event_store->add_event(
				array(
					'session_id'        => $session_id,
					'user_id'           => $user_id,
					'event_type'        => sanitize_text_field( $event['event_type'] ),
					'product_id'        => absint( $event['product_id'] ),
					'source_product_id' => absint( $event['source_product_id'] ?? 0 ),
					'context'           => sanitize_text_field( $event['context'] ?? '' ),
				)
			);

			if ( $result ) {
				++$processed;
			}
		}

		// Flush buffer immediately for REST requests.
		$event_store->flush_buffer();

		return new \WP_REST_Response(
			array(
				'success'   => true,
				'processed' => $processed,
			),
			200
		);
	}

	/**
	 * Handle recommendations request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_recommendations( $request ) {
		$location   = $request->get_param( 'location' );
		$product_id = (int) $request->get_param( 'product_id' );
		$engine     = $request->get_param( 'engine' );
		$limit      = min( 20, max( 1, (int) $request->get_param( 'limit' ) ) );
		$format     = $request->get_param( 'format' );
		$offset     = max( 0, (int) $request->get_param( 'offset' ) );

		$args = array(
			'offset' => $offset,
			'limit' => $limit,
		);

		if ( ! empty( $engine ) ) {
			$args['engine'] = $engine;
		}

		$recommendations = $this->manager->getRecommendations( $location, $product_id, $args );

		if ( 'html' === $format ) {
			$renderer = new \SmartRec\Display\Renderer( $this->manager, $this->settings );
			$products = array();
			foreach ( $recommendations as $rec ) {
				$product = wc_get_product( $rec['product_id'] );
				if ( $product ) {
					$products[] = $product;
				}
			}

			$location_settings = $this->settings->get_location_settings( $location );
			$html = $renderer->render_template(
				$products,
				$recommendations,
				$location,
				array_merge( $location_settings, $args )
			);

			$response = new \WP_REST_Response(
				array(
					'html'   => $html,
					'engine' => $engine,
					'cached' => false,
				),
				200
			);
		} else {
			$products_data = array();
			foreach ( $recommendations as $rec ) {
				$product = wc_get_product( $rec['product_id'] );
				if ( ! $product ) {
					continue;
				}

				$products_data[] = array(
					'id'        => $product->get_id(),
					'title'     => $product->get_name(),
					'permalink' => $product->get_permalink(),
					'image_url' => wp_get_attachment_url( $product->get_image_id() ),
					'price'     => $product->get_price(),
					'price_html' => $product->get_price_html(),
					'rating'    => $product->get_average_rating(),
					'reason'    => $rec['reason'] ?? '',
					'score'     => $rec['score'],
				);
			}

			$response = new \WP_REST_Response(
				array(
					'products' => $products_data,
					'engine'   => $engine,
					'cached'   => false,
				),
				200
			);
		}

		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	/**
	 * Handle trending products request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_trending( $request ) {
		$category_id = (int) $request->get_param( 'category_id' );
		$limit       = min( 20, max( 1, (int) $request->get_param( 'limit' ) ) );

		$args = array(
			'offset' => $offset,
			'limit'       => $limit,
			'engine'      => 'trending',
			'category_id' => $category_id,
		);

		$recommendations = $this->manager->getRecommendations( 'category_page', 0, $args );

		$products_data = array();
		foreach ( $recommendations as $rec ) {
			$product = wc_get_product( $rec['product_id'] );
			if ( ! $product ) {
				continue;
			}

			$products_data[] = array(
				'id'        => $product->get_id(),
				'title'     => $product->get_name(),
				'permalink' => $product->get_permalink(),
				'image_url' => wp_get_attachment_url( $product->get_image_id() ),
				'price'     => $product->get_price(),
				'rating'    => $product->get_average_rating(),
				'score'     => $rec['score'],
			);
		}

		return new \WP_REST_Response( array( 'products' => $products_data ), 200 );
	}

	/**
	 * Handle analytics request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_analytics( $request ) {
		$metric    = $request->get_param( 'metric' );
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		global $wpdb;

		$data = array();

		switch ( $metric ) {
			case 'clicks':
				$data = $this->get_click_analytics( $date_from, $date_to );
				break;

			case 'impressions':
				$data = $this->get_event_analytics( 'view', $date_from, $date_to );
				break;

			case 'top_products':
				$data = $this->get_top_recommended_products( $date_from, $date_to );
				break;

			case 'ctr':
				$clicks      = $this->get_event_count( 'click', $date_from, $date_to );
				$impressions = $this->get_event_count( 'view', $date_from, $date_to );
				$data        = array(
					'clicks'      => $clicks,
					'impressions' => $impressions,
					'ctr'         => $impressions > 0 ? round( $clicks / $impressions * 100, 2 ) : 0,
				);
				break;
		}

		return new \WP_REST_Response( array( 'data' => $data ), 200 );
	}

	/**
	 * Check admin permission.
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Get click analytics data.
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return array
	 */
	private function get_click_analytics( string $date_from, string $date_to ): array {
		return $this->get_event_analytics( 'click', $date_from, $date_to );
	}

	/**
	 * Get event analytics grouped by date.
	 *
	 * @param string $event_type Event type.
	 * @param string $date_from  Start date.
	 * @param string $date_to    End date.
	 * @return array
	 */
	private function get_event_analytics( string $event_type, string $date_from, string $date_to ): array {
		global $wpdb;

		$where = $wpdb->prepare( 'WHERE event_type = %s', $event_type );
		$args  = array();

		if ( ! empty( $date_from ) ) {
			$where .= $wpdb->prepare( ' AND created_at >= %s', $date_from );
		}
		if ( ! empty( $date_to ) ) {
			$where .= $wpdb->prepare( ' AND created_at <= %s', $date_to );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			"SELECT DATE(created_at) as date, COUNT(*) as count
			FROM {$wpdb->prefix}smartrec_events
			{$where}
			GROUP BY DATE(created_at)
			ORDER BY date ASC",
			ARRAY_A
		);
	}

	/**
	 * Get event count.
	 *
	 * @param string $event_type Event type.
	 * @param string $date_from  Start date.
	 * @param string $date_to    End date.
	 * @return int
	 */
	private function get_event_count( string $event_type, string $date_from, string $date_to ): int {
		global $wpdb;

		$where = $wpdb->prepare( 'WHERE event_type = %s', $event_type );
		if ( ! empty( $date_from ) ) {
			$where .= $wpdb->prepare( ' AND created_at >= %s', $date_from );
		}
		if ( ! empty( $date_to ) ) {
			$where .= $wpdb->prepare( ' AND created_at <= %s', $date_to );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}smartrec_events {$where}"
		);
	}

	/**
	 * Get top recommended products.
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to   End date.
	 * @return array
	 */
	private function get_top_recommended_products( string $date_from, string $date_to ): array {
		global $wpdb;

		$where = "WHERE event_type = 'click'";
		if ( ! empty( $date_from ) ) {
			$where .= $wpdb->prepare( ' AND created_at >= %s', $date_from );
		}
		if ( ! empty( $date_to ) ) {
			$where .= $wpdb->prepare( ' AND created_at <= %s', $date_to );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			"SELECT product_id, COUNT(*) as clicks
			FROM {$wpdb->prefix}smartrec_events
			{$where}
			GROUP BY product_id
			ORDER BY clicks DESC
			LIMIT 20",
			ARRAY_A
		);
	}
}
