<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Engagement_Provider {

	/**
	 * Get all engagement data for a course.
	 */
	public function get_engagement_data( int $course_id = 0 ): array {
		$at_risk = $this->get_at_risk_students( $course_id );

		return array(
			'scores'                     => $this->get_engagement_scores( $course_id ),
			'at_risk_students'           => $at_risk,
			'at_risk_count'              => count( $at_risk ),
			'engagement_trends'          => $this->get_engagement_trends( $course_id ),
			'high_intent_stuck'          => $this->get_high_intent_stuck_students( $course_id ),
			'power_learners'             => $this->get_power_learners( $course_id ),
			'low_engagement_high_score'  => $this->get_low_engagement_high_score_students( $course_id ),
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

	public function get_engagement_trends( int $course_id = 0, int $limit = 20 ): array {
		global $wpdb;

		$events_table = $wpdb->prefix . 'tutorlms_analytics_events';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$events_table}'") !== $events_table ) {
			return [];
		}

		$where = "ev.user_id > 0 AND p.post_type = 'tutor_enrolled' AND p.post_status IN ('completed', 'processing', 'publish')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( ' AND ev.course_id = %d AND p.post_parent = %d', $course_id, $course_id );
		}

		$rows = $wpdb->get_results( "
			SELECT
				ev.user_id,
				SUM(CASE WHEN ev.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as events_7d,
				SUM(CASE WHEN ev.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND ev.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as events_prev_7d,
				MAX(ev.created_at) as last_activity
			FROM {$events_table} ev
			INNER JOIN {$wpdb->posts} p ON p.post_author = ev.user_id
			WHERE {$where}
			  AND ev.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
			GROUP BY ev.user_id
			ORDER BY events_7d DESC
			LIMIT {$limit}
		", ARRAY_A );

		$data = [];
		foreach ( (array) $rows as $row ) {
			$user_id = (int) $row['user_id'];
			$user = get_userdata( $user_id );
			$current = (int) $row['events_7d'];
			$previous = (int) $row['events_prev_7d'];
			$change_pct = $previous > 0 ? round( ( ( $current - $previous ) / $previous ) * 100, 1 ) : ( $current > 0 ? 100.0 : 0.0 );
			$data[] = array(
				'user_id'        => $user_id,
				'display_name'   => $user ? $user->display_name : 'User ' . $user_id,
				'events_7d'      => $current,
				'events_prev_7d' => $previous,
				'trend'          => $current > $previous ? 'up' : ( $current < $previous ? 'down' : 'flat' ),
				'change_pct'     => $change_pct,
				'last_activity'  => (string) $row['last_activity'],
			);
		}

		return $data;
	}

	public function get_high_intent_stuck_students( int $course_id = 0, int $limit = 20 ): array {
		global $wpdb;

		$events_table = $wpdb->prefix . 'tutorlms_analytics_events';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$events_table}'") !== $events_table ) {
			return [];
		}

		$where = "ev.user_id > 0 AND ev.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND p.post_type = 'tutor_enrolled' AND p.post_status IN ('completed', 'processing', 'publish')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( ' AND ev.course_id = %d AND p.post_parent = %d', $course_id, $course_id );
		}

		$rows = $wpdb->get_results( "
			SELECT ev.user_id, COUNT(ev.id) as events_14d, MAX(ev.created_at) as last_activity
			FROM {$events_table} ev
			INNER JOIN {$wpdb->posts} p ON p.post_author = ev.user_id
			WHERE {$where}
			GROUP BY ev.user_id
			HAVING events_14d >= 10
			ORDER BY events_14d DESC
			LIMIT {$limit}
		", ARRAY_A );

		$data = [];
		foreach ( (array) $rows as $row ) {
			$user_id = (int) $row['user_id'];
			$progress_pct = $this->get_progress_pct_for_user( $user_id, $course_id );
			if ( $progress_pct >= 50 ) {
				continue;
			}
			$user = get_userdata( $user_id );
			$data[] = array(
				'user_id'       => $user_id,
				'display_name'  => $user ? $user->display_name : 'User ' . $user_id,
				'events_14d'    => (int) $row['events_14d'],
				'progress_pct'  => round( $progress_pct, 1 ),
				'last_activity' => (string) $row['last_activity'],
			);
		}

		return $data;
	}

	public function get_power_learners( int $course_id = 0, int $limit = 20 ): array {
		global $wpdb;

		$quiz_table = $wpdb->prefix . 'tutor_quiz_attempts';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$quiz_table}'") !== $quiz_table ) {
			return [];
		}

		$where_enroll = "e.post_type = 'tutor_enrolled' AND e.post_status IN ('completed', 'processing', 'publish')";
		$where_complete = "c.comment_type = 'course_completed' AND c.comment_approved IN ('approved', '1')";
		$quiz_join = '';
		if ( $course_id > 0 ) {
			$where_enroll .= $wpdb->prepare( ' AND e.post_parent = %d', $course_id );
			$where_complete .= $wpdb->prepare( ' AND c.comment_post_ID = %d', $course_id );
			$quiz_join = $wpdb->prepare( " AND q.quiz_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_parent IN (SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'topics'))", $course_id );
		}

		$rows = $wpdb->get_results( "
			SELECT
				e.post_author as user_id,
				DATEDIFF(c.comment_date, e.post_date) as days_to_complete,
				AVG(q.earned_marks / q.total_marks * 100) as quiz_avg_score
			FROM {$wpdb->posts} e
			INNER JOIN {$wpdb->comments} c ON c.user_id = e.post_author AND c.comment_post_ID = e.post_parent
			INNER JOIN {$quiz_table} q ON q.user_id = e.post_author AND q.total_marks > 0 {$quiz_join}
			WHERE {$where_enroll} AND {$where_complete}
			GROUP BY e.post_author, c.comment_date, e.post_date
			HAVING days_to_complete <= 7 AND quiz_avg_score >= 85
			ORDER BY quiz_avg_score DESC, days_to_complete ASC
			LIMIT {$limit}
		", ARRAY_A );

		$data = [];
		foreach ( (array) $rows as $row ) {
			$user_id = (int) $row['user_id'];
			$user = get_userdata( $user_id );
			$data[] = array(
				'user_id'          => $user_id,
				'display_name'     => $user ? $user->display_name : 'User ' . $user_id,
				'progress_pct'     => 100.0,
				'quiz_avg_score'   => round( (float) $row['quiz_avg_score'], 1 ),
				'days_to_complete' => (int) $row['days_to_complete'],
			);
		}

		return $data;
	}

	public function get_low_engagement_high_score_students( int $course_id = 0 ): array {
		global $wpdb;

		$quiz_table = $wpdb->prefix . 'tutor_quiz_attempts';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$quiz_table}'") !== $quiz_table ) {
			return [];
		}

		$scores = $this->get_engagement_scores( $course_id, 100 );
		$data = [];
		foreach ( $scores as $score ) {
			if ( $score['score'] >= 40 ) {
				continue;
			}
			$quiz_where = $wpdb->prepare( 'user_id = %d AND total_marks > 0', (int) $score['user_id'] );
			if ( $course_id > 0 ) {
				$quiz_where .= $wpdb->prepare( " AND quiz_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_parent IN (SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'topics'))", $course_id );
			}
			$avg = (float) $wpdb->get_var( "SELECT AVG(earned_marks / total_marks * 100) FROM {$quiz_table} WHERE {$quiz_where}" );
			if ( $avg >= 85 ) {
				$data[] = array(
					'user_id'        => (int) $score['user_id'],
					'display_name'   => $score['display_name'],
					'score'          => (int) $score['score'],
					'quiz_avg_score' => round( $avg, 1 ),
				);
			}
		}

		return $data;
	}

	private function get_progress_pct_for_user( int $user_id, int $course_id = 0 ): float {
		$progress_meta = get_user_meta( $user_id, '_tutor_course_progress', true );
		if ( ! is_array( $progress_meta ) ) {
			return 0.0;
		}

		if ( $course_id > 0 && isset( $progress_meta[ $course_id ] ) ) {
			$progress = $progress_meta[ $course_id ];
			return isset( $progress['completed_lesson'], $progress['total_lesson'] ) && $progress['total_lesson'] > 0
				? (float) ( $progress['completed_lesson'] / $progress['total_lesson'] * 100 )
				: 0.0;
		}

		$total = 0;
		$count = 0;
		foreach ( $progress_meta as $progress ) {
			if ( isset( $progress['completed_lesson'], $progress['total_lesson'] ) && $progress['total_lesson'] > 0 ) {
				$total += $progress['completed_lesson'] / $progress['total_lesson'] * 100;
				$count++;
			}
		}

		return $count > 0 ? (float) ( $total / $count ) : 0.0;
	}
}
