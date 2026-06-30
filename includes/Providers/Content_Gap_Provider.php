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
				  AND comment_approved = 'approved'
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
				WHERE p.post_parent = %d AND p.post_type = 'lesson' AND c.comment_type = 'lesson_completed' AND c.comment_approved = 'approved'
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
