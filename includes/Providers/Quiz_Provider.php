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
		$join  = "";
		
		if ( $course_id > 0 ) {
			$join  = "INNER JOIN {$wpdb->posts} p ON q.quiz_id = p.ID";
			$where .= $wpdb->prepare( " AND p.post_parent = %d", $course_id );
		}

		$stats = $wpdb->get_row( "
			SELECT 
				AVG(q.earned_marks / q.total_marks * 100) as avg_score,
				SUM(CASE WHEN q.is_pass = 1 THEN 1 ELSE 0 END) as passed_count,
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
}
