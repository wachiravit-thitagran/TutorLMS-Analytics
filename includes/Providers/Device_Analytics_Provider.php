<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

class Device_Analytics_Provider {

	/**
	 * Get all device analytics for a course.
	 */
	public function get_device_analytics( int $course_id = 0 ): array {
		return array(
			'device_distribution' => $this->get_device_distribution( $course_id ),
			'browser_distribution' => $this->get_browser_distribution( $course_id ),
			'hourly_activity'      => $this->get_hourly_activity( $course_id ),
		);
	}

	/**
	 * Device type distribution (Desktop / Mobile / Tablet).
	 */
	public function get_device_distribution( int $course_id = 0 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutorlms_analytics_events';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		if ( ! $table_exists ) return [];

		$where = "device_type IS NOT NULL AND device_type != ''";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND course_id = %d", $course_id );
		}

		$results = $wpdb->get_results( "
			SELECT device_type, COUNT(*) as count
			FROM {$table_name}
			WHERE {$where}
			GROUP BY device_type
			ORDER BY count DESC
		", ARRAY_A );

		$labels_map = array(
			'desktop' => 'Desktop',
			'mobile'  => 'Mobile',
			'tablet'  => 'Tablet',
		);

		$data = array();
		foreach ( (array) $results as $row ) {
			$label = $labels_map[ strtolower( $row['device_type'] ) ] ?? ucfirst( $row['device_type'] );
			$data[ $label ] = (int) $row['count'];
		}

		return $data;
	}

	/**
	 * Browser distribution.
	 */
	public function get_browser_distribution( int $course_id = 0 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutorlms_analytics_events';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		if ( ! $table_exists ) return [];

		$where = "browser IS NOT NULL AND browser != ''";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND course_id = %d", $course_id );
		}

		$results = $wpdb->get_results( "
			SELECT browser, COUNT(*) as count
			FROM {$table_name}
			WHERE {$where}
			GROUP BY browser
			ORDER BY count DESC
			LIMIT 6
		", ARRAY_A );

		$data = array();
		foreach ( (array) $results as $row ) {
			$data[ $row['browser'] ] = (int) $row['count'];
		}

		return $data;
	}

	/**
	 * Activity distribution by hour of day (0-23).
	 */
	public function get_hourly_activity( int $course_id = 0 ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'tutorlms_analytics_events';
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
		if ( ! $table_exists ) return [];

		$where = "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND course_id = %d", $course_id );
		}

		$results = $wpdb->get_results( "
			SELECT HOUR(created_at) as hour_num, COUNT(*) as count
			FROM {$table_name}
			WHERE {$where}
			GROUP BY HOUR(created_at)
			ORDER BY hour_num ASC
		", ARRAY_A );

		// Initialize all 24 hours
		$data = array_fill( 0, 24, 0 );
		foreach ( (array) $results as $row ) {
			$data[ (int) $row['hour_num'] ] = (int) $row['count'];
		}

		// Convert to labeled array
		$labeled = array();
		foreach ( $data as $hour => $count ) {
			$label = sprintf( '%02d:00', $hour );
			$labeled[ $label ] = $count;
		}

		return $labeled;
	}
}
