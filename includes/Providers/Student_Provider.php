<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Student_Provider {

	/**
	 * Get table data for student progress.
	 */
	public function get_student_table( int $course_id = 0, int $limit = 50 ): array {
		global $wpdb;

		// Get latest enrolled users
		$where = "c.comment_type = 'tutor_enrolled' AND c.comment_approved = 'approved'";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND c.comment_post_ID = %d", $course_id );
		}

		// We use a subquery or group by to get unique users and their latest enrollment date
		$query = "
			SELECT 
				c.user_id, 
				MAX(c.comment_date) as last_enrolled,
				COUNT(DISTINCT c.comment_post_ID) as courses_taken
			FROM {$wpdb->comments} c
			WHERE {$where}
			GROUP BY c.user_id
			ORDER BY last_enrolled DESC
			LIMIT %d
		";
		$users = $wpdb->get_results( $wpdb->prepare( $query, $limit ), ARRAY_A );

		if ( empty( $users ) ) {
			return array();
		}

		$table_data = array();

		foreach ( $users as $u ) {
			$uid = (int) $u['user_id'];
			$user_info = get_userdata( $uid );
			if ( ! $user_info ) continue;

			// Get progress from usermeta
			$progress_meta = get_user_meta( $uid, '_tutor_course_progress', true );
			$avg_progress = 0;
			
			if ( is_array( $progress_meta ) ) {
				if ( $course_id > 0 && isset( $progress_meta[ $course_id ] ) ) {
					$d = $progress_meta[ $course_id ];
					if ( isset( $d['completed_lesson'], $d['total_lesson'] ) && $d['total_lesson'] > 0 ) {
						$avg_progress = ( $d['completed_lesson'] / $d['total_lesson'] ) * 100;
					}
				} else {
					$total_pct = 0;
					$count = 0;
					foreach ( $progress_meta as $cid => $d ) {
						if ( isset( $d['completed_lesson'], $d['total_lesson'] ) && $d['total_lesson'] > 0 ) {
							$total_pct += ( $d['completed_lesson'] / $d['total_lesson'] ) * 100;
							$count++;
						}
					}
					$avg_progress = $count > 0 ? $total_pct / $count : 0;
				}
			}

			// Custom tracking table to get true last activity, fallback to enrollment date
			$table_name = $wpdb->prefix . 'tutorlms_analytics_events';
			$last_activity = $u['last_enrolled'];
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
			if ( $table_exists ) {
				$latest_event = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(created_at) FROM {$table_name} WHERE user_id = %d", $uid ) );
				if ( $latest_event ) {
					$last_activity = $latest_event;
				}
			}

			// Determine status: Active if activity within 14 days
			$days_since_activity = ( time() - strtotime( $last_activity ) ) / ( 60 * 60 * 24 );
			$status = $days_since_activity <= 14 ? 'Active' : 'Inactive';

			$table_data[] = array(
				'user_id'       => $uid,
				'display_name'  => $user_info->display_name,
				'email'         => $user_info->user_email,
				'courses_taken' => $u['courses_taken'],
				'avg_progress'  => round( (float) $avg_progress, 1 ),
				'last_activity' => $last_activity,
				'status'        => $status,
			);
		}

		return $table_data;
	}
}
