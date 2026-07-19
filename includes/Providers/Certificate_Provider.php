<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

use TutorLMS_Analytics\Date_Range;
use TutorLMS_Analytics\Tutor_Schema;

/**
 * Certificate analytics.
 *
 * In Tutor LMS a course completion is a wp_comments row with
 * comment_type = 'course_completed' whose comment_content stores a unique
 * 16-char certificate hash (verified 4.0.1). A completion with a non-empty
 * hash is treated as an issued certificate; verification uses the same hash.
 */
class Certificate_Provider {

	/**
	 * @param int             $course_id Optional course filter (0 = store-wide).
	 * @param Date_Range|null $range     Date range; defaults to the last 30 days.
	 */
	public function get_certificate_stats( int $course_id = 0, ?Date_Range $range = null ): array {
		global $wpdb;

		$range = $range ?? Date_Range::last_days( 30 );

		$stats = array(
			'available'                      => false,
			'issued_in_range'                => 0,
			'completion_to_certificate_rate' => 0.0,
			'monthly_trend'                  => array( 'labels' => array(), 'data' => array() ),
			'per_course'                     => array(),
		);

		$completed_type = Tutor_Schema::CT_COMPLETED;

		$available = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->comments} WHERE comment_type = %s LIMIT 1",
				$completed_type
			)
		);
		if ( ! $available ) {
			return $stats;
		}
		$stats['available'] = true;

		$course_where = '';
		if ( $course_id > 0 ) {
			$course_where = $wpdb->prepare( ' AND comment_post_ID = %d', $course_id );
		}

		// Certificates issued within range (completion rows with a hash).
		$stats['issued_in_range'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->comments}
				WHERE comment_type = %s
					AND comment_approved IN ('approved', '1')
					AND comment_content <> ''
					AND comment_date BETWEEN %s AND %s
					{$course_where}",
				$completed_type,
				$range->start_sql(),
				$range->end_sql()
			)
		);

		// All-time completion → certificate rate (share of completions that carry a hash).
		$rate_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS completions,
						SUM(CASE WHEN comment_content <> '' THEN 1 ELSE 0 END) AS with_hash
				FROM {$wpdb->comments}
				WHERE comment_type = %s
					AND comment_approved IN ('approved', '1')
					{$course_where}",
				$completed_type
			),
			ARRAY_A
		);
		$rate_row    = (array) ( $rate_row ?? array() );
		$completions = (int) ( $rate_row['completions'] ?? 0 );
		$with_hash   = (int) ( $rate_row['with_hash'] ?? 0 );
		if ( $completions > 0 ) {
			$stats['completion_to_certificate_rate'] = round( $with_hash / $completions * 100, 1 );
		}

		// Monthly issued trend (last 6 months, zero-filled).
		$trend_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(comment_date, '%%Y-%%m') AS ym, COUNT(*) AS issued
				FROM {$wpdb->comments}
				WHERE comment_type = %s
					AND comment_approved IN ('approved', '1')
					AND comment_content <> ''
					AND comment_date >= %s
					{$course_where}
				GROUP BY ym",
				$completed_type,
				gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of -5 months' ) )
			),
			ARRAY_A
		);
		$by_month = array();
		foreach ( (array) $trend_rows as $row ) {
			$row                            = (array) $row;
			$by_month[ (string) $row['ym'] ] = (int) $row['issued'];
		}
		for ( $i = 5; $i >= 0; $i-- ) {
			$ym                        = gmdate( 'Y-m', strtotime( "first day of -{$i} months" ) );
			$stats['monthly_trend']['labels'][] = $ym;
			$stats['monthly_trend']['data'][]   = $by_month[ $ym ] ?? 0;
		}

		// Top courses by certificates issued (global view only, all-time).
		if ( 0 === $course_id ) {
			$course_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT comment_post_ID AS course_id, COUNT(*) AS issued
					FROM {$wpdb->comments}
					WHERE comment_type = %s
						AND comment_approved IN ('approved', '1')
						AND comment_content <> ''
					GROUP BY comment_post_ID
					ORDER BY issued DESC
					LIMIT 5",
					$completed_type
				),
				ARRAY_A
			);
			foreach ( (array) $course_rows as $row ) {
				$row = (array) $row;
				$cid = (int) ( $row['course_id'] ?? 0 );
				$stats['per_course'][] = array(
					'id'     => $cid,
					'title'  => get_the_title( $cid ),
					'issued' => (int) ( $row['issued'] ?? 0 ),
				);
			}
		}

		return $stats;
	}
}
