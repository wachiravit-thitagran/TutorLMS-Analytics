<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Revenue_Provider {

	/**
	 * Get WooCommerce Revenue for the last 30 days.
	 */
	public function get_revenue_stats( int $course_id = 0 ): array {
		global $wpdb;

		// Check if WooCommerce tables exist
		$table_name = $wpdb->prefix . 'wc_order_stats';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

		if ( ! $table_exists ) {
			return array(
				'gross_revenue' => 0,
				'net_revenue'   => 0,
				'orders_count'  => 0,
				'trend'         => array( 'labels' => array(), 'data' => array() ),
			);
		}

		$where = "date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status IN ('wc-completed', 'wc-processing')";

		// If filtering by course_id, we need to join wc_order_product_lookup
		$join = "";
		if ( $course_id > 0 ) {
			// In Tutor LMS, the WooCommerce product ID is stored in course post_meta '_tutor_course_product_id'
			$product_id = (int) get_post_meta( $course_id, '_tutor_course_product_id', true );
			if ( $product_id > 0 ) {
				$lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
				$join = "INNER JOIN {$lookup_table} opl ON os.order_id = opl.order_id AND opl.product_id = {$product_id}";
			} else {
				// Course has no linked product, return 0
				return array(
					'gross_revenue' => 0,
					'net_revenue'   => 0,
					'orders_count'  => 0,
					'trend'         => array( 'labels' => array(), 'data' => array() ),
				);
			}
		}

		// Totals
		$totals = $wpdb->get_row( "
			SELECT 
				SUM(os.gross_total) as gross_revenue,
				SUM(os.net_total) as net_revenue,
				COUNT(DISTINCT os.order_id) as orders_count
			FROM {$table_name} os
			{$join}
			WHERE {$where}
		" );

		// Trend Line
		$trend_results = $wpdb->get_results( "
			SELECT DATE(os.date_created) as date, SUM(os.net_total) as revenue
			FROM {$table_name} os
			{$join}
			WHERE {$where}
			GROUP BY DATE(os.date_created)
			ORDER BY DATE(os.date_created) ASC
		", ARRAY_A );

		$trend = array( 'labels' => array(), 'data' => array() );
		foreach ( (array) $trend_results as $row ) {
			$trend['labels'][] = $row['date'];
			$trend['data'][]   = (float) $row['revenue'];
		}

		return array(
			'gross_revenue' => (float) ( $totals->gross_revenue ?? 0 ),
			'net_revenue'   => (float) ( $totals->net_revenue ?? 0 ),
			'orders_count'  => (int) ( $totals->orders_count ?? 0 ),
			'trend'         => $trend,
		);
	}
}
