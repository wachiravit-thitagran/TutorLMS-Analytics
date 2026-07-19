<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

use TutorLMS_Analytics\Tutor_Schema;

/**
 * Gradebook analytics (Tutor LMS Pro "Gradebook" addon; tables created by the
 * free-core upgrader when the addon is enabled).
 *
 * Reads {prefix}tutor_gradebooks_results (result_for 'quiz'|'assignment'|'final').
 * Distribution prefers 'final' rows when present, otherwise uses every row.
 */
class Gradebook_Provider {

	/**
	 * @param int $course_id Optional course filter (0 = store-wide).
	 */
	public function get_gradebook_stats( int $course_id = 0 ): array {
		global $wpdb;

		$stats = array(
			'available'          => false,
			'grade_distribution' => array(),
			'avg_percent'        => 0.0,
			'results_count'      => 0,
			'per_course'         => array(),
		);

		if ( ! Tutor_Schema::has_gradebook() ) {
			return $stats;
		}

		$results_table = Tutor_Schema::gradebook_results_table();

		$course_where = '';
		if ( $course_id > 0 ) {
			$course_where = $wpdb->prepare( ' AND course_id = %d', $course_id );
		}

		// Does the data contain any 'final' rows? If so, base metrics on those.
		$has_final = (bool) $wpdb->get_var(
			"SELECT 1 FROM {$results_table} WHERE result_for = 'final'{$course_where} LIMIT 1"
		);
		$scope_where = $has_final ? " AND result_for = 'final'" : '';

		$results_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$results_table} WHERE 1 = 1{$course_where}{$scope_where}"
		);
		if ( $results_count <= 0 ) {
			// Tables exist but no usable rows — treat as unavailable data.
			return $stats;
		}

		$stats['available']     = true;
		$stats['results_count'] = $results_count;

		$stats['avg_percent'] = round(
			(float) $wpdb->get_var(
				"SELECT AVG(earned_percent) FROM {$results_table} WHERE 1 = 1{$course_where}{$scope_where}"
			),
			1
		);

		$dist_rows = $wpdb->get_results(
			"SELECT grade_name, COUNT(*) AS cnt
			FROM {$results_table}
			WHERE 1 = 1{$course_where}{$scope_where}
			GROUP BY grade_name
			ORDER BY cnt DESC",
			ARRAY_A
		);
		foreach ( (array) $dist_rows as $row ) {
			$row  = (array) $row;
			$name = (string) ( $row['grade_name'] ?? '' );
			if ( '' === $name ) {
				$name = __( 'ไม่ระบุเกรด', 'tutorlms-analytics' );
			}
			$stats['grade_distribution'][ $name ] = (int) ( $row['cnt'] ?? 0 );
		}

		// Per-course averages (global view only).
		if ( 0 === $course_id ) {
			$course_rows = $wpdb->get_results(
				"SELECT course_id, AVG(earned_percent) AS avg_pct, COUNT(*) AS cnt
				FROM {$results_table}
				WHERE 1 = 1{$scope_where}
				GROUP BY course_id
				ORDER BY cnt DESC
				LIMIT 5",
				ARRAY_A
			);
			foreach ( (array) $course_rows as $row ) {
				$row = (array) $row;
				$cid = (int) ( $row['course_id'] ?? 0 );
				$stats['per_course'][] = array(
					'id'          => $cid,
					'title'       => get_the_title( $cid ),
					'avg_percent' => round( (float) ( $row['avg_pct'] ?? 0 ), 1 ),
					'results'     => (int) ( $row['cnt'] ?? 0 ),
				);
			}
		}

		return $stats;
	}
}
