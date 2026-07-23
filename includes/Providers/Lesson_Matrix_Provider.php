<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

/**
 * Per-student lesson progress matrix.
 *
 * Rows = enrolled students, columns = lessons in curriculum order.
 * Marks which lessons each student completed and their latest (current) lesson.
 */
class Lesson_Matrix_Provider {

	/**
	 * @param int $course_id Course post ID.
	 * @param int $limit     Max students (0 = no limit).
	 * @return array{lessons: array, students: array, summary: array}
	 */
	public function get_lesson_matrix( int $course_id, int $limit = 0 ): array {
		global $wpdb;

		$empty = array( 'lessons' => array(), 'students' => array(), 'summary' => array() );
		if ( $course_id <= 0 ) {
			return $empty;
		}

		// 1. Ordered lessons: course -> topics -> lessons.
		$topics = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'topics' AND post_parent = %d AND post_status = 'publish'
			 ORDER BY menu_order ASC, ID ASC",
			$course_id
		) );

		$lessons = array();
		foreach ( $topics as $topic_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts}
				 WHERE post_type = 'lesson' AND post_parent = %d AND post_status = 'publish'
				 ORDER BY menu_order ASC, ID ASC",
				(int) $topic_id
			), ARRAY_A );
			foreach ( $rows as $row ) {
				$lessons[] = array(
					'id'    => (int) $row['ID'],
					'title' => $row['post_title'],
				);
			}
		}

		if ( empty( $lessons ) ) {
			return $empty;
		}

		$lesson_ids = array_column( $lessons, 'id' );
		$lesson_order = array_flip( $lesson_ids ); // id => position

		// 2. Enrolled students.
		$limit_clause = $limit > 0 ? $wpdb->prepare( 'LIMIT %d', $limit ) : '';
		$enrolled = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_author AS user_id, MIN(post_date) AS enrolled_at
			 FROM {$wpdb->posts}
			 WHERE post_type = 'tutor_enrolled' AND post_parent = %d
			   AND post_status IN ('completed','processing','publish')
			 GROUP BY post_author
			 ORDER BY enrolled_at ASC
			 {$limit_clause}",
			$course_id
		), ARRAY_A );

		if ( empty( $enrolled ) ) {
			return array( 'lessons' => $lessons, 'students' => array(), 'summary' => array() );
		}

		$user_ids  = array_map( 'intval', array_column( $enrolled, 'user_id' ) );
		$in_users  = implode( ',', $user_ids );
		$in_lessons = implode( ',', array_map( 'intval', $lesson_ids ) );

		// 3a. Completions from lesson_completed comments (carries timestamp).
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IDs are int-sanitized above.
		$completion_rows = $wpdb->get_results(
			"SELECT user_id, comment_post_ID AS lesson_id, MAX(comment_date) AS completed_at
			 FROM {$wpdb->comments}
			 WHERE comment_type = 'lesson_completed'
			   AND comment_approved IN ('approved','1')
			   AND user_id IN ({$in_users})
			   AND comment_post_ID IN ({$in_lessons})
			 GROUP BY user_id, comment_post_ID",
			ARRAY_A
		);

		$completed = array(); // user_id => [ lesson_id => timestamp ]
		foreach ( $completion_rows as $row ) {
			$completed[ (int) $row['user_id'] ][ (int) $row['lesson_id'] ] = strtotime( (string) $row['completed_at'] );
		}

		// 3b. Union with _tutor_completed_lesson_id_{id} usermeta (value = timestamp).
		$meta_keys = array();
		foreach ( $lesson_ids as $lid ) {
			$meta_keys[] = "'_tutor_completed_lesson_id_" . (int) $lid . "'";
		}
		$in_keys = implode( ',', $meta_keys );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- keys built from int IDs.
		$meta_rows = $wpdb->get_results(
			"SELECT user_id, meta_key, meta_value
			 FROM {$wpdb->usermeta}
			 WHERE meta_key IN ({$in_keys}) AND user_id IN ({$in_users})",
			ARRAY_A
		);
		foreach ( $meta_rows as $row ) {
			$uid = (int) $row['user_id'];
			$lid = (int) str_replace( '_tutor_completed_lesson_id_', '', $row['meta_key'] );
			$ts  = is_numeric( $row['meta_value'] ) ? (int) $row['meta_value'] : 0;
			if ( ! isset( $completed[ $uid ][ $lid ] ) || $ts > $completed[ $uid ][ $lid ] ) {
				$completed[ $uid ][ $lid ] = $ts;
			}
		}

		// 4. Build per-student rows.
		$total    = count( $lessons );
		$students = array();
		$per_lesson_counts = array_fill_keys( $lesson_ids, 0 );

		foreach ( $user_ids as $uid ) {
			$user_info = get_userdata( $uid );
			if ( ! $user_info ) {
				continue;
			}

			$user_completed = isset( $completed[ $uid ] ) ? $completed[ $uid ] : array();
			$done_ids = array_values( array_intersect( $lesson_ids, array_keys( $user_completed ) ) );

			// Current = completed lesson furthest along the curriculum.
			$current_id = 0;
			$current_pos = -1;
			$last_ts = 0;
			foreach ( $done_ids as $lid ) {
				$per_lesson_counts[ $lid ]++;
				if ( $lesson_order[ $lid ] > $current_pos ) {
					$current_pos = $lesson_order[ $lid ];
					$current_id  = $lid;
				}
				if ( $user_completed[ $lid ] > $last_ts ) {
					$last_ts = $user_completed[ $lid ];
				}
			}

			$count = count( $done_ids );
			$students[] = array(
				'user_id'           => $uid,
				'display_name'      => $user_info->display_name,
				'avatar'            => get_avatar_url( $uid, array( 'size' => 48 ) ),
				'completed'         => $done_ids,
				'completed_count'   => $count,
				'total_lessons'     => $total,
				'percent'           => $total > 0 ? round( $count / $total * 100, 1 ) : 0,
				'current_lesson_id' => $current_id,
				'last_completed_at' => $last_ts > 0 ? gmdate( 'Y-m-d H:i:s', $last_ts ) : null,
			);
		}

		// Sort: furthest progress first.
		usort( $students, static function ( $a, $b ) {
			return $b['completed_count'] <=> $a['completed_count'];
		} );

		$student_count = count( $students );
		$avg_percent   = 0;
		$completed_all = 0;
		$not_started   = 0;
		foreach ( $students as $s ) {
			$avg_percent += $s['percent'];
			if ( $s['completed_count'] >= $total ) {
				$completed_all++;
			}
			if ( 0 === $s['completed_count'] ) {
				$not_started++;
			}
		}

		return array(
			'lessons'  => $lessons,
			'students' => $students,
			'summary'  => array(
				'total_students' => $student_count,
				'total_lessons'  => $total,
				'avg_percent'    => $student_count > 0 ? round( $avg_percent / $student_count, 1 ) : 0,
				'completed_all'  => $completed_all,
				'not_started'    => $not_started,
				'in_progress'    => $student_count - $completed_all - $not_started,
				'per_lesson'     => $per_lesson_counts,
			),
		);
	}
}
