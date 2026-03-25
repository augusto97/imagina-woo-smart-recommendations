<?php
/**
 * Session and visitor identification.
 *
 * @package SmartRec\Tracking
 */

namespace SmartRec\Tracking;

use SmartRec\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class SessionManager
 *
 * Manages session identification for visitors via cookies.
 */
class SessionManager {

	/**
	 * Cookie name.
	 *
	 * @var string
	 */
	const COOKIE_NAME = 'smartrec_session';

	/**
	 * Cookie expiry in days.
	 *
	 * @var int
	 */
	const COOKIE_EXPIRY_DAYS = 30;

	/**
	 * Current session ID.
	 *
	 * @var string
	 */
	private $session_id = '';

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
		$this->init();
	}

	/**
	 * Initialize session management.
	 *
	 * @return void
	 */
	private function init() {
		if ( ! $this->is_tracking_allowed() ) {
			return;
		}

		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			$this->session_id = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		}

		if ( empty( $this->session_id ) || ! $this->is_valid_session_id( $this->session_id ) ) {
			$this->session_id = $this->generate_session_id();
			$this->set_cookie();
		}

		// Link session to user on login.
		add_action( 'wp_login', array( $this, 'on_user_login' ), 10, 2 );
	}

	/**
	 * Get the current session ID.
	 *
	 * @return string
	 */
	public function get_session_id() {
		return $this->session_id;
	}

	/**
	 * Get the current user ID.
	 *
	 * @return int
	 */
	public function get_user_id() {
		return get_current_user_id();
	}

	/**
	 * Handle user login — merge anonymous session data.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 * @return void
	 */
	public function on_user_login( $user_login, $user ) {
		global $wpdb;

		if ( empty( $this->session_id ) ) {
			return;
		}

		// Update events from anonymous session to be linked to user.
		$wpdb->update(
			$wpdb->prefix . 'smartrec_events',
			array( 'user_id' => $user->ID ),
			array(
				'session_id' => $this->session_id,
				'user_id'    => 0,
			),
			array( '%d' ),
			array( '%s', '%d' )
		);

		// Update or create user profile.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}smartrec_user_profiles (user_id, session_id, last_active)
				VALUES (%d, %s, %s)
				ON DUPLICATE KEY UPDATE session_id = VALUES(session_id), last_active = VALUES(last_active)",
				$user->ID,
				$this->session_id,
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Check if tracking is allowed for the current visitor.
	 *
	 * @return bool
	 */
	public function is_tracking_allowed() {
		if ( ! $this->settings->get( 'tracking_enabled', true ) ) {
			return false;
		}

		// Respect Do Not Track header.
		if ( $this->settings->get( 'respect_dnt', true ) ) {
			if ( isset( $_SERVER['HTTP_DNT'] ) && '1' === $_SERVER['HTTP_DNT'] ) {
				return false;
			}
		}

		// Check cookie consent.
		$consent_mode = $this->settings->get( 'cookie_consent', 'auto' );
		if ( 'auto' === $consent_mode ) {
			if ( ! $this->check_consent_plugins() ) {
				// No consent plugin found — track by default but allow filter override.
				return apply_filters( 'smartrec_tracking_enabled', true, $this->get_user_id() );
			}
		} elseif ( 'never' === $consent_mode ) {
			return false;
		}

		return apply_filters( 'smartrec_tracking_enabled', true, $this->get_user_id() );
	}

	/**
	 * Check common cookie consent plugins.
	 *
	 * @return bool True if a consent plugin is found and consent is granted.
	 */
	private function check_consent_plugins() {
		// CookieYes.
		if ( isset( $_COOKIE['cookieyes-consent'] ) ) {
			$consent = sanitize_text_field( wp_unslash( $_COOKIE['cookieyes-consent'] ) );
			return strpos( $consent, 'analytics:yes' ) !== false;
		}

		// Complianz.
		if ( isset( $_COOKIE['cmplz_statistics'] ) ) {
			return 'allow' === sanitize_text_field( wp_unslash( $_COOKIE['cmplz_statistics'] ) );
		}

		// CookieBot.
		if ( isset( $_COOKIE['CookieConsent'] ) ) {
			$consent = sanitize_text_field( wp_unslash( $_COOKIE['CookieConsent'] ) );
			return strpos( $consent, 'statistics:true' ) !== false;
		}

		// No consent plugin detected.
		return false;
	}

	/**
	 * Generate a secure session ID.
	 *
	 * @return string
	 */
	private function generate_session_id() {
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( \Exception $e ) {
			return wp_generate_password( 64, false );
		}
	}

	/**
	 * Set the session cookie.
	 *
	 * @return void
	 */
	private function set_cookie() {
		if ( headers_sent() ) {
			return;
		}

		$expiry = time() + ( self::COOKIE_EXPIRY_DAYS * DAY_IN_SECONDS );

		setcookie(
			self::COOKIE_NAME,
			$this->session_id,
			array(
				'expires'  => $expiry,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Validate session ID format.
	 *
	 * @param string $session_id Session ID to validate.
	 * @return bool
	 */
	private function is_valid_session_id( $session_id ) {
		return (bool) preg_match( '/^[a-f0-9]{64}$/', $session_id );
	}
}
