<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Survival_Provider {

	/**
	 * Calculate Kaplan-Meier survival curves for a given course.
	 * Survival = probability of a student completing lesson N given they completed lesson N-1.
	 */
	public function get_survival_curve( int $course_id ): array {
		global $wpdb;

		// 1. Get all published lessons for this course in order
		$lessons = $wpdb->get_results( $wpdb->prepare( "
			SELECT ID, post_title 
			FROM {$wpdb->posts} 
			WHERE post_type = 'lesson' AND post_parent = %d AND post_status = 'publish'
			ORDER BY menu_order ASC
		", $course_id ) );

		if ( empty( $lessons ) ) {
			return array();
		}

		// 2. Count total enrolled students for this course
		$total_enrolled = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(DISTINCT post_author) 
			FROM {$wpdb->posts} 
			WHERE post_parent = %d AND post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')
		", $course_id ) );

		if ( $total_enrolled === 0 ) {
			return array();
		}

		$curve = array(
			'labels' => array( 'Enrollment' ),
			'data'   => array( 100.0 ), // 100% survival at start
		);

		$current_survivors = $total_enrolled;

		foreach ( $lessons as $lesson ) {
			// Count how many users completed this specific lesson
			// Tutor LMS stores this as _tutor_completed_lesson_id_{$lesson_id}
			$meta_key = '_tutor_completed_lesson_id_' . $lesson->ID;
			$completed_count = (int) $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT(DISTINCT user_id) 
				FROM {$wpdb->usermeta} 
				WHERE meta_key = %s
			", $meta_key ) );

			// Survival prob = (Completed / Initial Enrolled) * 100
			// (Simplified Kaplan-Meier without right-censoring for this specific use-case)
			$prob = ( $completed_count / $total_enrolled ) * 100;
			
			// Can't have higher survival than previous step in standard funnel
			$prob = min( $prob, end( $curve['data'] ) );

			$curve['labels'][] = $lesson->post_title;
			$curve['data'][]   = round( $prob, 2 );
		}

		return $curve;
	}
}
