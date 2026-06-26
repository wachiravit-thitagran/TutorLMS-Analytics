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
