<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

use TutorLMS_Analytics\Date_Range;
use TutorLMS_Analytics\Tutor_Schema;

/**
 * Monetization analytics on top of the Tutor LMS native eCommerce tables
 * (Tutor LMS >= 3.0, schema verified against 4.0.1).
 *
 * Every section degrades gracefully: when a table (or column) is missing the
 * full result shape is still returned with 'available' => false and
 * zeroed/empty values — keys are never omitted.
 */
class Monetization_Provider {

	/**
	 * Aggregate monetization stats.
	 *
	 * @param int             $course_id Optional course filter (0 = store-wide).
	 * @param Date_Range|null $range     Date range; defaults to the last 30 days.
	 */
	public function get_monetization_stats( int $course_id = 0, ?Date_Range $range = null ): array {
		global $wpdb;

		$range = $range ?? Date_Range::last_days( 30 );

		$stats = array(
			'available'          => false,
			'gross_revenue'      => 0.0,
			'net_revenue'        => 0.0,
			'orders_count'       => 0,
			'refund_amount'      => 0.0,
			'refund_rate'        => 0.0,
			'trend'              => array( 'labels' => array(), 'data' => array() ),
			'by_order_type'      => array(
				'single_order' => 0.0,
				'subscription' => 0.0,
				'renewal'      => 0.0,
			),
			'coupon_usage'       => array(
				'available'      => false,
				'total_used'     => 0,
				'total_discount' => 0.0,
				'top_coupons'    => array(),
			),
			'enrollment_sources' => array(
				'bundle'       => 0,
				'subscription' => 0,
				'native'       => 0,
				'external'     => 0,
				'manual_free'  => 0,
			),
			'gifts'              => array(
				'available'       => false,
				'gift_cart_items' => 0,
				'gift_deliveries' => 0,
			),
		);

		// These sections read wp_posts / their own tables and carry their own
		// guards, so they are populated even when the orders table is missing.
		$stats['enrollment_sources'] = $this->get_enrollment_sources( $course_id, $range );
		$stats['gifts']              = $this->get_gift_stats( $range );
		$stats['coupon_usage']       = $this->get_coupon_usage( $course_id, $range );

		if ( ! Tutor_Schema::has_native_orders() ) {
			return $stats;
		}

		$stats['available'] = true;

		$orders_table = Tutor_Schema::orders_table();
		$join         = $this->course_join( $course_id );
		$start_gmt    = $range->start_sql_gmt();
		$end_gmt      = $range->end_sql_gmt();

		// Revenue totals: paid + partially refunded orders in range, trash excluded.
		$totals_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(o.total_price), 0) AS gross_revenue,
						COALESCE(SUM(o.net_payment), 0) AS net_revenue,
						COUNT(DISTINCT o.id) AS orders_count
				FROM {$orders_table} o
				{$join}
				WHERE o.order_status <> 'trash'
					AND o.payment_status IN ('paid', 'partially-refunded')
					AND o.created_at_gmt BETWEEN %s AND %s",
				$start_gmt,
				$end_gmt
			),
			ARRAY_A
		);
		$totals = (array) ( $totals_rows[0] ?? array() );

		$stats['gross_revenue'] = round( (float) ( $totals['gross_revenue'] ?? 0 ), 2 );
		$stats['net_revenue']   = round( (float) ( $totals['net_revenue'] ?? 0 ), 2 );
		$stats['orders_count']  = (int) ( $totals['orders_count'] ?? 0 );

		// Refunds: refund_amount over all (non-trash) orders in range, so fully
		// refunded orders are included even though they are excluded from revenue.
		$refund_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(o.refund_amount), 0) AS refund_amount,
						SUM(CASE WHEN o.payment_status IN ('refunded', 'partially-refunded') THEN 1 ELSE 0 END) AS refunded_orders,
						SUM(CASE WHEN o.payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_orders
				FROM {$orders_table} o
				{$join}
				WHERE o.order_status <> 'trash'
					AND o.created_at_gmt BETWEEN %s AND %s",
				$start_gmt,
				$end_gmt
			),
			ARRAY_A
		);
		$refunds = (array) ( $refund_rows[0] ?? array() );

		$refunded_orders = (int) ( $refunds['refunded_orders'] ?? 0 );
		$paid_orders     = (int) ( $refunds['paid_orders'] ?? 0 );

		$stats['refund_amount'] = round( (float) ( $refunds['refund_amount'] ?? 0 ), 2 );
		if ( ( $refunded_orders + $paid_orders ) > 0 ) {
			$stats['refund_rate'] = round( $refunded_orders / ( $refunded_orders + $paid_orders ) * 100, 1 );
		}

		// Daily net revenue trend.
		$trend_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(o.created_at_gmt) AS trend_day,
						COALESCE(SUM(o.net_payment), 0) AS trend_net
				FROM {$orders_table} o
				{$join}
				WHERE o.order_status <> 'trash'
					AND o.payment_status IN ('paid', 'partially-refunded')
					AND o.created_at_gmt BETWEEN %s AND %s
				GROUP BY DATE(o.created_at_gmt)
				ORDER BY trend_day ASC",
				$start_gmt,
				$end_gmt
			),
			ARRAY_A
		);
		foreach ( (array) $trend_rows as $row ) {
			$row = (array) $row;
			$stats['trend']['labels'][] = (string) ( $row['trend_day'] ?? '' );
			$stats['trend']['data'][]   = round( (float) ( $row['trend_net'] ?? 0 ), 2 );
		}

		// Net revenue split by order type.
		$type_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.order_type AS order_type,
						COALESCE(SUM(o.net_payment), 0) AS type_net
				FROM {$orders_table} o
				{$join}
				WHERE o.order_status <> 'trash'
					AND o.payment_status IN ('paid', 'partially-refunded')
					AND o.created_at_gmt BETWEEN %s AND %s
				GROUP BY o.order_type",
				$start_gmt,
				$end_gmt
			),
			ARRAY_A
		);
		foreach ( (array) $type_rows as $row ) {
			$row  = (array) $row;
			$type = (string) ( $row['order_type'] ?? '' );
			if ( array_key_exists( $type, $stats['by_order_type'] ) ) {
				$stats['by_order_type'][ $type ] = round( (float) ( $row['type_net'] ?? 0 ), 2 );
			}
		}

		return $stats;
	}

	/**
	 * Coupon usage. total_used comes from tutor_coupon_usages which has no date
	 * column (a row is inserted when an order completes with a coupon), so that
	 * count is all-time; discount totals come from orders in range.
	 */
	private function get_coupon_usage( int $course_id, Date_Range $range ): array {
		global $wpdb;

		$usage = array(
			'available'      => false,
			'total_used'     => 0,
			'total_discount' => 0.0,
			'top_coupons'    => array(),
		);

		$usages_table = Tutor_Schema::coupon_usages_table();
		if ( Tutor_Schema::table_exists( $usages_table ) ) {
			$usage['available'] = true;
			// No date column on tutor_coupon_usages — all-time count. It also has
			// no order reference, so it cannot be scoped to a course.
			$usage['total_used'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$usages_table}`" );
		}

		if ( ! Tutor_Schema::has_native_orders() ) {
			return $usage;
		}

		$orders_table = Tutor_Schema::orders_table();
		$join         = $this->course_join( $course_id );
		$start_gmt    = $range->start_sql_gmt();
		$end_gmt      = $range->end_sql_gmt();

		$usage['total_discount'] = round(
			(float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(o.coupon_amount), 0)
					FROM {$orders_table} o
					{$join}
					WHERE o.order_status <> 'trash'
						AND o.payment_status = 'paid'
						AND o.coupon_code IS NOT NULL
						AND o.coupon_code <> ''
						AND o.created_at_gmt BETWEEN %s AND %s",
					$start_gmt,
					$end_gmt
				)
			),
			2
		);

		$coupon_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.coupon_code AS coupon_code,
						COUNT(DISTINCT o.id) AS coupon_uses,
						COALESCE(SUM(o.coupon_amount), 0) AS coupon_discount
				FROM {$orders_table} o
				{$join}
				WHERE o.order_status <> 'trash'
					AND o.payment_status = 'paid'
					AND o.coupon_code IS NOT NULL
					AND o.coupon_code <> ''
					AND o.created_at_gmt BETWEEN %s AND %s
				GROUP BY o.coupon_code
				ORDER BY coupon_uses DESC
				LIMIT 5",
				$start_gmt,
				$end_gmt
			),
			ARRAY_A
		);
		foreach ( (array) $coupon_rows as $row ) {
			$row = (array) $row;
			$usage['top_coupons'][] = array(
				'code'     => (string) ( $row['coupon_code'] ?? '' ),
				'uses'     => (int) ( $row['coupon_uses'] ?? 0 ),
				'discount' => round( (float) ( $row['coupon_discount'] ?? 0 ), 2 ),
			);
		}

		return $usage;
	}

	/**
	 * Classify enrollments (tutor_enrolled posts) by how they were created.
	 *
	 * Set-based: one aggregate query with two postmeta LEFT JOINs plus a LEFT
	 * JOIN against tutor_orders (only when that table exists).
	 *
	 * - bundle:      enrollment has a '_tutor_bundle_id' meta.
	 * - subscription/native: '_tutor_enrolled_by_order_id' resolves to a native
	 *   order; subscription when its order_type is subscription/renewal.
	 * - external:    order-backed but the id is not in tutor_orders (WooCommerce/EDD).
	 * - manual_free: no '_tutor_enrolled_by_order_id' meta at all.
	 */
	private function get_enrollment_sources( int $course_id, Date_Range $range ): array {
		global $wpdb;

		$sources = array(
			'bundle'       => 0,
			'subscription' => 0,
			'native'       => 0,
			'external'     => 0,
			'manual_free'  => 0,
		);

		$order_join = '';
		if ( Tutor_Schema::has_native_orders() ) {
			$orders_table      = Tutor_Schema::orders_table();
			$order_join        = "LEFT JOIN {$orders_table} o ON o.id = pm_order.meta_value";
			$subscription_case = "pm_bundle.post_id IS NULL AND pm_order.post_id IS NOT NULL AND o.id IS NOT NULL AND o.order_type IN ('subscription', 'renewal')";
			$native_case       = "pm_bundle.post_id IS NULL AND pm_order.post_id IS NOT NULL AND o.id IS NOT NULL AND o.order_type NOT IN ('subscription', 'renewal')";
			$external_case     = 'pm_bundle.post_id IS NULL AND pm_order.post_id IS NOT NULL AND o.id IS NULL';
		} else {
			// Without the native orders table every order-backed enrollment must
			// have come from an external engine (WooCommerce/EDD).
			$subscription_case = '1 = 0';
			$native_case       = '1 = 0';
			$external_case     = 'pm_bundle.post_id IS NULL AND pm_order.post_id IS NOT NULL';
		}

		$course_where = '';
		if ( $course_id > 0 ) {
			$course_where = $wpdb->prepare( ' AND e.post_parent = %d', $course_id );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SUM(CASE WHEN pm_bundle.post_id IS NOT NULL THEN 1 ELSE 0 END) AS src_bundle,
						SUM(CASE WHEN {$subscription_case} THEN 1 ELSE 0 END) AS src_subscription,
						SUM(CASE WHEN {$native_case} THEN 1 ELSE 0 END) AS src_native,
						SUM(CASE WHEN {$external_case} THEN 1 ELSE 0 END) AS src_external,
						SUM(CASE WHEN pm_bundle.post_id IS NULL AND pm_order.post_id IS NULL THEN 1 ELSE 0 END) AS src_manual_free
				FROM {$wpdb->posts} e
				LEFT JOIN {$wpdb->postmeta} pm_bundle ON pm_bundle.post_id = e.ID AND pm_bundle.meta_key = '_tutor_bundle_id'
				LEFT JOIN {$wpdb->postmeta} pm_order ON pm_order.post_id = e.ID AND pm_order.meta_key = '_tutor_enrolled_by_order_id'
				{$order_join}
				WHERE e.post_type = %s
					AND e.post_status IN ('completed', 'processing', 'publish')
					AND e.post_date BETWEEN %s AND %s
					{$course_where}",
				Tutor_Schema::PT_ENROLLMENT,
				$range->start_sql(),
				$range->end_sql()
			),
			ARRAY_A
		);
		$row = (array) ( $rows[0] ?? array() );

		$sources['bundle']       = (int) ( $row['src_bundle'] ?? 0 );
		$sources['subscription'] = (int) ( $row['src_subscription'] ?? 0 );
		$sources['native']       = (int) ( $row['src_native'] ?? 0 );
		$sources['external']     = (int) ( $row['src_external'] ?? 0 );
		$sources['manual_free']  = (int) ( $row['src_manual_free'] ?? 0 );

		return $sources;
	}

	/**
	 * Gift purchase signals (Tutor LMS >= 3.8).
	 */
	private function get_gift_stats( Date_Range $range ): array {
		global $wpdb;

		$gifts = array(
			'available'       => false,
			'gift_cart_items' => 0,
			'gift_deliveries' => 0,
		);

		$cart_table = Tutor_Schema::cart_items_table();
		// The item_type column only exists since Tutor 3.8, so guard on the
		// column as well as the table (the table may predate the column).
		if ( Tutor_Schema::table_exists( $cart_table ) && in_array( 'item_type', $this->table_columns( $cart_table ), true ) ) {
			$gifts['available'] = true;
			// Cart rows carry no reliable timestamp — all-time, store-wide count.
			$gifts['gift_cart_items'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM `{$cart_table}` WHERE item_type = 'gift'"
			);
		}

		$scheduler_table = Tutor_Schema::scheduler_table();
		if ( Tutor_Schema::table_exists( $scheduler_table ) ) {
			$gifts['available'] = true;
			if ( in_array( 'scheduled_at_gmt', $this->table_columns( $scheduler_table ), true ) ) {
				$gifts['gift_deliveries'] = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM `{$scheduler_table}` WHERE type = 'gift' AND scheduled_at_gmt BETWEEN %s AND %s",
						$range->start_sql_gmt(),
						$range->end_sql_gmt()
					)
				);
			} else {
				$gifts['gift_deliveries'] = (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM `{$scheduler_table}` WHERE type = 'gift'"
				);
			}
		}

		return $gifts;
	}

	/**
	 * Order-items join used to scope order queries to a single course.
	 *
	 * Note: subscription/renewal orders reference a subscription *plan* id in
	 * order_items.item_id (not a course id), so plan-based orders will not
	 * match a course filter. Documented limitation of the native schema.
	 */
	private function course_join( int $course_id ): string {
		global $wpdb;

		if ( $course_id <= 0 ) {
			return '';
		}

		$items_table = Tutor_Schema::order_items_table();

		return $wpdb->prepare(
			"INNER JOIN {$items_table} oi ON oi.order_id = o.id AND oi.item_id = %d",
			$course_id
		);
	}

	/**
	 * Column names of a table, lowercased. Empty array when the table is missing.
	 *
	 * @return string[]
	 */
	private function table_columns( string $table ): array {
		global $wpdb;

		$rows = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );

		$columns = array();
		foreach ( (array) $rows as $row ) {
			$row = (array) $row;
			if ( isset( $row['Field'] ) ) {
				$columns[] = strtolower( (string) $row['Field'] );
			}
		}

		return $columns;
	}
}
