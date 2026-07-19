<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

use TutorLMS_Analytics\Date_Range;
use TutorLMS_Analytics\Tutor_Schema;

/**
 * Live-lesson analytics for Zoom ('tutor_zoom_meeting') and Google Meet
 * ('tutor-google-meet') integrations (verified post types / meta keys, 4.0.1).
 *
 * A live lesson's parent can be a course directly or a topic (whose parent is
 * the course), so both are resolved. Start datetimes live in postmeta as
 * 'Y-m-d H:i:s' strings, compared as strings against the site clock.
 */
class Live_Lesson_Provider {

	private const ZOOM_META = '_tutor_zm_start_datetime';
	private const MEET_META = 'tutor-google-meet-start-datetime';

	/**
	 * @param int             $course_id Optional course filter (0 = store-wide).
	 * @param Date_Range|null $range     Date range; defaults to the last 30 days.
	 */
	public function get_live_lesson_stats( int $course_id = 0, ?Date_Range $range = null ): array {
		global $wpdb;

		$range = $range ?? Date_Range::last_days( 30 );

		$stats = array(
			'available'    => false,
			'total'        => 0,
			'zoom'         => 0,
			'google_meet'  => 0,
			'held_in_range' => 0,
			'upcoming'     => array(),
			'per_course'   => array(),
		);

		if ( ! Tutor_Schema::has_live_lessons() ) {
			return $stats;
		}
		$stats['available'] = true;

		$now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );

		$stats['zoom']        = $this->count_type( Tutor_Schema::PT_ZOOM_LESSON, $course_id );
		$stats['google_meet'] = $this->count_type( Tutor_Schema::PT_MEET_LESSON, $course_id );
		$stats['total']       = $stats['zoom'] + $stats['google_meet'];

		// Meetings whose start datetime falls in range and is in the past.
		$stats['held_in_range'] =
			$this->count_held( Tutor_Schema::PT_ZOOM_LESSON, self::ZOOM_META, $course_id, $range, $now )
			+ $this->count_held( Tutor_Schema::PT_MEET_LESSON, self::MEET_META, $course_id, $range, $now );

		// Upcoming meetings (start >= now), merged and sorted, top 5.
		$upcoming = array_merge(
			$this->upcoming_rows( Tutor_Schema::PT_ZOOM_LESSON, self::ZOOM_META, 'zoom', $course_id, $now ),
			$this->upcoming_rows( Tutor_Schema::PT_MEET_LESSON, self::MEET_META, 'google_meet', $course_id, $now )
		);
		usort(
			$upcoming,
			static function ( $a, $b ) {
				return strcmp( $a['start'], $b['start'] );
			}
		);
		$stats['upcoming'] = array_slice( $upcoming, 0, 5 );

		// Per-course counts (global view only).
		if ( 0 === $course_id ) {
			$stats['per_course'] = $this->per_course_counts();
		}

		return $stats;
	}

	private function count_type( string $post_type, int $course_id ): int {
		global $wpdb;

		if ( $course_id > 0 ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$wpdb->posts} m
					LEFT JOIN {$wpdb->posts} parent ON parent.ID = m.post_parent
					WHERE m.post_type = %s
						AND m.post_status = 'publish'
						AND ( m.post_parent = %d OR parent.post_parent = %d )",
					$post_type,
					$course_id,
					$course_id
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				$post_type
			)
		);
	}

	private function count_held( string $post_type, string $meta_key, int $course_id, Date_Range $range, string $now ): int {
		global $wpdb;

		$course_join  = '';
		$course_where = '';
		if ( $course_id > 0 ) {
			$course_join  = "LEFT JOIN {$wpdb->posts} parent ON parent.ID = m.post_parent";
			$course_where = $wpdb->prepare( ' AND ( m.post_parent = %d OR parent.post_parent = %d )', $course_id, $course_id );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->posts} m
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = m.ID AND pm.meta_key = %s
				{$course_join}
				WHERE m.post_type = %s
					AND m.post_status = 'publish'
					AND pm.meta_value <> ''
					AND pm.meta_value BETWEEN %s AND %s
					AND pm.meta_value <= %s
					{$course_where}",
				$meta_key,
				$post_type,
				$range->start_sql(),
				$range->end_sql(),
				$now
			)
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function upcoming_rows( string $post_type, string $meta_key, string $type_label, int $course_id, string $now ): array {
		global $wpdb;

		$course_join  = "LEFT JOIN {$wpdb->posts} parent ON parent.ID = m.post_parent";
		$course_where = '';
		if ( $course_id > 0 ) {
			$course_where = $wpdb->prepare( ' AND ( m.post_parent = %d OR parent.post_parent = %d )', $course_id, $course_id );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.ID AS id, m.post_title AS title, pm.meta_value AS start,
						COALESCE( NULLIF(parent.post_parent, 0), m.post_parent ) AS course_id
				FROM {$wpdb->posts} m
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = m.ID AND pm.meta_key = %s
				{$course_join}
				WHERE m.post_type = %s
					AND m.post_status = 'publish'
					AND pm.meta_value <> ''
					AND pm.meta_value >= %s
					{$course_where}
				ORDER BY pm.meta_value ASC
				LIMIT 5",
				$meta_key,
				$post_type,
				$now
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$row = (array) $row;
			$cid = (int) ( $row['course_id'] ?? 0 );
			$out[] = array(
				'id'           => (int) ( $row['id'] ?? 0 ),
				'title'        => (string) ( $row['title'] ?? '' ),
				'type'         => $type_label,
				'start'        => (string) ( $row['start'] ?? '' ),
				'course_id'    => $cid,
				'course_title' => get_the_title( $cid ),
			);
		}

		return $out;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function per_course_counts(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE( NULLIF(parent.post_parent, 0), m.post_parent ) AS course_id,
						COUNT(*) AS cnt
				FROM {$wpdb->posts} m
				LEFT JOIN {$wpdb->posts} parent ON parent.ID = m.post_parent
				WHERE m.post_type IN (%s, %s)
					AND m.post_status = 'publish'
				GROUP BY course_id
				ORDER BY cnt DESC
				LIMIT 5",
				Tutor_Schema::PT_ZOOM_LESSON,
				Tutor_Schema::PT_MEET_LESSON
			),
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$row = (array) $row;
			$cid = (int) ( $row['course_id'] ?? 0 );
			$out[] = array(
				'id'    => $cid,
				'title' => get_the_title( $cid ),
				'count' => (int) ( $row['cnt'] ?? 0 ),
			);
		}

		return $out;
	}
}
