<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Quiz_Provider {

	public function get_quiz_performance( int $course_id = 0 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutor_quiz_attempts';

		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		if ( ! $table_exists ) {
			return array(
				'avg_score' => 0,
				'pass_rate' => 0,
			);
		}

		$where = "q.total_marks > 0";
		$join  = "LEFT JOIN {$wpdb->postmeta} pm ON q.quiz_id = pm.post_id AND pm.meta_key = '_tutor_quiz_passing_grade'";

		if ( $course_id > 0 ) {
			$join  .= " INNER JOIN {$wpdb->posts} p ON q.quiz_id = p.ID";
			$where .= $wpdb->prepare( " AND p.post_parent = %d", $course_id );
		}

		$stats = $wpdb->get_row( "
			SELECT
				AVG(q.earned_marks / q.total_marks * 100) as avg_score,
				SUM(CASE WHEN (q.earned_marks / q.total_marks * 100) >= COALESCE(NULLIF(pm.meta_value, ''), 80) THEN 1 ELSE 0 END) as passed_count,
				COUNT(*) as total_attempts
			FROM {$table_name} q
			{$join}
			WHERE {$where}
		" );

		if ( ! $stats || $stats->total_attempts == 0 ) {
			return array(
				'avg_score' => 0,
				'pass_rate' => 0,
			);
		}

		return array(
			'avg_score' => round( (float) $stats->avg_score, 2 ),
			'pass_rate' => round( ( $stats->passed_count / $stats->total_attempts ) * 100, 2 ),
		);
	}

	public function get_quiz_score_distribution( int $course_id = 0 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutor_quiz_attempts';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		if ( ! $table_exists ) return [];

		$where = "q.total_marks > 0";
		$join  = "";
		if ( $course_id > 0 ) {
			$join  = "INNER JOIN {$wpdb->posts} p ON q.quiz_id = p.ID";
			$where .= $wpdb->prepare( " AND p.post_parent = %d", $course_id );
		}

		$query = "
			SELECT (q.earned_marks / q.total_marks * 100) as pct
			FROM {$table_name} q
			{$join}
			WHERE {$where}
		";
		$results = $wpdb->get_results( $query, ARRAY_A );

		$dist = array(
			'0-50%'  => 0,
			'51-70%' => 0,
			'71-80%' => 0,
			'81-100%' => 0,
		);

		foreach ( (array) $results as $row ) {
			$pct = (float) $row['pct'];
			if ( $pct <= 50 ) {
				$dist['0-50%']++;
			} elseif ( $pct <= 70 ) {
				$dist['51-70%']++;
			} elseif ( $pct <= 80 ) {
				$dist['71-80%']++;
			} else {
				$dist['81-100%']++;
			}
		}

		return $dist;
	}

	public function get_quiz_diagnostics( int $course_id = 0 ): array {
		return array(
			'question_difficulty' => $this->get_question_difficulty( $course_id ),
			'common_wrong_answers' => $this->get_common_wrong_answers( $course_id ),
			'attempts_before_pass' => $this->get_attempts_before_pass( $course_id ),
			'retry_behavior'       => $this->get_retry_behavior( $course_id ),
		);
	}

	public function get_question_difficulty( int $course_id = 0, int $limit = 10 ): array {
		global $wpdb;

		$attempts_table = $wpdb->prefix . 'tutor_quiz_attempts';
		$answers_table  = $wpdb->prefix . 'tutor_quiz_attempt_answers';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$attempts_table}'") !== $attempts_table || $wpdb->get_var("SHOW TABLES LIKE '{$answers_table}'") !== $answers_table ) {
			return [];
		}

		$join = "INNER JOIN {$attempts_table} qa ON qa.attempt_id = a.quiz_attempt_id";
		$where = '1=1';
		if ( $course_id > 0 ) {
			$join .= " INNER JOIN {$wpdb->posts} qpost ON qpost.ID = qa.quiz_id INNER JOIN {$wpdb->posts} topic ON topic.ID = qpost.post_parent";
			$where .= $wpdb->prepare( ' AND topic.post_parent = %d', $course_id );
		}

		$rows = $wpdb->get_results( "
			SELECT
				a.question_id,
				qa.quiz_id,
				COUNT(*) as attempts,
				SUM(CASE WHEN CAST(a.achieved_mark AS DECIMAL(10,2)) > 0 THEN 1 ELSE 0 END) as correct_count
			FROM {$answers_table} a
			{$join}
			WHERE {$where}
			GROUP BY a.question_id, qa.quiz_id
			HAVING COUNT(*) > 0
			ORDER BY (SUM(CASE WHEN CAST(a.achieved_mark AS DECIMAL(10,2)) > 0 THEN 1 ELSE 0 END) / COUNT(*)) ASC, COUNT(*) DESC
			LIMIT {$limit}
		", ARRAY_A );

		$data = [];
		foreach ( (array) $rows as $row ) {
			$attempts = (int) $row['attempts'];
			$correct = (int) $row['correct_count'];
			$data[] = array(
				'question_id'  => (int) $row['question_id'],
				'title'        => get_the_title( (int) $row['question_id'] ),
				'quiz_title'   => get_the_title( (int) $row['quiz_id'] ),
				'correct_rate' => $attempts > 0 ? round( ( $correct / $attempts ) * 100, 1 ) : 0.0,
				'attempts'     => $attempts,
			);
		}

		return $data;
	}

	public function get_common_wrong_answers( int $course_id = 0, int $limit = 10 ): array {
		global $wpdb;

		$attempts_table = $wpdb->prefix . 'tutor_quiz_attempts';
		$answers_table  = $wpdb->prefix . 'tutor_quiz_attempt_answers';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$attempts_table}'") !== $attempts_table || $wpdb->get_var("SHOW TABLES LIKE '{$answers_table}'") !== $answers_table ) {
			return [];
		}

		$join = "INNER JOIN {$attempts_table} qa ON qa.attempt_id = a.quiz_attempt_id";
		$where = 'CAST(a.achieved_mark AS DECIMAL(10,2)) <= 0';
		if ( $course_id > 0 ) {
			$join .= " INNER JOIN {$wpdb->posts} qpost ON qpost.ID = qa.quiz_id INNER JOIN {$wpdb->posts} topic ON topic.ID = qpost.post_parent";
			$where .= $wpdb->prepare( ' AND topic.post_parent = %d', $course_id );
		}

		$rows = $wpdb->get_results( "
			SELECT a.question_id, a.given_answer as answer, COUNT(*) as selected_count
			FROM {$answers_table} a
			{$join}
			WHERE {$where}
			GROUP BY a.question_id, a.given_answer
			ORDER BY COUNT(*) DESC
			LIMIT {$limit}
		", ARRAY_A );

		$data = [];
		foreach ( (array) $rows as $row ) {
			$question_id = (int) $row['question_id'];
			$data[] = array(
				'question_id'    => $question_id,
				'question_title' => get_the_title( $question_id ),
				'answer'         => $this->format_given_answer( (string) $row['answer'] ),
				'selected_count' => (int) $row['selected_count'],
			);
		}

		return $data;
	}

	/**
	 * Convert a raw given_answer value into human-readable text.
	 *
	 * Tutor LMS stores choice-based answers as a PHP-serialized array of
	 * answer-option IDs (e.g. a:1:{i:0;s:1:"2";}); open-ended answers are
	 * stored as plain text.
	 */
	private function format_given_answer( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '—';
		}

		$values = array( $raw );

		if ( function_exists( 'is_serialized' ) && is_serialized( $raw ) ) {
			$decoded = @unserialize( $raw, array( 'allowed_classes' => false ) );
			if ( is_array( $decoded ) ) {
				$values = array_values( $decoded );
			} elseif ( is_scalar( $decoded ) ) {
				$values = array( (string) $decoded );
			}
		} elseif ( 0 === strpos( $raw, '[' ) || 0 === strpos( $raw, '{' ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$values = array_values( $decoded );
			}
		}

		$labels = array();
		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ' ', array_map( 'strval', $value ) );
			}
			$value = (string) $value;
			$label = is_numeric( $value ) ? $this->answer_option_title( (int) $value ) : '';
			$labels[] = '' !== $label ? $label : wp_strip_all_tags( $value );
		}

		return implode( ', ', array_filter( $labels, 'strlen' ) );
	}

	/**
	 * Look up the option text for an answer-option ID (cached per request).
	 */
	private function answer_option_title( int $answer_id ): string {
		global $wpdb;
		static $cache = array();
		static $table_exists = null;

		if ( isset( $cache[ $answer_id ] ) ) {
			return $cache[ $answer_id ];
		}

		$table = $wpdb->prefix . 'tutor_quiz_question_answers';
		if ( null === $table_exists ) {
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
		}
		if ( ! $table_exists ) {
			return $cache[ $answer_id ] = '';
		}

		$title = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT answer_title FROM {$table} WHERE answer_id = %d", $answer_id )
		);

		return $cache[ $answer_id ] = wp_strip_all_tags( $title );
	}

	public function get_attempts_before_pass( int $course_id = 0, int $limit = 10 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutor_quiz_attempts';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name ) {
			return [];
		}

		$join = "LEFT JOIN {$wpdb->postmeta} pm ON q.quiz_id = pm.post_id AND pm.meta_key = '_tutor_quiz_passing_grade'";
		$where = 'q.total_marks > 0';
		if ( $course_id > 0 ) {
			$join .= " INNER JOIN {$wpdb->posts} qp ON qp.ID = q.quiz_id INNER JOIN {$wpdb->posts} topic ON topic.ID = qp.post_parent";
			$where .= $wpdb->prepare( ' AND topic.post_parent = %d', $course_id );
		}

		$rows = $wpdb->get_results( "
			SELECT quiz_id, AVG(attempt_number) as avg_attempts_before_pass, COUNT(*) as passed_users
			FROM (
				SELECT
					q.quiz_id,
					q.user_id,
					COUNT(prior.attempt_id) + 1 as attempt_number
				FROM {$table_name} q
				{$join}
				LEFT JOIN {$table_name} prior ON prior.quiz_id = q.quiz_id AND prior.user_id = q.user_id AND prior.attempt_id < q.attempt_id
				WHERE {$where}
				  AND (q.earned_marks / q.total_marks * 100) >= COALESCE(NULLIF(pm.meta_value, ''), 80)
				GROUP BY q.quiz_id, q.user_id, q.attempt_id
			) passed_attempts
			GROUP BY quiz_id
			ORDER BY AVG(attempt_number) DESC
			LIMIT {$limit}
		", ARRAY_A );

		$data = [];
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'quiz_id'                  => (int) $row['quiz_id'],
				'title'                    => get_the_title( (int) $row['quiz_id'] ),
				'avg_attempts_before_pass' => round( (float) $row['avg_attempts_before_pass'], 1 ),
				'passed_users'             => (int) $row['passed_users'],
			);
		}

		return $data;
	}

	public function get_retry_behavior( int $course_id = 0, int $limit = 10 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutor_quiz_attempts';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name ) {
			return [];
		}

		$join = "LEFT JOIN {$wpdb->postmeta} pm ON q.quiz_id = pm.post_id AND pm.meta_key = '_tutor_quiz_passing_grade'";
		$where = 'q.total_marks > 0';
		if ( $course_id > 0 ) {
			$join .= " INNER JOIN {$wpdb->posts} qp ON qp.ID = q.quiz_id INNER JOIN {$wpdb->posts} topic ON topic.ID = qp.post_parent";
			$where .= $wpdb->prepare( ' AND topic.post_parent = %d', $course_id );
		}

		$rows = $wpdb->get_results( "
			SELECT
				failed.quiz_id,
				COUNT(DISTINCT failed.user_id) as failed_users,
				COUNT(DISTINCT retry.user_id) as retried_users
			FROM {$table_name} failed
			INNER JOIN {$table_name} q ON q.attempt_id = failed.attempt_id
			{$join}
			LEFT JOIN {$table_name} retry ON retry.quiz_id = failed.quiz_id AND retry.user_id = failed.user_id AND retry.attempt_id > failed.attempt_id
			WHERE {$where}
			  AND (failed.earned_marks / failed.total_marks * 100) < COALESCE(NULLIF(pm.meta_value, ''), 80)
			GROUP BY failed.quiz_id
			ORDER BY COUNT(DISTINCT retry.user_id) DESC
			LIMIT {$limit}
		", ARRAY_A );

		$data = [];
		foreach ( (array) $rows as $row ) {
			$failed = (int) $row['failed_users'];
			$retried = (int) $row['retried_users'];
			$data[] = array(
				'quiz_id'       => (int) $row['quiz_id'],
				'title'         => get_the_title( (int) $row['quiz_id'] ),
				'failed_users'  => $failed,
				'retried_users' => $retried,
				'retry_rate'    => $failed > 0 ? round( ( $retried / $failed ) * 100, 1 ) : 0.0,
			);
		}

		return $data;
	}

	public function get_pass_fail_ratio( int $course_id = 0 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutor_quiz_attempts';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		if ( ! $table_exists ) return [];

		$where = "q.total_marks > 0";
		$join  = "LEFT JOIN {$wpdb->postmeta} pm ON q.quiz_id = pm.post_id AND pm.meta_key = '_tutor_quiz_passing_grade'";
		if ( $course_id > 0 ) {
			$join  .= " INNER JOIN {$wpdb->posts} p ON q.quiz_id = p.ID";
			$where .= $wpdb->prepare( " AND p.post_parent = %d", $course_id );
		}

		$query = "
			SELECT
				SUM(CASE WHEN (q.earned_marks / q.total_marks * 100) >= COALESCE(NULLIF(pm.meta_value, ''), 80) THEN 1 ELSE 0 END) as passed,
				SUM(CASE WHEN (q.earned_marks / q.total_marks * 100) < COALESCE(NULLIF(pm.meta_value, ''), 80) THEN 1 ELSE 0 END) as failed
			FROM {$table_name} q
			{$join}
			WHERE {$where}
		";
		$stats = $wpdb->get_row( $query );

		if ( ! $stats || ($stats->passed == 0 && $stats->failed == 0) ) {
			return [];
		}

		return array(
			'สอบผ่าน (Pass)' => (int) $stats->passed,
			'สอบตก (Fail)'   => (int) $stats->failed,
		);
	}
}
