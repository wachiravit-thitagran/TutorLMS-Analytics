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
		$where = "p.post_type = 'tutor_enrolled' AND p.post_status IN ('completed', 'processing', 'publish')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND p.post_parent = %d", $course_id );
		}

		// We use a subquery or group by to get unique users and their latest enrollment date
		$query = "
			SELECT 
				p.post_author as user_id, 
				MAX(p.post_date) as last_enrolled,
				COUNT(DISTINCT p.post_parent) as courses_taken
			FROM {$wpdb->posts} p
			WHERE {$where}
			GROUP BY p.post_author
			ORDER BY last_enrolled DESC
			LIMIT %d
		";
		$users = $wpdb->get_results( $wpdb->prepare( $query, $limit ), ARRAY_A );

		if ( empty( $users ) ) {
			return array();
		}

		// Pre-fetch quiz stats for these users
		$user_ids = array_column( $users, 'user_id' );
		$quiz_stats = array();
		if ( ! empty( $user_ids ) ) {
			$quiz_table = $wpdb->prefix . 'tutor_quiz_attempts';
			$quiz_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$quiz_table}'") === $quiz_table;
			if ( $quiz_table_exists ) {
				$in_users = implode( ',', array_map( 'intval', $user_ids ) );
				$q_where = "q.user_id IN ($in_users) AND q.total_marks > 0";
				$q_join = "";
				if ( $course_id > 0 ) {
					$q_where .= $wpdb->prepare( " AND p.post_parent = %d", $course_id );
					$q_join = "INNER JOIN {$wpdb->posts} p ON q.quiz_id = p.ID";
				}
				$q_query = "
					SELECT q.user_id, 
						   COUNT(*) as total_attempts, 
						   AVG(q.earned_marks / q.total_marks * 100) as avg_score
					FROM {$quiz_table} q
					{$q_join}
					WHERE {$q_where}
					GROUP BY q.user_id
				";
				$q_results = $wpdb->get_results( $q_query, ARRAY_A );
				if ( $q_results ) {
					foreach ( $q_results as $qr ) {
						$quiz_stats[ $qr['user_id'] ] = array(
							'attempts'  => (int) $qr['total_attempts'],
							'avg_score' => round( (float) $qr['avg_score'], 1 ),
						);
					}
				}
			}
		}

		$table_data = array();

		foreach ( $users as $u ) {
			$uid = (int) $u['user_id'];
			$user_info = get_userdata( $uid );
			if ( ! $user_info ) continue;

			// Get progress from usermeta
			$progress_meta = get_user_meta( $uid, '_tutor_course_progress', true );
			$avg_progress = 0;
			$completed_lesson = 0;
			$total_lesson = 0;
			
			if ( is_array( $progress_meta ) ) {
				if ( $course_id > 0 && isset( $progress_meta[ $course_id ] ) ) {
					$d = $progress_meta[ $course_id ];
					if ( isset( $d['completed_lesson'], $d['total_lesson'] ) ) {
						$completed_lesson = (int) $d['completed_lesson'];
						$total_lesson = (int) $d['total_lesson'];
						if ( $total_lesson > 0 ) {
							$avg_progress = ( $completed_lesson / $total_lesson ) * 100;
						}
					}
				} else {
					$total_pct = 0;
					$count = 0;
					foreach ( $progress_meta as $cid => $d ) {
						if ( isset( $d['completed_lesson'], $d['total_lesson'] ) && $d['total_lesson'] > 0 ) {
							$total_pct += ( $d['completed_lesson'] / $d['total_lesson'] ) * 100;
							$count++;
							$completed_lesson += (int) $d['completed_lesson'];
							$total_lesson += (int) $d['total_lesson'];
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

			$quiz_data = isset( $quiz_stats[ $uid ] ) ? $quiz_stats[ $uid ] : array( 'attempts' => 0, 'avg_score' => 0 );

			$table_data[] = array(
				'user_id'          => $uid,
				'display_name'     => $user_info->display_name,
				'email'            => $user_info->user_email,
				'courses_taken'    => $u['courses_taken'],
				'avg_progress'     => round( (float) $avg_progress, 1 ),
				'completed_lesson' => $completed_lesson,
				'total_lesson'     => $total_lesson,
				'quiz_attempts'    => $quiz_data['attempts'],
				'quiz_avg_score'   => $quiz_data['avg_score'],
				'last_activity'    => $last_activity,
				'status'           => $status,
			);
		}

		return $table_data;
	}
}
