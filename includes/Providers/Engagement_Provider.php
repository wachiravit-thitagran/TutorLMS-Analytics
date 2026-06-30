<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Engagement_Provider {

	/**
	 * Get all engagement data for a course.
	 */
	public function get_engagement_data( int $course_id = 0 ): array {
		return array(
			'scores'          => $this->get_engagement_scores( $course_id ),
			'at_risk_students' => $this->get_at_risk_students( $course_id ),
			'at_risk_count'    => count( $this->get_at_risk_students( $course_id ) ),
		);
	}

	/**
	 * Calculate engagement score per student (0-100).
	 * 
	 * Score breakdown:
	 * - Frequency: events in last 14 days (max 25)
	 * - Progress: course progress % (max 25)
	 * - Quiz score: average quiz score (max 25)
	 * - Consistency: unique active days in last 14 days (max 25)
	 */
	public function get_engagement_scores( int $course_id = 0, int $limit = 50 ): array {
		global $wpdb;

		// Get enrolled students
		$where = "p.post_type = 'tutor_enrolled' AND p.post_status IN ('completed', 'processing', 'publish')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND p.post_parent = %d", $course_id );
		}

		$users = $wpdb->get_results( "
			SELECT DISTINCT p.post_author as user_id
			FROM {$wpdb->posts} p
			WHERE {$where}
			ORDER BY p.post_date DESC
			LIMIT {$limit}
		", ARRAY_A );

		if ( empty( $users ) ) return [];

		$events_table = $wpdb->prefix . 'tutorlms_analytics_events';
		$has_events_table = $wpdb->get_var("SHOW TABLES LIKE '{$events_table}'") === $events_table;

		$quiz_table = $wpdb->prefix . 'tutor_quiz_attempts';
		$has_quiz_table = $wpdb->get_var("SHOW TABLES LIKE '{$quiz_table}'") === $quiz_table;

		$scores = array();

		foreach ( $users as $u ) {
			$uid = (int) $u['user_id'];
			$user_info = get_userdata( $uid );
			if ( ! $user_info ) continue;

			// 1. Frequency score (events in last 14 days, max 25)
			$frequency_score = 0;
			if ( $has_events_table ) {
				$freq_where = $wpdb->prepare( "user_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)", $uid );
				if ( $course_id > 0 ) {
					$freq_where .= $wpdb->prepare( " AND course_id = %d", $course_id );
				}
				$event_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$events_table} WHERE {$freq_where}" );
				// Normalize: 20+ events = max score
				$frequency_score = min( 25, round( ($event_count / 20) * 25 ) );
			}

			// 2. Progress score (max 25)
			$progress_score = 0;
			$progress_pct = 0;
			$progress_meta = get_user_meta( $uid, '_tutor_course_progress', true );
			if ( is_array( $progress_meta ) ) {
				if ( $course_id > 0 && isset( $progress_meta[ $course_id ] ) ) {
					$d = $progress_meta[ $course_id ];
					if ( isset( $d['completed_lesson'], $d['total_lesson'] ) && $d['total_lesson'] > 0 ) {
						$progress_pct = ( $d['completed_lesson'] / $d['total_lesson'] ) * 100;
					}
				} else {
					$total_pct = 0;
					$count = 0;
					foreach ( $progress_meta as $d ) {
						if ( isset( $d['completed_lesson'], $d['total_lesson'] ) && $d['total_lesson'] > 0 ) {
							$total_pct += ( $d['completed_lesson'] / $d['total_lesson'] ) * 100;
							$count++;
						}
					}
					$progress_pct = $count > 0 ? $total_pct / $count : 0;
				}
				$progress_score = round( ($progress_pct / 100) * 25 );
			}

			// 3. Quiz score (max 25)
			$quiz_score_val = 0;
			if ( $has_quiz_table ) {
				$q_where = $wpdb->prepare( "q.user_id = %d AND q.total_marks > 0", $uid );
				if ( $course_id > 0 ) {
					$q_where .= $wpdb->prepare(
						" AND q.quiz_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_parent IN (SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'topics'))",
						$course_id
					);
				}
				$avg_quiz = (float) $wpdb->get_var( "SELECT AVG(q.earned_marks / q.total_marks * 100) FROM {$quiz_table} q WHERE {$q_where}" );
				$quiz_score_val = round( ($avg_quiz / 100) * 25 );
			}

			// 4. Consistency score (unique active days in last 14 days, max 25)
			$consistency_score = 0;
			if ( $has_events_table ) {
				$cons_where = $wpdb->prepare( "user_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)", $uid );
				if ( $course_id > 0 ) {
					$cons_where .= $wpdb->prepare( " AND course_id = %d", $course_id );
				}
				$active_days = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT DATE(created_at)) FROM {$events_table} WHERE {$cons_where}" );
				// Normalize: 7+ unique days = max score
				$consistency_score = min( 25, round( ($active_days / 7) * 25 ) );
			}

			$total_score = (int) min( 100, $frequency_score + $progress_score + $quiz_score_val + $consistency_score );

			$scores[] = array(
				'user_id'       => $uid,
				'display_name'  => $user_info->display_name,
				'score'         => $total_score,
				'progress_pct'  => round( $progress_pct, 1 ),
				'breakdown'     => array(
					'frequency'   => (int) $frequency_score,
					'progress'    => (int) $progress_score,
					'quiz'        => (int) $quiz_score_val,
					'consistency' => (int) $consistency_score,
				),
			);
		}

		// Sort by score ascending (lowest engagement first)
		usort( $scores, function( $a, $b ) {
			return $a['score'] <=> $b['score'];
		});

		return $scores;
	}

	/**
	 * Get at-risk students: engagement score < 30 AND progress < 50%.
	 */
	public function get_at_risk_students( int $course_id = 0 ): array {
		$all_scores = $this->get_engagement_scores( $course_id, 100 );

		return array_values( array_filter( $all_scores, function( $s ) {
			return $s['score'] < 30 && $s['progress_pct'] < 50;
		} ) );
	}
}
