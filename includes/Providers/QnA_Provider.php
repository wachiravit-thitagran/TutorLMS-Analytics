<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

use TutorLMS_Analytics\Date_Range;
use TutorLMS_Analytics\Tutor_Schema;

/**
 * Q&A analytics built on wp_comments rows with comment_type = 'tutor_q_and_a'
 * (verified against Tutor LMS 4.0.1).
 *
 * A question is any row with comment_parent = 0 regardless of comment_approved
 * (4.0 inserts 'approved'; legacy rows may carry 'waiting_for_answer'), and its
 * replies point back via comment_parent. "Unanswered" means a question with
 * zero child rows.
 */
class QnA_Provider {

	public function get_qna_stats( int $course_id = 0, ?Date_Range $range = null ): array {
		global $wpdb;

		$range = $range ? $range : Date_Range::last_days( 30 );

		$stats = array(
			'available'                => false,
			'total_questions'          => 0,
			'unanswered'               => 0,
			'answered_rate'            => 0.0,
			'avg_first_response_hours' => null,
			'top_courses'              => array(),
			'recent_unanswered'        => array(),
		);

		$available = (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$wpdb->comments} WHERE comment_type = %s LIMIT 1", Tutor_Schema::CT_QNA )
		);
		if ( ! $available ) {
			return $stats;
		}
		$stats['available'] = true;

		$qna_type = Tutor_Schema::CT_QNA; // Trusted class constant, safe to inline.

		$course_where = '';
		if ( $course_id > 0 ) {
			$course_where = $wpdb->prepare( ' AND q.comment_post_ID = %d', $course_id );
		}
		$range_where = $wpdb->prepare(
			' AND q.comment_date >= %s AND q.comment_date <= %s',
			$range->start_sql(),
			$range->end_sql()
		);

		// Questions in range with their reply counts, aggregated once.
		$totals = $wpdb->get_results(
			"
			SELECT COUNT(*) AS total_questions,
				SUM(CASE WHEN t.reply_count = 0 THEN 1 ELSE 0 END) AS unanswered
			FROM (
				SELECT q.comment_ID, COUNT(r.comment_ID) AS reply_count
				FROM {$wpdb->comments} q
				LEFT JOIN {$wpdb->comments} r
					ON r.comment_parent = q.comment_ID AND r.comment_type = '{$qna_type}'
				WHERE q.comment_type = '{$qna_type}'
					AND q.comment_parent = 0
					{$course_where}
					{$range_where}
				GROUP BY q.comment_ID
			) t
			",
			ARRAY_A
		);
		$row = ( is_array( $totals ) && isset( $totals[0] ) ) ? (array) $totals[0] : array();

		$total      = isset( $row['total_questions'] ) ? (int) $row['total_questions'] : 0;
		$unanswered = isset( $row['unanswered'] ) ? (int) $row['unanswered'] : 0;

		$stats['total_questions'] = $total;
		$stats['unanswered']      = $unanswered;
		$stats['answered_rate']   = $total > 0 ? round( ( ( $total - $unanswered ) / $total ) * 100, 1 ) : 0.0;

		// Average time to the first reply, over answered questions in range.
		$avg_hours = $wpdb->get_var(
			"
			SELECT AVG(TIMESTAMPDIFF(SECOND, q.comment_date, fr.first_reply_date)) / 3600
			FROM {$wpdb->comments} q
			INNER JOIN (
				SELECT comment_parent, MIN(comment_date) AS first_reply_date
				FROM {$wpdb->comments}
				WHERE comment_type = '{$qna_type}' AND comment_parent > 0
				GROUP BY comment_parent
			) fr ON fr.comment_parent = q.comment_ID
			WHERE q.comment_type = '{$qna_type}'
				AND q.comment_parent = 0
				{$course_where}
				{$range_where}
			"
		);
		if ( null !== $avg_hours && '' !== $avg_hours ) {
			$stats['avg_first_response_hours'] = round( (float) $avg_hours, 1 );
		}

		// Courses generating the most questions (global view only).
		if ( 0 === $course_id ) {
			$rows = $wpdb->get_results(
				"
				SELECT t.course_id,
					COUNT(*) AS questions,
					SUM(CASE WHEN t.reply_count = 0 THEN 1 ELSE 0 END) AS unanswered
				FROM (
					SELECT q.comment_ID, q.comment_post_ID AS course_id, COUNT(r.comment_ID) AS reply_count
					FROM {$wpdb->comments} q
					LEFT JOIN {$wpdb->comments} r
						ON r.comment_parent = q.comment_ID AND r.comment_type = '{$qna_type}'
					WHERE q.comment_type = '{$qna_type}'
						AND q.comment_parent = 0
						{$range_where}
					GROUP BY q.comment_ID, q.comment_post_ID
				) t
				GROUP BY t.course_id
				ORDER BY questions DESC
				LIMIT 5
				",
				ARRAY_A
			);
			foreach ( (array) $rows as $r ) {
				$r   = (array) $r;
				$cid = (int) ( isset( $r['course_id'] ) ? $r['course_id'] : 0 );

				$stats['top_courses'][] = array(
					'id'         => $cid,
					'title'      => get_the_title( $cid ),
					'questions'  => (int) ( isset( $r['questions'] ) ? $r['questions'] : 0 ),
					'unanswered' => (int) ( isset( $r['unanswered'] ) ? $r['unanswered'] : 0 ),
				);
			}
		}

		// Newest questions still waiting for a reply.
		$rows = $wpdb->get_results(
			"
			SELECT q.comment_ID, q.comment_content, q.comment_author, q.comment_date, q.comment_post_ID
			FROM {$wpdb->comments} q
			LEFT JOIN {$wpdb->comments} r
				ON r.comment_parent = q.comment_ID AND r.comment_type = '{$qna_type}'
			WHERE q.comment_type = '{$qna_type}'
				AND q.comment_parent = 0
				AND r.comment_ID IS NULL
				{$course_where}
				{$range_where}
			ORDER BY q.comment_date DESC
			LIMIT 5
			",
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			$r = (array) $r;

			$stats['recent_unanswered'][] = array(
				'id'        => (int) ( isset( $r['comment_ID'] ) ? $r['comment_ID'] : 0 ),
				'excerpt'   => $this->excerpt( (string) ( isset( $r['comment_content'] ) ? $r['comment_content'] : '' ) ),
				'author'    => (string) ( isset( $r['comment_author'] ) ? $r['comment_author'] : '' ),
				'date'      => (string) ( isset( $r['comment_date'] ) ? $r['comment_date'] : '' ),
				'course_id' => (int) ( isset( $r['comment_post_ID'] ) ? $r['comment_post_ID'] : 0 ),
			);
		}

		return $stats;
	}

	/** Plain-text excerpt capped at $length characters (multibyte-safe). */
	private function excerpt( string $text, int $length = 80 ): string {
		$text = trim( strip_tags( $text ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $length );
		}
		return substr( $text, 0, $length );
	}
}
