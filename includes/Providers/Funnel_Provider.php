<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Funnel_Provider {

	public function get_progress_distribution( int $course_id = 0 ): array {
		global $wpdb;
		
		// Get progress from usermeta where meta_key = _tutor_course_progress
		$query = "
			SELECT meta_value as progress 
			FROM {$wpdb->usermeta} 
			WHERE meta_key = '_tutor_course_progress'
		";
		$results = $wpdb->get_results( $query, ARRAY_A );

		$bins = array(
			'0-20%'  => 0,
			'21-50%' => 0,
			'51-99%' => 0,
			'100%'   => 0,
		);

		foreach ( (array) $results as $row ) {
			$progress_data = maybe_unserialize( $row['progress'] );
			if ( is_array( $progress_data ) ) {
				foreach ( $progress_data as $cid => $data ) {
					// If filtering by course, skip others
					if ( $course_id > 0 && $cid != $course_id ) {
						continue;
					}

					$pct = 0;
					if ( isset( $data['completed_lesson'], $data['total_lesson'] ) && $data['total_lesson'] > 0 ) {
						$pct = ( $data['completed_lesson'] / $data['total_lesson'] ) * 100;
					} elseif ( is_numeric( $data ) ) {
						$pct = (float) $data;
					}

					if ( $pct <= 20 ) {
						$bins['0-20%']++;
					} elseif ( $pct <= 50 ) {
						$bins['21-50%']++;
					} elseif ( $pct < 100 ) {
						$bins['51-99%']++;
					} else {
						$bins['100%']++;
					}
				}
			} elseif ( is_numeric( $row['progress'] ) ) {
				// If global numeric progress and filtering by course is required, we can't be sure it's for this course.
				// Tutor LMS usually uses the array format for _tutor_course_progress.
				if ( $course_id === 0 ) {
					$pct = (float) $row['progress'];
					if ( $pct <= 20 ) $bins['0-20%']++;
					elseif ( $pct <= 50 ) $bins['21-50%']++;
					elseif ( $pct < 100 ) $bins['51-99%']++;
					else $bins['100%']++;
				}
			}
		}

		return $bins;
	}
}
