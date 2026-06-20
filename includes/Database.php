<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

class Database {

	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::get_events_table_name();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			course_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lesson_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(100) NOT NULL,
			event_value text,
			user_agent text,
			device_type varchar(50),
			browser varchar(50),
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY lesson_id (lesson_id),
			KEY event_type (event_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function get_events_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'tutorlms_analytics_events';
	}
}
