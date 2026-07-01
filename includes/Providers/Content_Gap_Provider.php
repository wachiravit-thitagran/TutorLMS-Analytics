<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Content_Gap_Provider {

	/**
	 * Get all content gap analysis data for a course.
	 */
	public function get_content_gaps( int $course_id ): array {
		if ( $course_id <= 0 ) return [];

		return array(
			'highest_dropoff_lessons' => $this->get_highest_dropoff_lessons( $course_id ),
			'hardest_quizzes'         => $this->get_hardest_quizzes( $course_id ),
			'lesson_quiz_correlation' => $this->get_lesson_quiz_correlation( $course_id ),
			'exit_lessons'            => $this->get_exit_lessons( $course_id ),
			'difficulty_index'        => $this->get_content_difficulty_index( $course_id ),
			'engagement_index'        => $this->get_content_engagement_index( $course_id ),
		);
	}

	/**
	 * Find lessons with the highest drop-off rate (top 3).
	 * Compares completion count of each lesson vs the previous lesson.
	 */
	public function get_highest_dropoff_lessons( int $course_id ): array {
		global $wpdb;

		// Get total enrolled
		$total_enrolled = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(DISTINCT post_author)
			FROM {$wpdb->posts}
			WHERE post_parent = %d AND post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')
		", $course_id ) );

		if ( $total_enrolled === 0 ) return [];

		// Get all lessons in order (through topics)
		$topics = $wpdb->get_results( $wpdb->prepare( "
			SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'topics' AND post_parent = %d AND post_status = 'publish'
			ORDER BY menu_order ASC, ID ASC
		", $course_id ), ARRAY_A );

		if ( empty( $topics ) ) return [];

		$topic_ids = array_column( $topics, 'ID' );
		$in_topics = implode( ',', array_map( 'intval', $topic_ids ) );

		$lessons = $wpdb->get_results( "
			SELECT ID, post_title
			FROM {$wpdb->posts}
			WHERE post_type = 'lesson' AND post_parent IN ({$in_topics}) AND post_status = 'publish'
			ORDER BY menu_order ASC, ID ASC
		", ARRAY_A );

		if ( empty( $lessons ) ) return [];

		// Count completions per lesson
		$lesson_completions = array();
		foreach ( $lessons as $lesson ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT(DISTINCT user_id)
				FROM {$wpdb->comments}
				WHERE comment_type = 'lesson_completed'
				  AND comment_post_ID = %d
				  AND comment_approved IN ('approved', '1')
			", (int) $lesson['ID'] ) );

			$lesson_completions[] = array(
				'id'         => (int) $lesson['ID'],
				'title'      => $lesson['post_title'],
				'completed'  => $count,
			);
		}

		// Calculate drop-off between consecutive lessons
		$dropoffs = array();
		$prev_count = $total_enrolled;

		foreach ( $lesson_completions as $i => $lesson ) {
			$current_count = $lesson['completed'];
			$drop = $prev_count - $current_count;
			$drop_pct = $prev_count > 0 ? round( ($drop / $prev_count) * 100, 1 ) : 0;

			if ( $drop > 0 ) {
				$dropoffs[] = array(
					'lesson_id'   => $lesson['id'],
					'title'       => $lesson['title'],
					'position'    => $i + 1,
					'prev_count'  => $prev_count,
					'completed'   => $current_count,
					'drop_count'  => $drop,
					'drop_pct'    => $drop_pct,
				);
			}

			$prev_count = $current_count;
		}

		// Sort by drop_pct descending and return top 3
		usort( $dropoffs, function( $a, $b ) {
			return $b['drop_pct'] <=> $a['drop_pct'];
		});

		return array_slice( $dropoffs, 0, 3 );
	}

	/**
	 * Find quizzes with lowest pass rate (top 3 hardest).
	 */
	public function get_hardest_quizzes( int $course_id ): array {
		global $wpdb;

		$quiz_table = $wpdb->prefix . 'tutor_quiz_attempts';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$quiz_table}'") === $quiz_table;
		if ( ! $table_exists ) return [];

		// Get quiz IDs that belong to this course (via topics)
		$topics = $wpdb->get_results( $wpdb->prepare( "
			SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'topics' AND post_parent = %d AND post_status = 'publish'
		", $course_id ), ARRAY_A );

		if ( empty( $topics ) ) return [];

		$topic_ids = array_column( $topics, 'ID' );
		$in_topics = implode( ',', array_map( 'intval', $topic_ids ) );

		$quizzes = $wpdb->get_results( "
			SELECT ID, post_title
			FROM {$wpdb->posts}
			WHERE post_type = 'tutor_quiz' AND post_parent IN ({$in_topics}) AND post_status = 'publish'
		", ARRAY_A );

		if ( empty( $quizzes ) ) return [];

		$results = array();
		foreach ( $quizzes as $quiz ) {
			$quiz_id = (int) $quiz['ID'];

			// Get passing grade
			$passing_grade = (float) get_post_meta( $quiz_id, '_tutor_quiz_passing_grade', true );
			if ( $passing_grade <= 0 ) $passing_grade = 80;

			$stats = $wpdb->get_row( $wpdb->prepare( "
				SELECT
					COUNT(*) as total_attempts,
					SUM(CASE WHEN (earned_marks / total_marks * 100) >= %f THEN 1 ELSE 0 END) as passed,
					AVG(earned_marks / total_marks * 100) as avg_score
				FROM {$quiz_table}
				WHERE quiz_id = %d AND total_marks > 0
			", $passing_grade, $quiz_id ) );

			if ( ! $stats || $stats->total_attempts == 0 ) continue;

			$pass_rate = round( ($stats->passed / $stats->total_attempts) * 100, 1 );

			$results[] = array(
				'quiz_id'        => $quiz_id,
				'title'          => $quiz['post_title'],
				'total_attempts' => (int) $stats->total_attempts,
				'pass_rate'      => $pass_rate,
				'avg_score'      => round( (float) $stats->avg_score, 1 ),
				'passing_grade'  => $passing_grade,
			);
		}

		// Sort by pass_rate ascending (hardest first)
		usort( $results, function( $a, $b ) {
			return $a['pass_rate'] <=> $b['pass_rate'];
		});

		return array_slice( $results, 0, 3 );
	}

	/**
	 * Correlation between lesson completion and subsequent quiz performance.
	 * For each topic, compares lesson completion % with quiz average score.
	 */
	public function get_exit_lessons( int $course_id, int $limit = 10 ): array {
		global $wpdb;

		$events_table = $wpdb->prefix . 'tutorlms_analytics_events';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$events_table}'") !== $events_table ) {
			return [];
		}

		$total_learners = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(DISTINCT user_id)
			FROM {$events_table}
			WHERE course_id = %d AND user_id > 0
		", $course_id ) );

		if ( $total_learners <= 0 ) {
			return [];
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "
			SELECT last_events.lesson_id, COUNT(*) as exit_count
			FROM (
				SELECT e.user_id, e.lesson_id, e.created_at
				FROM {$events_table} e
				INNER JOIN (
					SELECT user_id, MAX(created_at) as last_seen
					FROM {$events_table}
					WHERE course_id = %d AND user_id > 0 AND lesson_id > 0
					GROUP BY user_id
				) latest ON latest.user_id = e.user_id AND latest.last_seen = e.created_at
				WHERE e.course_id = %d AND e.lesson_id > 0
			) last_events
			GROUP BY last_events.lesson_id
			ORDER BY exit_count DESC
			LIMIT {$limit}
		", $course_id, $course_id ), ARRAY_A );

		$data = [];
		foreach ( (array) $rows as $row ) {
			$title = get_the_title( (int) $row['lesson_id'] );
			if ( ! $title ) {
				continue;
			}
			$exit_count = (int) $row['exit_count'];
			$data[] = array(
				'lesson_id'  => (int) $row['lesson_id'],
				'title'      => $title,
				'exit_count' => $exit_count,
				'exit_rate'  => round( ( $exit_count / $total_learners ) * 100, 1 ),
			);
		}

		return $data;
	}

	public function get_content_difficulty_index( int $course_id, int $limit = 10 ): array {
		$dropoffs = $this->get_highest_dropoff_lessons( $course_id );
		$hardest_quizzes = $this->get_hardest_quizzes( $course_id );
		$data = [];

		foreach ( $dropoffs as $dropoff ) {
			$score = $this->clamp_score( (float) $dropoff['drop_pct'] * 2 );
			$data[] = array(
				'content_id' => (int) $dropoff['lesson_id'],
				'title'      => $dropoff['title'],
				'type'       => 'lesson',
				'score'      => $score,
				'signals'    => array(
					'dropoff_pct'      => (float) $dropoff['drop_pct'],
					'avg_time_minutes' => 0,
					'quiz_avg_score'   => 0,
				),
			);
		}

		foreach ( $hardest_quizzes as $quiz ) {
			$score = $this->clamp_score( 100 - (float) $quiz['pass_rate'] );
			$data[] = array(
				'content_id' => (int) $quiz['quiz_id'],
				'title'      => $quiz['title'],
				'type'       => 'quiz',
				'score'      => $score,
				'signals'    => array(
					'dropoff_pct'      => 0,
					'avg_time_minutes' => 0,
					'quiz_avg_score'   => (float) $quiz['avg_score'],
				),
			);
		}

		usort( $data, function( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		return array_slice( $data, 0, $limit );
	}

	public function get_content_engagement_index( int $course_id, int $limit = 10 ): array {
		global $wpdb;

		$total_enrolled = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(DISTINCT post_author)
			FROM {$wpdb->posts}
			WHERE post_parent = %d AND post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')
		", $course_id ) );

		if ( $total_enrolled <= 0 ) {
			return [];
		}

		$topics = $wpdb->get_results( $wpdb->prepare( "
			SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'topics' AND post_parent = %d AND post_status = 'publish'
		", $course_id ), ARRAY_A );

		if ( empty( $topics ) ) return [];
		$topic_ids = array_column( $topics, 'ID' );
		$in_topics = implode( ',', array_map( 'intval', $topic_ids ) );

		$lessons = $wpdb->get_results( "
			SELECT ID, post_title
			FROM {$wpdb->posts}
			WHERE post_type = 'lesson' AND post_parent IN ({$in_topics}) AND post_status = 'publish'
			ORDER BY menu_order ASC, ID ASC
			LIMIT {$limit}
		", ARRAY_A );

		$data = [];
		foreach ( (array) $lessons as $lesson ) {
			$completed = (int) $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT(DISTINCT user_id)
				FROM {$wpdb->comments}
				WHERE comment_type = 'lesson_completed' AND comment_post_ID = %d AND comment_approved IN ('approved', '1')
			", (int) $lesson['ID'] ) );
			$completion_pct = round( ( $completed / $total_enrolled ) * 100, 1 );
			$data[] = array(
				'content_id' => (int) $lesson['ID'],
				'title'      => $lesson['post_title'],
				'type'       => 'lesson',
				'score'      => $this->clamp_score( $completion_pct ),
				'signals'    => array(
					'completion_pct'   => $completion_pct,
					'revisit_rate'     => 0,
					'continuation_pct' => $completion_pct,
				),
			);
		}

		usort( $data, function( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		return $data;
	}

	private function clamp_score( float $score ): int {
		return (int) max( 0, min( 100, round( $score ) ) );
	}

	public function get_lesson_quiz_correlation( int $course_id ): array {
		global $wpdb;

		$quiz_table = $wpdb->prefix . 'tutor_quiz_attempts';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$quiz_table}'") === $quiz_table;
		if ( ! $table_exists ) return [];

		$total_enrolled = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(DISTINCT post_author)
			FROM {$wpdb->posts}
			WHERE post_parent = %d AND post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')
		", $course_id ) );

		if ( $total_enrolled === 0 ) return [];

		$topics = $wpdb->get_results( $wpdb->prepare( "
			SELECT ID, post_title FROM {$wpdb->posts}
			WHERE post_type = 'topics' AND post_parent = %d AND post_status = 'publish'
			ORDER BY menu_order ASC, ID ASC
		", $course_id ), ARRAY_A );

		$correlation = array();

		foreach ( (array) $topics as $topic ) {
			$topic_id = (int) $topic['ID'];

			// Get lesson completion % in this topic
			$lesson_count = (int) $wpdb->get_var( $wpdb->prepare( "
				SELECT COUNT(DISTINCT user_id)
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
				WHERE p.post_parent = %d AND p.post_type = 'lesson' AND c.comment_type = 'lesson_completed' AND c.comment_approved IN ('approved', '1')
			", $topic_id ) );

			$lesson_completion_pct = $total_enrolled > 0 ? round( ($lesson_count / $total_enrolled) * 100, 1 ) : 0;

			// Get quiz avg score in this topic
			$quiz_ids = $wpdb->get_col( $wpdb->prepare( "
				SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'tutor_quiz' AND post_parent = %d AND post_status = 'publish'
			", $topic_id ) );

			if ( empty( $quiz_ids ) ) continue;

			$in_quizzes = implode( ',', array_map( 'intval', $quiz_ids ) );
			$avg_score = (float) $wpdb->get_var( "
				SELECT AVG(earned_marks / total_marks * 100)
				FROM {$quiz_table}
				WHERE quiz_id IN ({$in_quizzes}) AND total_marks > 0
			" );

			$correlation[] = array(
				'topic_title'          => $topic['post_title'],
				'lesson_completion_pct' => $lesson_completion_pct,
				'quiz_avg_score'       => round( $avg_score, 1 ),
			);
		}

		return $correlation;
	}
}
