<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

use TutorLMS_Analytics\Date_Range;
use TutorLMS_Analytics\Tutor_Schema;

/**
 * Assignment analytics.
 *
 * Assignments are 'tutor_assignments' posts (assignment → topic → course).
 * Submissions are wp_comments rows with comment_type 'tutor_assignment' and
 * comment_approved 'submitting' (in progress) or 'submitted' (final), verified
 * against 4.0.1. Grading is done by Tutor Pro, which stores earned points in
 * commentmeta 'assignment_mark' and the evaluation time in 'evaluate_time';
 * those metrics degrade to null when Pro is absent.
 *
 * Per-assignment max/pass marks come from the serialized postmeta
 * 'assignment_option'.
 */
class Assignment_Provider {

	private const SUBMISSION_TYPE = 'tutor_assignment';
	private const MARK_META       = 'assignment_mark';
	private const EVAL_META       = 'evaluate_time';

	/**
	 * @param int             $course_id Optional course filter (0 = store-wide).
	 * @param Date_Range|null $range     Date range; defaults to the last 30 days.
	 */
	public function get_assignment_stats( int $course_id = 0, ?Date_Range $range = null ): array {
		global $wpdb;

		$range = $range ?? Date_Range::last_days( 30 );

		$stats = array(
			'available'                => false,
			'total_assignments'        => 0,
			'submissions'              => 0,
			'in_progress'              => 0,
			'graded'                   => null,
			'pending_review'           => null,
			'avg_score_pct'            => null,
			'pass_rate'                => null,
			'grading_turnaround_hours' => null,
			'per_assignment'           => array(),
		);

		$assignment_exists = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1",
				Tutor_Schema::PT_ASSIGNMENT
			)
		);
		if ( ! $assignment_exists ) {
			return $stats;
		}
		$stats['available'] = true;

		// Assignment ids for this scope (assignment → topic → course).
		$assignment_ids = $this->assignment_ids( $course_id );
		$stats['total_assignments'] = count( $assignment_ids );

		if ( empty( $assignment_ids ) ) {
			return $stats;
		}
		$ids_in = implode( ',', array_map( 'intval', $assignment_ids ) );

		// Submissions / in-progress within range.
		$sub_rows = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN comment_approved = 'submitted' THEN 1 ELSE 0 END) AS submissions,
					SUM(CASE WHEN comment_approved = 'submitting' THEN 1 ELSE 0 END) AS in_progress
				FROM {$wpdb->comments}
				WHERE comment_type = %s
					AND comment_post_ID IN ({$ids_in})
					AND comment_date BETWEEN %s AND %s",
				self::SUBMISSION_TYPE,
				$range->start_sql(),
				$range->end_sql()
			),
			ARRAY_A
		);
		$sub_rows            = (array) ( $sub_rows ?? array() );
		$stats['submissions'] = (int) ( $sub_rows['submissions'] ?? 0 );
		$stats['in_progress'] = (int) ( $sub_rows['in_progress'] ?? 0 );

		// Is Pro grading present at all (any 'assignment_mark' commentmeta)?
		$has_grading = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->commentmeta} WHERE meta_key = %s LIMIT 1",
				self::MARK_META
			)
		);

		if ( $has_grading ) {
			$this->add_grading_metrics( $stats, $assignment_ids, $ids_in );
		}

		// Per-assignment breakdown (all-time submissions, top 10 by volume).
		$per_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.comment_post_ID AS assignment_id, COUNT(*) AS submissions
				FROM {$wpdb->comments} c
				WHERE c.comment_type = %s
					AND c.comment_approved = 'submitted'
					AND c.comment_post_ID IN ({$ids_in})
				GROUP BY c.comment_post_ID
				ORDER BY submissions DESC
				LIMIT 10",
				self::SUBMISSION_TYPE
			),
			ARRAY_A
		);
		foreach ( (array) $per_rows as $row ) {
			$row = (array) $row;
			$aid = (int) ( $row['assignment_id'] ?? 0 );

			$graded       = null;
			$avg_score_pct = null;
			if ( $has_grading ) {
				$graded        = $this->graded_count_for( $aid );
				$avg_score_pct = $this->avg_score_for( $aid );
			}

			$stats['per_assignment'][] = array(
				'id'            => $aid,
				'title'         => get_the_title( $aid ),
				'submissions'   => (int) ( $row['submissions'] ?? 0 ),
				'graded'        => $graded,
				'avg_score_pct' => $avg_score_pct,
			);
		}

		return $stats;
	}

	/**
	 * Populate graded / pending_review / avg_score_pct / pass_rate / turnaround.
	 *
	 * @param array<string,mixed> $stats          By reference.
	 * @param int[]               $assignment_ids Scope.
	 * @param string              $ids_in         Comma-separated, pre-escaped int list.
	 */
	private function add_grading_metrics( array &$stats, array $assignment_ids, string $ids_in ): void {
		global $wpdb;

		// Graded / pending across all-time submissions in scope.
		$counts = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN cm.meta_id IS NOT NULL THEN 1 ELSE 0 END) AS graded,
					SUM(CASE WHEN cm.meta_id IS NULL THEN 1 ELSE 0 END) AS pending
				FROM {$wpdb->comments} c
				LEFT JOIN {$wpdb->commentmeta} cm
					ON cm.comment_id = c.comment_ID AND cm.meta_key = %s
				WHERE c.comment_type = %s
					AND c.comment_approved = 'submitted'
					AND c.comment_post_ID IN ({$ids_in})",
				self::MARK_META,
				self::SUBMISSION_TYPE
			),
			ARRAY_A
		);
		$counts                  = (array) ( $counts ?? array() );
		$stats['graded']         = (int) ( $counts['graded'] ?? 0 );
		$stats['pending_review'] = (int) ( $counts['pending'] ?? 0 );

		// Grading turnaround (hours) from 'evaluate_time' meta.
		$turnaround = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG( (em.meta_value + 0) - UNIX_TIMESTAMP(c.comment_date) ) / 3600
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} em
					ON em.comment_id = c.comment_ID AND em.meta_key = %s
				WHERE c.comment_type = %s
					AND c.comment_post_ID IN ({$ids_in})
					AND em.meta_value > 0",
				self::EVAL_META,
				self::SUBMISSION_TYPE
			)
		);
		if ( null !== $turnaround && '' !== $turnaround ) {
			$stats['grading_turnaround_hours'] = round( (float) $turnaround, 1 );
		}

		// Score % and pass rate need per-assignment max/pass marks from postmeta.
		$marks = $this->assignment_marks( $assignment_ids );

		$graded_marks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.comment_post_ID AS assignment_id, ( cm.meta_value + 0 ) AS mark
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm
					ON cm.comment_id = c.comment_ID AND cm.meta_key = %s
				WHERE c.comment_type = %s
					AND c.comment_post_ID IN ({$ids_in})",
				self::MARK_META,
				self::SUBMISSION_TYPE
			),
			ARRAY_A
		);

		$score_sum   = 0.0;
		$score_count = 0;
		$pass_count  = 0;
		$pass_total  = 0;
		foreach ( (array) $graded_marks as $row ) {
			$row = (array) $row;
			$aid = (int) ( $row['assignment_id'] ?? 0 );
			$mk  = (float) ( $row['mark'] ?? 0 );

			$total_mark = $marks[ $aid ]['total_mark'] ?? null;
			$pass_mark  = $marks[ $aid ]['pass_mark'] ?? null;

			if ( null !== $total_mark && $total_mark > 0 ) {
				$score_sum += ( $mk / $total_mark ) * 100;
				++$score_count;
			}
			if ( null !== $pass_mark ) {
				++$pass_total;
				if ( $mk >= $pass_mark ) {
					++$pass_count;
				}
			}
		}

		if ( $score_count > 0 ) {
			$stats['avg_score_pct'] = round( $score_sum / $score_count, 1 );
		}
		if ( $pass_total > 0 ) {
			$stats['pass_rate'] = round( $pass_count / $pass_total * 100, 1 );
		}
	}

	/**
	 * Assignment post ids in scope. When a course is given, walk the
	 * assignment → topic → course hierarchy.
	 *
	 * @return int[]
	 */
	private function assignment_ids( int $course_id ): array {
		global $wpdb;

		if ( $course_id > 0 ) {
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT a.ID
					FROM {$wpdb->posts} a
					INNER JOIN {$wpdb->posts} topic ON topic.ID = a.post_parent
					WHERE a.post_type = %s
						AND a.post_status = 'publish'
						AND topic.post_parent = %d",
					Tutor_Schema::PT_ASSIGNMENT,
					$course_id
				)
			);
		} else {
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
					Tutor_Schema::PT_ASSIGNMENT
				)
			);
		}

		return array_map( 'intval', (array) $rows );
	}

	/**
	 * Parse total_mark / pass_mark from each assignment's serialized
	 * 'assignment_option' postmeta.
	 *
	 * @param int[] $assignment_ids
	 * @return array<int,array{total_mark:?float,pass_mark:?float}>
	 */
	private function assignment_marks( array $assignment_ids ): array {
		$marks = array();
		foreach ( $assignment_ids as $aid ) {
			$aid    = (int) $aid;
			$option = get_post_meta( $aid, 'assignment_option', true );
			if ( is_string( $option ) ) {
				$option = maybe_unserialize( $option );
			}

			$total_mark = null;
			$pass_mark  = null;
			if ( is_array( $option ) ) {
				if ( isset( $option['total_mark'] ) && is_numeric( $option['total_mark'] ) ) {
					$total_mark = (float) $option['total_mark'];
				}
				if ( isset( $option['pass_mark'] ) && is_numeric( $option['pass_mark'] ) ) {
					$pass_mark = (float) $option['pass_mark'];
				}
			}

			$marks[ $aid ] = array(
				'total_mark' => $total_mark,
				'pass_mark'  => $pass_mark,
			);
		}

		return $marks;
	}

	private function graded_count_for( int $assignment_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm
					ON cm.comment_id = c.comment_ID AND cm.meta_key = %s
				WHERE c.comment_type = %s
					AND c.comment_post_ID = %d",
				self::MARK_META,
				self::SUBMISSION_TYPE,
				$assignment_id
			)
		);
	}

	private function avg_score_for( int $assignment_id ): ?float {
		global $wpdb;

		$option = get_post_meta( $assignment_id, 'assignment_option', true );
		if ( is_string( $option ) ) {
			$option = maybe_unserialize( $option );
		}
		$total_mark = ( is_array( $option ) && isset( $option['total_mark'] ) && is_numeric( $option['total_mark'] ) )
			? (float) $option['total_mark']
			: 0.0;
		if ( $total_mark <= 0 ) {
			return null;
		}

		$avg_mark = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG( cm.meta_value + 0 )
				FROM {$wpdb->comments} c
				INNER JOIN {$wpdb->commentmeta} cm
					ON cm.comment_id = c.comment_ID AND cm.meta_key = %s
				WHERE c.comment_type = %s
					AND c.comment_post_ID = %d",
				self::MARK_META,
				self::SUBMISSION_TYPE,
				$assignment_id
			)
		);
		if ( null === $avg_mark || '' === $avg_mark ) {
			return null;
		}

		return round( (float) $avg_mark / $total_mark * 100, 1 );
	}
}
