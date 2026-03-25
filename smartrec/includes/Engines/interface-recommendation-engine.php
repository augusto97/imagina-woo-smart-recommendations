<?php
/**
 * Recommendation engine interface.
 *
 * @package SmartRec\Engines
 */

namespace SmartRec\Engines;

defined( 'ABSPATH' ) || exit;

/**
 * Interface RecommendationEngineInterface
 *
 * All recommendation engines must implement this interface.
 */
interface RecommendationEngineInterface {

	/**
	 * Get engine unique identifier.
	 *
	 * @return string
	 */
	public function getId(): string;

	/**
	 * Get human-readable engine name.
	 *
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get engine description.
	 *
	 * @return string
	 */
	public function getDescription(): string;

	/**
	 * Check if this engine is available and properly configured.
	 *
	 * @return bool
	 */
	public function isAvailable(): bool;

	/**
	 * Get recommendations for a specific context.
	 *
	 * @param int    $productId  Current product ID (0 if not on product page).
	 * @param int    $userId     Current user ID (0 if guest).
	 * @param string $sessionId  Current session ID.
	 * @param array  $args       Additional arguments (limit, exclude, context, etc.).
	 * @return array Array of ['product_id' => int, 'score' => float, 'reason' => string].
	 */
	public function getRecommendations( int $productId, int $userId, string $sessionId, array $args = array() ): array;

	/**
	 * Get the default number of products this engine returns.
	 *
	 * @return int
	 */
	public function getDefaultLimit(): int;

	/**
	 * Get engine priority (higher = shown first).
	 *
	 * @return int
	 */
	public function getPriority(): int;

	/**
	 * Get the minimum amount of data needed for this engine to work.
	 *
	 * @return array
	 */
	public function getMinimumDataRequirements(): array;
}
