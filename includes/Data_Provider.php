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
			'enrollment_trend'      => $this->get_enrollment_trend_30_days( $course_id ),
			'top_courses'           => $this->get_top_courses_by_enrollment( $course_id ),
			'completion_trend'      => $this->get_daily_completions_30_days( $course_id ),
			'active_students_trend' => $this->get_daily_active_students_30_days( $course_id ),
			'course_popularity'     => $course_id === 0 ? $this->get_course_popularity() : [],
			'activity_by_day'       => $this->get_activity_by_day_of_week( $course_id ),
			'content_insights'      => $course_id > 0 ? $this->get_course_content_insights( $course_id ) : [],
		);
	}

	private function get_total_enrolled_students( int $course_id ): int {
		global $wpdb;
		$where = "post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND post_parent = %d", $course_id );
		}
		$count = $wpdb->get_var( "SELECT COUNT(DISTINCT post_author) FROM {$wpdb->posts} WHERE {$where}" );
		return (int) $count;
	}

	private function get_total_course_completions( int $course_id ): int {
		global $wpdb;
		$where = "comment_type = 'course_completed' AND comment_approved IN ('approved', '1')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND comment_post_ID = %d", $course_id );
		}
		$count = $wpdb->get_var( "SELECT COUNT(DISTINCT comment_ID) FROM {$wpdb->comments} WHERE {$where}" );
		return (int) $count;
	}

	private function get_enrollment_trend_30_days( int $course_id ): array {
		global $wpdb;
		
		$where = "post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish') AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND post_parent = %d", $course_id );
		}

		$query = "
			SELECT DATE(post_date) as date, COUNT(ID) as count
			FROM {$wpdb->posts}
			WHERE {$where}
			GROUP BY DATE(post_date)
			ORDER BY DATE(post_date) ASC
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

	private function get_daily_completions_30_days( int $course_id ): array {
		global $wpdb;
		
		$where = "comment_type = 'course_completed' AND comment_approved IN ('approved', '1') AND comment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
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

	private function get_daily_active_students_30_days( int $course_id ): array {
		global $wpdb;
		
		$types = "'course_completed', 'lesson_completed', 'tutor_quiz_attempt', 'assignment_submitted'";
		$where = "comment_type IN ($types) AND comment_approved IN ('approved', '1') AND user_id > 0 AND comment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND comment_post_ID = %d", $course_id );
		}

		$query = "
			SELECT DATE(comment_date) as date, COUNT(DISTINCT user_id) as count
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
		
		$where = "post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND post_parent = %d", $course_id );
		}

		$query = "
			SELECT post_parent as course_id, COUNT(ID) as count
			FROM {$wpdb->posts}
			WHERE {$where}
			GROUP BY post_parent
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

	private function get_course_popularity(): array {
		global $wpdb;
		
		$query = "
			SELECT post_parent as course_id, COUNT(ID) as count
			FROM {$wpdb->posts}
			WHERE post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')
			GROUP BY post_parent
			ORDER BY count DESC
		";
		$results = $wpdb->get_results( $query, ARRAY_A );
		
		$popularity = array();
		foreach ( (array) $results as $row ) {
			$course_title = get_the_title( $row['course_id'] );
			if ( $course_title ) {
				$popularity[ $course_title ] = (int) $row['count'];
			}
		}
		
		// If more than 6, group others
		if ( count( $popularity ) > 6 ) {
			$top = array_slice( $popularity, 0, 5, true );
			$others_sum = array_sum( array_slice( $popularity, 5 ) );
			$top['อื่นๆ (Others)'] = $others_sum;
			return $top;
		}
		
		return $popularity;
	}

	private function get_activity_by_day_of_week( int $course_id ): array {
		global $wpdb;
		
		$types = "'course_completed', 'lesson_completed', 'tutor_quiz_attempt', 'assignment_submitted'";
		$where = "comment_type IN ($types) AND comment_approved IN ('approved', '1') AND comment_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( " AND comment_post_ID = %d", $course_id );
		}

		$query = "
			SELECT DAYOFWEEK(comment_date) as day_num, COUNT(comment_ID) as count
			FROM {$wpdb->comments}
			WHERE {$where}
			GROUP BY DAYOFWEEK(comment_date)
		";
		$results = $wpdb->get_results( $query, ARRAY_A );
		
		$days_map = array(
			1 => 'อาทิตย์',
			2 => 'จันทร์',
			3 => 'อังคาร',
			4 => 'พุธ',
			5 => 'พฤหัสบดี',
			6 => 'ศุกร์',
			7 => 'เสาร์',
		);
		
		$data = array_fill_keys( array_values( $days_map ), 0 );
		foreach ( (array) $results as $row ) {
			$day_name = $days_map[ (int) $row['day_num'] ];
			$data[ $day_name ] = (int) $row['count'];
		}
		
		return $data;
	}

	private function get_course_content_insights( int $course_id ): array {
		global $wpdb;
		if ( $course_id <= 0 ) return [];

		// 1. Get all topics for this course ordered by menu_order
		$topics = $wpdb->get_results( $wpdb->prepare( "
			SELECT ID, post_title 
			FROM {$wpdb->posts} 
			WHERE post_type = 'topics' AND post_parent = %d AND post_status = 'publish'
			ORDER BY menu_order ASC, ID ASC
		", $course_id ), ARRAY_A );

		if ( empty( $topics ) ) return [];

		$insights = [];

		$quiz_attempts_table = $wpdb->prefix . 'tutor_quiz_attempts';
		$has_quiz_table = $wpdb->get_var("SHOW TABLES LIKE '{$quiz_attempts_table}'") === $quiz_attempts_table;

		foreach ( (array) $topics as $topic ) {
			$topic_id = (int) $topic['ID'];
			
			// 2. Get children (lesson, tutor_quiz) of this topic
			$contents = $wpdb->get_results( $wpdb->prepare( "
				SELECT ID, post_title, post_type
				FROM {$wpdb->posts}
				WHERE post_type IN ('lesson', 'tutor_quiz') AND post_parent = %d AND post_status = 'publish'
				ORDER BY menu_order ASC, ID ASC
			", $topic_id ), ARRAY_A );

			if ( empty( $contents ) ) continue;

			$topic_data = [
				'id'       => $topic_id,
				'title'    => $topic['post_title'],
				'contents' => [],
			];

			foreach ( (array) $contents as $content ) {
				$content_id = (int) $content['ID'];
				$item = [
					'id'    => $content_id,
					'title' => $content['post_title'],
					'type'  => $content['post_type'],
				];

				if ( $content['post_type'] === 'lesson' ) {
					// Count completed students
					$completed_count = $wpdb->get_var( $wpdb->prepare( "
						SELECT COUNT(DISTINCT user_id) 
						FROM {$wpdb->comments} 
						WHERE comment_type = 'lesson_completed' 
						  AND comment_post_ID = %d 
						  AND comment_approved IN ('approved', '1')
					", $content_id ) );
					$item['completed_count'] = (int) $completed_count;
					
				} elseif ( $content['post_type'] === 'tutor_quiz' ) {
					if ( $has_quiz_table ) {
						$quiz_stats = $wpdb->get_row( $wpdb->prepare( "
							SELECT 
								COUNT(attempt_id) as total_attempts,
								COUNT(DISTINCT user_id) as unique_users,
								AVG(earned_marks / total_marks * 100) as avg_score
							FROM {$quiz_attempts_table}
							WHERE quiz_id = %d AND total_marks > 0
						", $content_id ) );

						$item['total_attempts'] = (int) ($quiz_stats->total_attempts ?? 0);
						$item['unique_users']   = (int) ($quiz_stats->unique_users ?? 0);
						$item['avg_score']      = round( (float) ($quiz_stats->avg_score ?? 0), 2 );
						$item['avg_attempts_per_user'] = $item['unique_users'] > 0 
							? round( $item['total_attempts'] / $item['unique_users'], 1 ) 
							: 0;
					} else {
						$item['total_attempts'] = 0;
						$item['unique_users']   = 0;
						$item['avg_score']      = 0;
						$item['avg_attempts_per_user'] = 0;
					}
				}

				$topic_data['contents'][] = $item;
			}

			$insights[] = $topic_data;
		}

		return $insights;
	}
}
