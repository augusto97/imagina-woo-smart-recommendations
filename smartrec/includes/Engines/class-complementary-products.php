<?php
/**
 * Complementary Products engine — attribute-based cross-selling.
 *
 * @package SmartRec\Engines
 */

namespace SmartRec\Engines;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class ComplementaryProducts
 *
 * Recommends complementary products based on category rules and cross-sell data.
 */
class ComplementaryProducts implements RecommendationEngineInterface {

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
		return 'complementary';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return __( 'Complementary Products', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return __( 'Recommends complementary products for cross-selling based on category rules.', 'smartrec' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable(): bool {
		return (bool) $this->settings->get( 'engine_complementary_enabled', true );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecommendations( int $productId, int $userId, string $sessionId, array $args = array() ): array {
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

		$recommendations = array();

		// 1. Check for manually defined complementary products.
		$manual = get_post_meta( $productId, '_smartrec_complementary_products', true );
		if ( ! empty( $manual ) && is_array( $manual ) ) {
			foreach ( $manual as $manual_id ) {
				$manual_product = wc_get_product( (int) $manual_id );
				if ( $manual_product && $manual_product->is_in_stock() && ! in_array( (int) $manual_id, $exclude, true ) ) {
					$recommendations[] = array(
						'product_id' => (int) $manual_id,
						'score'      => 1.0,
						'reason'     => __( 'Complements this product', 'smartrec' ),
					);
				}
			}
		}

		// 2. Get complementary category rules.
		if ( count( $recommendations ) < $limit ) {
			$rules      = $this->get_complementary_rules( $productId );
			$categories = $product->get_category_ids();

			$complementary_cats = array();
			foreach ( $categories as $cat_id ) {
				foreach ( $rules as $rule ) {
					if ( (int) $rule['source_category'] === $cat_id ) {
						foreach ( $rule['complementary_categories'] as $comp_cat ) {
							$complementary_cats[ (int) $comp_cat ] = (float) ( $rule['weight'] ?? 0.5 );
						}
					}
				}
			}

			if ( ! empty( $complementary_cats ) ) {
				$rule_products = $this->get_products_from_categories(
					array_keys( $complementary_cats ),
					$exclude,
					$limit * 3,
					$product
				);

				foreach ( $rule_products as $rp ) {
					$cat_weight = $complementary_cats[ $rp['category_id'] ] ?? 0.5;
					$recommendations[] = array(
						'product_id' => $rp['product_id'],
						'score'      => $rp['score'] * $cat_weight,
						'reason'     => __( 'Complements this product', 'smartrec' ),
					);
				}
			}
		}

		// 3. Fallback: WooCommerce native cross-sells.
		if ( count( $recommendations ) < $limit ) {
			$cross_sells = $product->get_cross_sell_ids();
			foreach ( $cross_sells as $cs_id ) {
				if ( in_array( $cs_id, $exclude, true ) ) {
					continue;
				}
				$cs_product = wc_get_product( $cs_id );
				if ( $cs_product && $cs_product->is_in_stock() ) {
					$recommendations[] = array(
						'product_id' => $cs_id,
						'score'      => 0.3,
						'reason'     => __( 'You might also need', 'smartrec' ),
					);
				}
			}
		}

		// Deduplicate.
		$seen  = array();
		$unique = array();
		foreach ( $recommendations as $rec ) {
			if ( ! isset( $seen[ $rec['product_id'] ] ) ) {
				$seen[ $rec['product_id'] ] = true;
				$unique[] = $rec;
			}
		}

		// Sort by score.
		usort(
			$unique,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		$results = array_slice( $unique, 0, $limit );

		return apply_filters( 'smartrec_engine_results', $results, $this->getId(), $productId );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDefaultLimit(): int {
		return 6;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority(): int {
		return (int) $this->settings->get( 'engine_complementary_priority', 7 );
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMinimumDataRequirements(): array {
		return array(
			'description' => __( 'Complementary rules or WooCommerce cross-sells configured.', 'smartrec' ),
		);
	}

	/**
	 * Get complementary rules for a product.
	 *
	 * @param int $productId Product ID.
	 * @return array
	 */
	private function get_complementary_rules( int $productId ): array {
		$rules = $this->settings->get( 'complementary_rules', array() );
		return apply_filters( 'smartrec_complementary_rules', $rules, $productId );
	}

	/**
	 * Get products from complementary categories, scored by popularity and price compatibility.
	 *
	 * @param array       $category_ids Category IDs.
	 * @param array       $exclude      Exclude product IDs.
	 * @param int         $limit        Max results.
	 * @param \WC_Product $source       Source product for price comparison.
	 * @return array
	 */
	private function get_products_from_categories( array $category_ids, array $exclude, int $limit, $source ): array {
		global $wpdb;

		$source_price = (float) $source->get_price();
		$results      = array();

		foreach ( $category_ids as $cat_id ) {
			$products = wc_get_products(
				array(
					'status'       => 'publish',
					'limit'        => $limit,
					'category'     => array( (string) $cat_id ),
					'exclude'      => $exclude,
					'orderby'      => 'popularity',
					'order'        => 'DESC',
					'stock_status' => 'instock',
					'return'       => 'ids',
				)
			);

			foreach ( $products as $pid ) {
				$product = wc_get_product( $pid );
				if ( ! $product ) {
					continue;
				}

				$score         = 0.0;
				$product_price = (float) $product->get_price();

				// Popularity: 40%.
				$score += 0.4;

				// Price compatibility: 30%.
				if ( $source_price > 0 && $product_price > 0 ) {
					$ratio = $product_price / $source_price;
					if ( $ratio >= 0.3 && $ratio <= 2.0 ) {
						$score += 0.3;
					}
				}

				// Rating: 30%.
				$rating = (float) $product->get_average_rating();
				if ( $rating > 0 ) {
					$score += ( $rating / 5.0 ) * 0.3;
				}

				$results[] = array(
					'product_id'  => $pid,
					'category_id' => $cat_id,
					'score'       => $score,
				);
			}
		}

		return $results;
	}
}
