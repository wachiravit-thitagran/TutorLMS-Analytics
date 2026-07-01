<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Course_Performance_Provider {

	/**
	 * Get table data for course performance.
	 */
	public function get_course_table( int $course_id = 0 ): array {
		global $wpdb;

		$where = "post_type = 'courses' AND post_status = 'publish'";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND ID = %d", $course_id );
		}

		$courses = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE {$where} ORDER BY post_title ASC", ARRAY_A );
		if ( empty( $courses ) ) {
			return array();
		}

		$table_data = array();
		
		// For WooCommerce revenue
		$wc_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_order_product_lookup'") === $wpdb->prefix . 'wc_order_product_lookup';

		foreach ( $courses as $course ) {
			$cid = (int) $course['ID'];

			// 1. Total Learners
			$learners = (int) $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT(DISTINCT post_author) 
				FROM {$wpdb->posts} 
				WHERE post_parent = %d AND post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')
			", $cid ) );

			// 2. Completions
			$completions = (int) $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT(DISTINCT comment_ID) 
				FROM {$wpdb->comments} 
				WHERE comment_post_ID = %d AND comment_type = 'course_completed' AND comment_approved IN ('approved', '1')
			", $cid ) );

			$completion_rate = $learners > 0 ? round( ( $completions / $learners ) * 100, 1 ) : 0;

			// 3. Avg Rating
			$rating_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type = 'tutor_course_rating' AND comment_approved IN ('approved', '1')", $cid ) );
			
			$avg_rating = 0;
			if ( $rating_count > 0 ) {
				$total_rating = (int) $wpdb->get_var( $wpdb->prepare( "
					SELECT SUM(m.meta_value) 
					FROM {$wpdb->commentmeta} m 
					INNER JOIN {$wpdb->comments} c ON m.comment_id = c.comment_ID 
					WHERE c.comment_post_ID = %d AND c.comment_type = 'tutor_course_rating' AND c.comment_approved IN ('approved', '1') AND m.meta_key = 'tutor_rating'
				", $cid ) );
				$avg_rating = round( $total_rating / $rating_count, 1 );
			}

			// 4. Revenue (WooCommerce)
			$revenue = 0;
			if ( $wc_table_exists ) {
				$product_id = (int) get_post_meta( $cid, '_tutor_course_product_id', true );
				if ( $product_id > 0 ) {
					$revenue = (float) $wpdb->get_var( "
						SELECT SUM(product_net_revenue) 
						FROM {$wpdb->prefix}wc_order_product_lookup 
						WHERE product_id = {$product_id}
					" );
				}
			}

			// 5. Course Health Score (Simplified: Completion*0.4 + Rating(out of 5)*10 + Enrolled(if >0)*10 )
			$health = min( 100, round( ($completion_rate * 0.5) + (($avg_rating / 5) * 40) + ($learners > 0 ? 10 : 0) ) );

			$table_data[] = array(
				'id'              => $cid,
				'title'           => $course['post_title'],
				'learners'        => $learners,
				'completion_rate' => $completion_rate,
				'avg_rating'      => $avg_rating,
				'revenue'         => $revenue,
				'health'          => $health,
			);
		}

		// Sort by health descending
		usort( $table_data, function( $a, $b ) {
			return $b['health'] <=> $a['health'];
		});

		return $table_data;
	}
}
