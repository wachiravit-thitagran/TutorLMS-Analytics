<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

class Data_Provider {
	
	public function get_all_courses(): array {
		global $wpdb;
		$results = $wpdb->get_results( "
			SELECT ID, post_title 
			FROM {$wpdb->posts} 
			WHERE post_type = 'courses' AND post_status = 'publish'
			ORDER BY post_title ASC
		", ARRAY_A );
		return is_array( $results ) ? $results : array();
	}

	public function get_all_stats( int $course_id = 0 ): array {
		return array(
			'total_students'      => $this->get_total_enrolled_students( $course_id ),
			'total_completions'   => $this->get_total_course_completions( $course_id ),
			'enrollment_trend'    => $this->get_enrollment_trend_30_days( $course_id ),
			'top_courses'         => $this->get_top_courses_by_enrollment( $course_id ),
		);
	}

	private function get_total_enrolled_students( int $course_id ): int {
		global $wpdb;
		$where = "comment_type = 'tutor_enrolled' AND comment_approved = 'approved'";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND comment_post_ID = %d", $course_id );
		}
		$count = $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->comments} WHERE {$where}" );
		return (int) $count;
	}

	private function get_total_course_completions( int $course_id ): int {
		global $wpdb;
		$where = "comment_type = 'course_completed' AND comment_approved = 'approved'";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND comment_post_ID = %d", $course_id );
		}
		$count = $wpdb->get_var( "SELECT COUNT(DISTINCT comment_ID) FROM {$wpdb->comments} WHERE {$where}" );
		return (int) $count;
	}

	private function get_enrollment_trend_30_days( int $course_id ): array {
		global $wpdb;
		
		$where = "comment_type = 'tutor_enrolled' AND comment_approved = 'approved' AND comment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND comment_post_ID = %d", $course_id );
		}

		$query = "
			SELECT DATE(comment_date) as date, COUNT(comment_ID) as count
			FROM {$wpdb->comments}
			WHERE {$where}
			GROUP BY DATE(comment_date)
			ORDER BY DATE(comment_date) ASC
		";
		$results = $wpdb->get_results( $query, ARRAY_A );
		
		$trend = array(
			'labels' => array(),
			'data'   => array(),
		);
		foreach ( (array) $results as $row ) {
			$trend['labels'][] = $row['date'];
			$trend['data'][]   = (int) $row['count'];
		}
		return $trend;
	}

	private function get_top_courses_by_enrollment( int $course_id ): array {
		global $wpdb;
		
		$where = "comment_type = 'tutor_enrolled' AND comment_approved = 'approved'";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND comment_post_ID = %d", $course_id );
		}

		$query = "
			SELECT comment_post_ID as course_id, COUNT(comment_ID) as count
			FROM {$wpdb->comments}
			WHERE {$where}
			GROUP BY comment_post_ID
			ORDER BY count DESC
			LIMIT 5
		";
		$results = $wpdb->get_results( $query, ARRAY_A );
		
		$top = array();
		foreach ( (array) $results as $row ) {
			$course_title = get_the_title( $row['course_id'] );
			$top[] = array(
				'id'    => (int) $row['course_id'],
				'title' => $course_title ?: __( 'Unknown Course', 'tutorlms-analytics' ),
				'count' => (int) $row['count'],
			);
		}
		return $top;
	}
}
