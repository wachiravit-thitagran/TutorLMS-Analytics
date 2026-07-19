<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

use TutorLMS_Analytics\Date_Range;
use TutorLMS_Analytics\Tutor_Schema;

/**
 * Subscription analytics on top of Tutor LMS native eCommerce orders
 * (order_type 'subscription'/'renewal', verified 4.0.1) and the Pro
 * tutor_subscriptions tables.
 *
 * The column layout of tutor_subscriptions is NOT guaranteed across Pro
 * versions, so columns are detected at runtime and status-based metrics
 * return null when the 'status' column is unavailable.
 */
class Subscription_Provider {

	/**
	 * Aggregate subscription stats.
	 *
	 * @param Date_Range|null $range Date range; defaults to the last 30 days.
	 */
	public function get_subscription_stats( ?Date_Range $range = null ): array {
		global $wpdb;

		$range = $range ?? Date_Range::last_days( 30 );

		$stats = array(
			'available'                => false,
			'active_subscriptions'     => null,
			'cancelled_subscriptions'  => null,
			'churn_rate'               => null,
			'new_subscriptions'        => 0,
			'renewals'                 => 0,
			'subscription_net_revenue' => 0.0,
			'mrr_estimate'             => 0.0,
			'trend'                    => array( 'labels' => array(), 'data' => array() ),
			'plans'                    => array(),
		);

		if ( ! Tutor_Schema::has_native_orders() ) {
			return $stats;
		}

		$orders_table   = Tutor_Schema::orders_table();
		$has_subs_table = Tutor_Schema::has_subscriptions();

		if ( ! $has_subs_table ) {
			// No Pro subscriptions table: only available when native orders show
			// at least one subscription-type order.
			$has_subscription_orders = (bool) $wpdb->get_var(
				"SELECT 1 FROM {$orders_table} WHERE order_type = 'subscription' LIMIT 1"
			);
			if ( ! $has_subscription_orders ) {
				return $stats;
			}
		}

		$stats['available'] = true;

		if ( $has_subs_table ) {
			$subscriptions_table = Tutor_Schema::subscriptions_table();
			// Detect columns at runtime — only use 'status' when present,
			// otherwise the status-based metrics stay null.
			$columns = $this->table_columns( $subscriptions_table );
			if ( in_array( 'status', $columns, true ) ) {
				$active    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$subscriptions_table}` WHERE status = 'active'" );
				$cancelled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$subscriptions_table}` WHERE status IN ('cancelled', 'canceled')" );

				$stats['active_subscriptions']    = $active;
				$stats['cancelled_subscriptions'] = $cancelled;
				if ( ( $active + $cancelled ) > 0 ) {
					$stats['churn_rate'] = round( $cancelled / ( $active + $cancelled ) * 100, 1 );
				}
			}
		}

		$start_gmt = $range->start_sql_gmt();
		$end_gmt   = $range->end_sql_gmt();

		// New subscriptions, renewals and their net revenue for the range.
		$totals_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SUM(CASE WHEN o.order_type = 'subscription' THEN 1 ELSE 0 END) AS new_subscriptions,
						SUM(CASE WHEN o.order_type = 'renewal' THEN 1 ELSE 0 END) AS renewals,
						COALESCE(SUM(o.net_payment), 0) AS subscription_net_revenue
				FROM {$orders_table} o
				WHERE o.order_status <> 'trash'
					AND o.order_type IN ('subscription', 'renewal')
					AND o.payment_status = 'paid'
					AND o.created_at_gmt BETWEEN %s AND %s",
				$start_gmt,
				$end_gmt
			),
			ARRAY_A
		);
		$totals = (array) ( $totals_rows[0] ?? array() );

		$stats['new_subscriptions']        = (int) ( $totals['new_subscriptions'] ?? 0 );
		$stats['renewals']                 = (int) ( $totals['renewals'] ?? 0 );
		$stats['subscription_net_revenue'] = round( (float) ( $totals['subscription_net_revenue'] ?? 0 ), 2 );

		// MRR approximation: in-range subscription net revenue normalized to a
		// 30-day month. This is an estimate, not contract-based MRR.
		$stats['mrr_estimate'] = round( $stats['subscription_net_revenue'] / $range->days() * 30, 2 );

		// Daily count of paid subscription + renewal orders.
		$trend_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(o.created_at_gmt) AS sub_day,
						COUNT(*) AS sub_orders
				FROM {$orders_table} o
				WHERE o.order_status <> 'trash'
					AND o.order_type IN ('subscription', 'renewal')
					AND o.payment_status = 'paid'
					AND o.created_at_gmt BETWEEN %s AND %s
				GROUP BY DATE(o.created_at_gmt)
				ORDER BY sub_day ASC",
				$start_gmt,
				$end_gmt
			),
			ARRAY_A
		);
		foreach ( (array) $trend_rows as $row ) {
			$row = (array) $row;
			$stats['trend']['labels'][] = (string) ( $row['sub_day'] ?? '' );
			$stats['trend']['data'][]   = (int) ( $row['sub_orders'] ?? 0 );
		}

		// Per-plan breakdown: order_items.item_id holds the subscription plan id.
		if ( Tutor_Schema::table_exists( Tutor_Schema::subscription_plans_table() )
			&& Tutor_Schema::table_exists( Tutor_Schema::order_items_table() ) ) {

			$plans_table = Tutor_Schema::subscription_plans_table();
			$items_table = Tutor_Schema::order_items_table();

			$plan_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT sp.plan_name AS plan_name,
							COUNT(DISTINCT o.id) AS plan_orders,
							COALESCE(SUM(o.net_payment), 0) AS plan_revenue
					FROM {$orders_table} o
					INNER JOIN {$items_table} oi ON oi.order_id = o.id
					INNER JOIN {$plans_table} sp ON sp.id = oi.item_id
					WHERE o.order_status <> 'trash'
						AND o.order_type IN ('subscription', 'renewal')
						AND o.payment_status = 'paid'
						AND o.created_at_gmt BETWEEN %s AND %s
					GROUP BY sp.id, sp.plan_name
					ORDER BY plan_revenue DESC
					LIMIT 10",
					$start_gmt,
					$end_gmt
				),
				ARRAY_A
			);
			foreach ( (array) $plan_rows as $row ) {
				$row = (array) $row;
				$stats['plans'][] = array(
					'plan_name' => (string) ( $row['plan_name'] ?? '' ),
					'orders'    => (int) ( $row['plan_orders'] ?? 0 ),
					'revenue'   => round( (float) ( $row['plan_revenue'] ?? 0 ), 2 ),
				);
			}
		}

		return $stats;
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
