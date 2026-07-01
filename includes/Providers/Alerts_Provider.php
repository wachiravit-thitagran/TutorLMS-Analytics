<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Alerts_Provider {

	public function get_alerts( int $course_id = 0 ): array {
		$alerts = array();
		
		$alerts = array_merge( $alerts, $this->get_low_rating_alerts( $course_id ) );
		$alerts = array_merge( $alerts, $this->get_inactive_learner_alerts( $course_id ) );
		
		return $alerts;
	}

	private function get_low_rating_alerts( int $course_id ): array {
		global $wpdb;
		$alerts = array();

		$where = "c.comment_type = 'tutor_course_rating' AND c.comment_approved IN ('approved', '1') AND m.meta_key = 'tutor_rating' AND CAST(m.meta_value AS UNSIGNED) <= 2";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND c.comment_post_ID = %d", $course_id );
		}
		
		// Get ratings from last 30 days
		$where .= " AND c.comment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

		$query = "
			SELECT c.comment_ID, c.comment_post_ID, m.meta_value as rating, c.comment_author 
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_id
			WHERE {$where}
			ORDER BY c.comment_date DESC
			LIMIT 5
		";
		$results = $wpdb->get_results( $query );

		foreach ( (array) $results as $r ) {
			$course_name = get_the_title( $r->comment_post_ID );
			$alerts[] = array(
				'type'    => 'warning',
				'title'   => 'Low Rating Alert',
				'message' => sprintf( 'Course "%s" received a %d-star rating from %s.', $course_name, $r->rating, $r->comment_author ),
				'action'  => admin_url( 'admin.php?page=tutor-reviews' )
			);
		}

		return $alerts;
	}

	private function get_inactive_learner_alerts( int $course_id ): array {
		global $wpdb;
		$alerts = array();

		// Check tracking table if it exists
		$table_name = $wpdb->prefix . 'tutorlms_analytics_events';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		
		if ( ! $table_exists ) {
			return $alerts; // Can't reliably check inactivity without tracking table
		}

		$where = "";
		if ( $course_id > 0 ) {
			$where = $wpdb->prepare( "WHERE course_id = %d", $course_id );
		}

		// Find users whose last event was > 14 days ago but they haven't completed the course
		$query = "
			SELECT user_id, MAX(created_at) as last_seen 
			FROM {$table_name} 
			{$where}
			GROUP BY user_id 
			HAVING last_seen < DATE_SUB(NOW(), INTERVAL 14 DAY)
		";
		
		$inactive_count = (int) $wpdb->query( $query );

		if ( $inactive_count > 0 ) {
			$alerts[] = array(
				'type'    => 'danger',
				'title'   => 'Inactive Learners',
				'message' => sprintf( '%d learners have been inactive for over 14 days.', $inactive_count ),
				'action'  => admin_url( 'admin.php?page=tutor_students' ) // Fallback URL
			);
		}

		return $alerts;
	}
}
