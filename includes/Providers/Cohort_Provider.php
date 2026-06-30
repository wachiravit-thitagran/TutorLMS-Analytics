<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Cohort_Provider {

	public function get_cohort_analytics( int $course_id = 0 ): array {
		return array(
			'completion_by_enrollment_cohort' => $this->get_completion_by_enrollment_cohort( $course_id ),
			'retention_by_week'               => $this->get_retention_by_week( $course_id ),
		);
	}

	public function get_completion_by_enrollment_cohort( int $course_id = 0, int $months = 12 ): array {
		global $wpdb;

		$where = "e.post_type = 'tutor_enrolled' AND e.post_status IN ('completed', 'processing', 'publish')";
		$where .= $wpdb->prepare( ' AND e.post_date >= DATE_SUB(NOW(), INTERVAL %d MONTH)', $months );
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( ' AND e.post_parent = %d', $course_id );
		}

		$query = "
			SELECT
				DATE_FORMAT(e.post_date, '%Y-%m') as cohort,
				COUNT(DISTINCT e.post_author) as enrolled,
				COUNT(DISTINCT c.user_id) as completed
			FROM {$wpdb->posts} e
			LEFT JOIN {$wpdb->comments} c
				ON c.user_id = e.post_author
				AND c.comment_post_ID = e.post_parent
				AND c.comment_type = 'course_completed'
				AND c.comment_approved = 'approved'
			WHERE {$where}
			GROUP BY cohort
			ORDER BY cohort ASC
		";

		$rows = $wpdb->get_results( $query, ARRAY_A );
		$data = array();

		foreach ( (array) $rows as $row ) {
			$enrolled = (int) $row['enrolled'];
			$completed = (int) $row['completed'];
			$data[] = array(
				'cohort'          => (string) $row['cohort'],
				'enrolled'        => $enrolled,
				'completed'       => $completed,
				'completion_rate' => $enrolled > 0 ? round( ( $completed / $enrolled ) * 100, 1 ) : 0.0,
			);
		}

		return $data;
	}

	public function get_retention_by_week( int $course_id = 0, int $weeks = 8 ): array {
		global $wpdb;

		$events_table = $wpdb->prefix . 'tutorlms_analytics_events';
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$events_table}'" ) === $events_table;
		if ( ! $table_exists ) {
			return array();
		}

		$where = "e.post_type = 'tutor_enrolled' AND e.post_status IN ('completed', 'processing', 'publish')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( ' AND e.post_parent = %d', $course_id );
		}

		$total_enrolled = (int) $wpdb->get_var( "
			SELECT COUNT(DISTINCT e.post_author)
			FROM {$wpdb->posts} e
			WHERE {$where}
		" );

		if ( $total_enrolled <= 0 ) {
			return array();
		}

		$query = $wpdb->prepare( "
			SELECT
				TIMESTAMPDIFF(WEEK, e.post_date, ev.created_at) + 1 as week_number,
				COUNT(DISTINCT ev.user_id) as active_learners
			FROM {$wpdb->posts} e
			INNER JOIN {$events_table} ev
				ON ev.user_id = e.post_author
				AND ev.course_id = e.post_parent
			WHERE {$where}
			  AND ev.user_id > 0
			  AND ev.created_at >= e.post_date
			  AND TIMESTAMPDIFF(WEEK, e.post_date, ev.created_at) BETWEEN 0 AND %d
			GROUP BY week_number
			ORDER BY week_number ASC
		", max( 0, $weeks - 1 ) );

		$rows = $wpdb->get_results( $query, ARRAY_A );
		$active_by_week = array();
		foreach ( (array) $rows as $row ) {
			$active_by_week[ (int) $row['week_number'] ] = (int) $row['active_learners'];
		}

		$data = array();
		for ( $week = 1; $week <= $weeks; $week++ ) {
			$active = $active_by_week[ $week ] ?? 0;
			$data[] = array(
				'week'            => 'Week ' . $week,
				'active_learners' => $active,
				'retention_rate'   => round( ( $active / $total_enrolled ) * 100, 1 ),
			);
		}

		return $data;
	}
}
