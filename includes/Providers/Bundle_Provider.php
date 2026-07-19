<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

use TutorLMS_Analytics\Date_Range;
use TutorLMS_Analytics\Tutor_Schema;

/**
 * Course-bundle analytics (Tutor LMS 4.0 Pro feature; post_type 'course-bundle').
 *
 * Bundle enrollments are ordinary tutor_enrolled posts that carry a
 * '_tutor_bundle_id' postmeta pointing at the bundle. Revenue/orders come from
 * the native eCommerce tables when present.
 *
 * Note: the 4.0 bundle-expiry setting is Pro-only and its storage key is not
 * documented in the free source, so no expiry metric is exposed here.
 */
class Bundle_Provider {

	/**
	 * @param int             $course_id When > 0, only bundles that produced
	 *                                   enrollments for this course are listed.
	 * @param Date_Range|null $range     Date range; defaults to the last 30 days.
	 */
	public function get_bundle_stats( int $course_id = 0, ?Date_Range $range = null ): array {
		global $wpdb;

		$range = $range ?? Date_Range::last_days( 30 );

		$stats = array(
			'available'                   => false,
			'total_bundles'               => 0,
			'bundle_enrollments_in_range' => 0,
			'bundles'                     => array(),
			// Bundle-expiry (4.0 Pro) storage is undocumented in the free source.
			'note_expiry_unavailable'     => true,
		);

		if ( ! Tutor_Schema::has_bundles() ) {
			return $stats;
		}

		$stats['available'] = true;

		$stats['total_bundles'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				Tutor_Schema::PT_BUNDLE
			)
		);

		$course_where = '';
		if ( $course_id > 0 ) {
			$course_where = $wpdb->prepare( ' AND e.post_parent = %d', $course_id );
		}

		// Enrollments in range that were produced by a bundle purchase.
		$stats['bundle_enrollments_in_range'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->posts} e
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = e.ID AND pm.meta_key = '_tutor_bundle_id'
				WHERE e.post_type = %s
					AND e.post_status IN ('completed', 'processing', 'publish')
					AND e.post_date BETWEEN %s AND %s
					{$course_where}",
				Tutor_Schema::PT_ENROLLMENT,
				$range->start_sql(),
				$range->end_sql()
			)
		);

		// Per-bundle roll-up. All-time enrollments/students grouped by bundle id.
		$course_join_where = '';
		if ( $course_id > 0 ) {
			$course_join_where = $wpdb->prepare( ' AND e.post_parent = %d', $course_id );
		}

		$bundle_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS bundle_id,
						COUNT(DISTINCT e.ID) AS enrollments,
						COUNT(DISTINCT e.post_author) AS students
				FROM {$wpdb->posts} e
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = e.ID AND pm.meta_key = '_tutor_bundle_id'
				WHERE e.post_type = %s
					AND e.post_status IN ('completed', 'processing', 'publish')
					{$course_join_where}
				GROUP BY pm.meta_value
				ORDER BY enrollments DESC
				LIMIT 10",
				Tutor_Schema::PT_ENROLLMENT
			),
			ARRAY_A
		);

		$has_orders  = Tutor_Schema::has_native_orders();
		$orders_tbl  = Tutor_Schema::orders_table();
		$items_tbl   = Tutor_Schema::order_items_table();
		$has_items   = Tutor_Schema::table_exists( $items_tbl );

		foreach ( (array) $bundle_rows as $row ) {
			$row       = (array) $row;
			$bundle_id = (int) ( $row['bundle_id'] ?? 0 );
			if ( $bundle_id <= 0 ) {
				continue;
			}

			$orders  = null;
			$revenue = null;

			if ( $has_orders && $has_items ) {
				$order_stats = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT o.id) AS orders,
								COALESCE(SUM(o.net_payment), 0) AS revenue
						FROM {$orders_tbl} o
						INNER JOIN {$items_tbl} oi ON oi.order_id = o.id AND oi.item_id = %d
						WHERE o.order_status <> 'trash'
							AND o.payment_status IN ('paid', 'partially-refunded')",
						$bundle_id
					),
					ARRAY_A
				);
				$order_stats = (array) ( $order_stats ?? array() );
				$orders      = (int) ( $order_stats['orders'] ?? 0 );
				$revenue     = round( (float) ( $order_stats['revenue'] ?? 0 ), 2 );
			}

			$stats['bundles'][] = array(
				'id'          => $bundle_id,
				'title'       => get_the_title( $bundle_id ),
				'enrollments' => (int) ( $row['enrollments'] ?? 0 ),
				'students'    => (int) ( $row['students'] ?? 0 ),
				'orders'      => $orders,
				'revenue'     => $revenue,
			);
		}

		return $stats;
	}
}
