<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Rating_Analytics_Provider {

	/**
	 * Get all rating analytics for a course.
	 */
	public function get_rating_analytics( int $course_id = 0 ): array {
		return array(
			'distribution'         => $this->get_rating_distribution( $course_id ),
			'nps_score'            => $this->get_nps_score( $course_id ),
			'rating_trend'         => $this->get_rating_trend( $course_id ),
			'review_response_rate' => $this->get_review_response_rate( $course_id ),
		);
	}

	/**
	 * Rating distribution: count of 1★ through 5★.
	 */
	public function get_rating_distribution( int $course_id = 0 ): array {
		global $wpdb;

		$where = "c.comment_type = 'tutor_course_rating' AND c.comment_approved = 'approved' AND m.meta_key = 'tutor_rating'";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND c.comment_post_ID = %d", $course_id );
		}

		$results = $wpdb->get_results( "
			SELECT CAST(m.meta_value AS UNSIGNED) as stars, COUNT(*) as count
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
			WHERE {$where}
			GROUP BY CAST(m.meta_value AS UNSIGNED)
			ORDER BY stars ASC
		", ARRAY_A );

		// Initialize all 5 star levels
		$dist = array(
			'1★' => 0,
			'2★' => 0,
			'3★' => 0,
			'4★' => 0,
			'5★' => 0,
		);

		foreach ( (array) $results as $row ) {
			$stars = (int) $row['stars'];
			if ( $stars >= 1 && $stars <= 5 ) {
				$dist[ $stars . '★' ] = (int) $row['count'];
			}
		}

		return $dist;
	}

	/**
	 * Net Promoter Score (NPS).
	 * 4-5★ = Promoter, 3★ = Passive, 1-2★ = Detractor
	 * NPS = Promoter% - Detractor%  (range: -100 to +100)
	 */
	public function get_nps_score( int $course_id = 0 ): array {
		global $wpdb;

		$where = "c.comment_type = 'tutor_course_rating' AND c.comment_approved = 'approved' AND m.meta_key = 'tutor_rating'";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND c.comment_post_ID = %d", $course_id );
		}

		$stats = $wpdb->get_row( "
			SELECT 
				COUNT(*) as total,
				SUM(CASE WHEN CAST(m.meta_value AS UNSIGNED) >= 4 THEN 1 ELSE 0 END) as promoters,
				SUM(CASE WHEN CAST(m.meta_value AS UNSIGNED) = 3 THEN 1 ELSE 0 END) as passives,
				SUM(CASE WHEN CAST(m.meta_value AS UNSIGNED) <= 2 THEN 1 ELSE 0 END) as detractors
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
			WHERE {$where}
		" );

		if ( ! $stats || $stats->total == 0 ) {
			return array(
				'score'      => 0,
				'promoters'  => 0,
				'passives'   => 0,
				'detractors' => 0,
				'total'      => 0,
			);
		}

		$promoter_pct  = ($stats->promoters / $stats->total) * 100;
		$detractor_pct = ($stats->detractors / $stats->total) * 100;
		$nps           = round( $promoter_pct - $detractor_pct, 0 );

		return array(
			'score'      => (int) $nps,
			'promoters'  => (int) $stats->promoters,
			'passives'   => (int) $stats->passives,
			'detractors' => (int) $stats->detractors,
			'total'      => (int) $stats->total,
		);
	}

	/**
	 * Average rating per month (last 6 months).
	 */
	public function get_rating_trend( int $course_id = 0 ): array {
		global $wpdb;

		$where = "c.comment_type = 'tutor_course_rating' AND c.comment_approved = 'approved' AND m.meta_key = 'tutor_rating' AND c.comment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND c.comment_post_ID = %d", $course_id );
		}

		$results = $wpdb->get_results( "
			SELECT 
				DATE_FORMAT(c.comment_date, '%Y-%m') as month,
				AVG(CAST(m.meta_value AS DECIMAL(3,1))) as avg_rating,
				COUNT(*) as review_count
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
			WHERE {$where}
			GROUP BY DATE_FORMAT(c.comment_date, '%Y-%m')
			ORDER BY month ASC
		", ARRAY_A );

		$trend = array(
			'labels' => array(),
			'data'   => array(),
			'counts' => array(),
		);

		foreach ( (array) $results as $row ) {
			$trend['labels'][] = $row['month'];
			$trend['data'][]   = round( (float) $row['avg_rating'], 1 );
			$trend['counts'][] = (int) $row['review_count'];
		}

		return $trend;
	}

	/**
	 * Review response rate: % of course completers who left a review.
	 */
	public function get_review_response_rate( int $course_id = 0 ): array {
		global $wpdb;

		// Count completions
		$complete_where = "comment_type = 'course_completed' AND comment_approved = 'approved'";
		if ( $course_id > 0 ) {
			$complete_where .= $wpdb->prepare( " AND comment_post_ID = %d", $course_id );
		}
		$completions = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->comments} WHERE {$complete_where} AND user_id > 0" );

		// Count reviews
		$review_where = "comment_type = 'tutor_course_rating' AND comment_approved = 'approved'";
		if ( $course_id > 0 ) {
			$review_where .= $wpdb->prepare( " AND comment_post_ID = %d", $course_id );
		}
		$reviews = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->comments} WHERE {$review_where} AND user_id > 0" );

		$rate = $completions > 0 ? round( ($reviews / $completions) * 100, 1 ) : 0;

		return array(
			'rate'        => $rate,
			'reviews'     => $reviews,
			'completions' => $completions,
		);
	}
}
