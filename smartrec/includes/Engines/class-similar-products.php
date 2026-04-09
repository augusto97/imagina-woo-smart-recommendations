<?php
/**
 * Similar Products engine — content-based filtering.
 *
 * @package SmartRec\Engines
 */

namespace SmartRec\Engines;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SimilarProducts
 *
 * Recommends products based on shared categories, tags, and attributes.
 */
class SimilarProducts implements RecommendationEngineInterface {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getId(): string {
		return 'similar_products';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Similar Products', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return __( 'Recommends products with similar categories, tags, and attributes.', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable(): bool {
		return (bool) $this->settings->get( 'engine_similar_enabled', true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecommendations( int $productId, int $userId, string $sessionId, array $args = array() ): array {
		// If no product specified (e.g. homepage shortcode), find the last viewed product.
		if ( $productId <= 0 && ! empty( $sessionId ) ) {
			$productId = $this->get_last_viewed_product( $userId, $sessionId );
		}

		if ( $productId <= 0 ) {
			return array();
		}

		$product = wc_get_product( $productId );
		if ( ! $product ) {
			return array();
		}

		$limit   = $args['limit'] ?? $this->getDefaultLimit();
		$exclude = $args['exclude'] ?? array();
		$exclude[] = $productId;

		// Get product attributes.
		$categories = $product->get_category_ids();
		$tags       = $product->get_tag_ids();
		$price      = (float) $product->get_price();
		$type       = $product->get_type();
		$attributes = $this->get_product_attribute_values( $product );

		// Query candidate products.
		$candidates = $this->get_candidates( $categories, $tags, $exclude, $limit * 5 );

		// Batch-prime cache: 2-3 queries instead of N individual ones.
		if ( ! empty( $candidates ) ) {
			_prime_post_caches( $candidates, true );
			update_meta_cache( 'post', $candidates );
		}

		// Score candidates (all wc_get_product calls now hit in-memory cache).
		$scored = array();
		foreach ( $candidates as $candidate_id ) {
			$candidate = wc_get_product( $candidate_id );
			if ( ! $candidate || ! $candidate->is_in_stock() || 'publish' !== $candidate->get_status() ) {
				continue;
			}

			$score = $this->calculate_score( $candidate, $categories, $tags, $attributes, $price, $type );
			if ( $score > 0 ) {
				$scored[] = array(
					'product_id' => $candidate_id,
					'score'      => $score,
					'reason'     => __( 'Similar to this product', 'smartrec' ),
				);
			}
		}

		// Sort by score DESC.
		usort(
			$scored,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		// Normalize scores.
		$scored = $this->normalize_scores( $scored );

		$results = array_slice( $scored, 0, $limit );

		return apply_filters( 'smartrec_engine_results', $results, $this->getId(), $productId );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDefaultLimit(): int {
		return 8;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority(): int {
		return (int) $this->settings->get( 'engine_similar_priority', 5 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMinimumDataRequirements(): array {
		return array(
			'description' => __( 'At least one product with categories or tags.', 'smartrec' ),
			'min_products' => 2,
		);
	}

	/**
	 * Get candidate product IDs from the same categories and tags.
	 *
	 * @param array $categories Category IDs.
	 * @param array $tags       Tag IDs.
	 * @param array $exclude    Product IDs to exclude.
	 * @param int   $limit      Max candidates.
	 * @return array Product IDs.
	 */
	private function get_candidates( array $categories, array $tags, array $exclude, int $limit ): array {
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__not_in'   => $exclude,
			'fields'         => 'ids',
			'tax_query'      => array(
				'relation' => 'OR',
			),
		);

		if ( ! empty( $categories ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $categories,
			);
		}

		if ( ! empty( $tags ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'term_id',
				'terms'    => $tags,
			);
		}

		// If no categories or tags, fall back to all products.
		if ( empty( $categories ) && empty( $tags ) ) {
			unset( $query_args['tax_query'] );
		}

		$query = new \WP_Query( $query_args );
		return $query->posts;
	}

	/**
	 * Calculate similarity score for a candidate product.
	 *
	 * @param \WC_Product $candidate  Candidate product.
	 * @param array       $categories Source categories.
	 * @param array       $tags       Source tags.
	 * @param array       $attributes Source attributes.
	 * @param float       $price      Source price.
	 * @param string      $type       Source product type.
	 * @return float
	 */
	private function calculate_score( $candidate, array $categories, array $tags, array $attributes, float $price, string $type ): float {
		$score = 0.0;

		// Shared categories: +0.3 each.
		$candidate_cats   = $candidate->get_category_ids();
		$shared_cats      = array_intersect( $categories, $candidate_cats );
		$score           += count( $shared_cats ) * 0.3;

		// Shared tags: +0.15 each.
		$candidate_tags   = $candidate->get_tag_ids();
		$shared_tags      = array_intersect( $tags, $candidate_tags );
		$score           += count( $shared_tags ) * 0.15;

		// Shared attribute values: +0.2 each.
		$candidate_attrs  = $this->get_product_attribute_values( $candidate );
		foreach ( $attributes as $attr_name => $values ) {
			if ( isset( $candidate_attrs[ $attr_name ] ) ) {
				$shared = array_intersect( $values, $candidate_attrs[ $attr_name ] );
				$score += count( $shared ) * 0.2;
			}
		}

		// Price within ±30%: +0.1.
		if ( $price > 0 ) {
			$candidate_price = (float) $candidate->get_price();
			if ( $candidate_price > 0 ) {
				$ratio = $candidate_price / $price;
				if ( $ratio >= 0.7 && $ratio <= 1.3 ) {
					$score += 0.1;
				}
			}
		}

		// Same product type: +0.05.
		if ( $type === $candidate->get_type() ) {
			$score += 0.05;
		}

		return $score;
	}

	/**
	 * Get attribute values for a product.
	 *
	 * @param \WC_Product $product Product.
	 * @return array Associative array of attribute_name => array of values.
	 */
	private function get_product_attribute_values( $product ): array {
		$result     = array();
		$attributes = $product->get_attributes();

		foreach ( $attributes as $attr_name => $attribute ) {
			if ( $attribute instanceof \WC_Product_Attribute ) {
				$terms = $attribute->get_terms();
				if ( $terms ) {
					$result[ $attr_name ] = wp_list_pluck( $terms, 'term_id' );
				} else {
					$options = $attribute->get_options();
					if ( $options ) {
						$result[ $attr_name ] = $options;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Normalize scores to 0-1 range.
	 *
	 * @param array $scored Scored products.
	 * @return array
	 */
	/**
	 * Get the last product viewed by this user/session.
	 *
	 * @param int    $userId    User ID.
	 * @param string $sessionId Session ID.
	 * @return int Product ID or 0.
	 */
	private function get_last_viewed_product( int $userId, string $sessionId ): int {
		global $wpdb;

		$where = '';
		$args  = array();

		if ( $userId > 0 ) {
			$where = 'WHERE (user_id = %d OR session_id = %s) AND event_type = %s';
			$args  = array( $userId, $sessionId, 'view' );
		} else {
			$where = 'WHERE session_id = %s AND event_type = %s';
			$args  = array( $sessionId, 'view' );
		}

		$args[] = 1;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT product_id FROM {$wpdb->prefix}smartrec_events {$where} ORDER BY created_at DESC LIMIT %d",
				$args
			)
		);

		return (int) $product_id;
	}

	private function normalize_scores( array $scored ): array {
		if ( empty( $scored ) ) {
			return $scored;
		}

		$max_score = $scored[0]['score'];
		if ( $max_score <= 0 ) {
			return $scored;
		}

		foreach ( $scored as &$item ) {
			$item['score'] = round( $item['score'] / $max_score, 6 );
		}

		return $scored;
	}
}
