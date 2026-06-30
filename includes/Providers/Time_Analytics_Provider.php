<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Time_Analytics_Provider {

	/**
	 * Get all time analytics for a course.
	 */
	public function get_time_analytics( int $course_id = 0 ): array {
		return array(
			'avg_time_per_lesson' => $this->get_avg_time_per_lesson( $course_id ),
			'total_learning_time' => $this->get_total_learning_time( $course_id ),
			'time_per_content'    => $this->get_time_per_content( $course_id ),
			'avg_days_to_complete' => $this->get_avg_days_to_complete( $course_id ),
		);
	}

	/**
	 * Average time spent per lesson (in seconds), calculated from page_exit events.
	 */
	public function get_avg_time_per_lesson( int $course_id = 0 ): float {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutorlms_analytics_events';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		if ( ! $table_exists ) return 0;

		$where = "event_type = 'page_exit' AND lesson_id > 0 AND CAST(event_value AS UNSIGNED) > 0 AND CAST(event_value AS UNSIGNED) < 7200";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND course_id = %d", $course_id );
		}

		$avg = $wpdb->get_var( "SELECT AVG(CAST(event_value AS UNSIGNED)) FROM {$table_name} WHERE {$where}" );
		return round( (float) $avg, 0 );
	}

	/**
	 * Total average learning time per student across the course (in seconds).
	 */
	public function get_total_learning_time( int $course_id = 0 ): float {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutorlms_analytics_events';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		if ( ! $table_exists ) return 0;

		$where = "event_type = 'page_exit' AND CAST(event_value AS UNSIGNED) > 0 AND CAST(event_value AS UNSIGNED) < 7200";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND course_id = %d", $course_id );
		}

		// Sum time per user, then average across users
		$avg = $wpdb->get_var( "
			SELECT AVG(total_time) FROM (
				SELECT user_id, SUM(CAST(event_value AS UNSIGNED)) as total_time
				FROM {$table_name}
				WHERE {$where} AND user_id > 0
				GROUP BY user_id
			) as user_times
		" );
		return round( (float) $avg, 0 );
	}

	/**
	 * Time spent per individual content item (lesson/quiz) for a course.
	 */
	public function get_time_per_content( int $course_id ): array {
		global $wpdb;

		if ( $course_id <= 0 ) return [];

		$table_name = $wpdb->prefix . 'tutorlms_analytics_events';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		if ( ! $table_exists ) return [];

		$query = $wpdb->prepare( "
			SELECT 
				e.lesson_id,
				AVG(CAST(e.event_value AS UNSIGNED)) as avg_seconds,
				COUNT(*) as sample_count
			FROM {$table_name} e
			WHERE e.event_type = 'page_exit'
			  AND e.lesson_id > 0
			  AND e.course_id = %d
			  AND CAST(e.event_value AS UNSIGNED) > 0
			  AND CAST(e.event_value AS UNSIGNED) < 7200
			GROUP BY e.lesson_id
			ORDER BY avg_seconds DESC
		", $course_id );

		$results = $wpdb->get_results( $query, ARRAY_A );
		$data = [];

		foreach ( (array) $results as $row ) {
			$title = get_the_title( (int) $row['lesson_id'] );
			if ( $title ) {
				$data[] = array(
					'lesson_id'    => (int) $row['lesson_id'],
					'title'        => $title,
					'avg_seconds'  => round( (float) $row['avg_seconds'], 0 ),
					'sample_count' => (int) $row['sample_count'],
				);
			}
		}

		return $data;
	}

	/**
	 * Average number of days from enrollment to course completion.
	 */
	public function get_avg_days_to_complete( int $course_id = 0 ): float {
		global $wpdb;

		$where_enroll   = "e.post_type = 'tutor_enrolled' AND e.post_status IN ('completed', 'processing', 'publish')";
		$where_complete = "c.comment_type = 'course_completed' AND c.comment_approved = 'approved'";

		if ( $course_id > 0 ) {
			$where_enroll   .= $wpdb->prepare( " AND e.post_parent = %d", $course_id );
			$where_complete .= $wpdb->prepare( " AND c.comment_post_ID = %d", $course_id );
		}

		$query = "
			SELECT AVG(DATEDIFF(c.comment_date, e.post_date)) as avg_days
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->posts} e 
				ON e.post_author = c.user_id 
				AND e.post_parent = c.comment_post_ID
			WHERE {$where_enroll}
			  AND {$where_complete}
			  AND DATEDIFF(c.comment_date, e.post_date) >= 0
		";

		$avg = $wpdb->get_var( $query );
		return round( (float) $avg, 1 );
	}
}
