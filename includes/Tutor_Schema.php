<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

/**
 * Capability/feature detection for the Tutor LMS install (verified against Tutor LMS 4.0.1).
 *
 * Every "new" provider must degrade gracefully when a table/addon is missing
 * (e.g. Pro-only features), so all existence checks live here and are
 * request-cached to avoid repeated SHOW TABLES calls.
 */
class Tutor_Schema {

	/** @var array<string,bool> */
	private static array $table_cache = array();

	public static function table_exists( string $table ): bool {
		global $wpdb;

		if ( isset( self::$table_cache[ $table ] ) ) {
			return self::$table_cache[ $table ];
		}

		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		self::$table_cache[ $table ] = ( $found === $table );
		return self::$table_cache[ $table ];
	}

	// ---- Native eCommerce (Tutor LMS >= 3.0, verified 4.0.1) ----

	public static function orders_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_orders';
	}

	public static function order_items_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_order_items';
	}

	public static function ordermeta_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_ordermeta';
	}

	public static function coupon_usages_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_coupon_usages';
	}

	public static function coupons_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_coupons';
	}

	public static function cart_items_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_cart_items';
	}

	public static function scheduler_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_scheduler';
	}

	public static function has_native_orders(): bool {
		return self::table_exists( self::orders_table() );
	}

	// ---- Subscriptions (Pro addon; tables registered by free core) ----

	public static function subscriptions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_subscriptions';
	}

	public static function subscription_plans_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_subscription_plans';
	}

	public static function has_subscriptions(): bool {
		return self::table_exists( self::subscriptions_table() );
	}

	// ---- Quiz ----

	public static function quiz_attempts_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_quiz_attempts';
	}

	public static function quiz_questions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_quiz_questions';
	}

	public static function quiz_attempt_answers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_quiz_attempt_answers';
	}

	public static function has_quiz_tables(): bool {
		return self::table_exists( self::quiz_attempts_table() );
	}

	/**
	 * Question types introduced in Tutor LMS 4.0 (identifiers verified in 4.0.1 source).
	 *
	 * @return string[]
	 */
	public static function new_v4_question_types(): array {
		return array( 'draw_image', 'pin_image', 'coordinates', 'scale', 'puzzle' );
	}

	// ---- Gradebook (Pro addon; DDL lives in free Upgrader) ----

	public static function gradebooks_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_gradebooks';
	}

	public static function gradebook_results_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutor_gradebooks_results';
	}

	public static function has_gradebook(): bool {
		return self::table_exists( self::gradebook_results_table() );
	}

	// ---- Post types / comment types (verified 4.0.1) ----

	public const PT_COURSE        = 'courses';
	public const PT_BUNDLE        = 'course-bundle';
	public const PT_ENROLLMENT    = 'tutor_enrolled';
	public const PT_ASSIGNMENT    = 'tutor_assignments';
	public const PT_ZOOM_LESSON   = 'tutor_zoom_meeting';
	public const PT_MEET_LESSON   = 'tutor-google-meet';

	public const CT_QNA           = 'tutor_q_and_a';
	public const CT_ASSIGNMENT    = 'tutor_assignment';
	public const CT_COMPLETED     = 'course_completed';
	public const CT_RATING        = 'tutor_course_rating';

	public static function has_bundles(): bool {
		global $wpdb;
		static $has = null;
		if ( null === $has ) {
			$has = (bool) $wpdb->get_var(
				$wpdb->prepare( "SELECT 1 FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1", self::PT_BUNDLE )
			);
		}
		return $has;
	}

	public static function has_live_lessons(): bool {
		global $wpdb;
		static $has = null;
		if ( null === $has ) {
			$has = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->posts} WHERE post_type IN (%s, %s) LIMIT 1",
					self::PT_ZOOM_LESSON,
					self::PT_MEET_LESSON
				)
			);
		}
		return $has;
	}

	/** Tutor LMS core version, when available. */
	public static function tutor_version(): string {
		return defined( 'TUTOR_VERSION' ) ? (string) TUTOR_VERSION : '';
	}
}
