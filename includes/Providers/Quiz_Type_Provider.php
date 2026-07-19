<?php
declare(strict_types=1);

namespace TutorLMS_Analytics\Providers;

use TutorLMS_Analytics\Tutor_Schema;

/**
 * Quiz question-type analytics, including adoption of the five new interactive
 * question types shipped in Tutor LMS 4.0 (draw_image, pin_image, coordinates,
 * scale, puzzle — identifiers verified in 4.0.1 source).
 *
 * Reads {prefix}tutor_quiz_questions and {prefix}tutor_quiz_attempt_answers.
 */
class Quiz_Type_Provider {

	/**
	 * @param int $course_id When > 0, restricts to quizzes in that course
	 *                       (quiz post_parent = topic, topic post_parent = course).
	 */
	public function get_question_type_stats( int $course_id = 0 ): array {
		global $wpdb;

		$stats = array(
			'available'       => false,
			'types'           => array(),
			'new_v4_adoption' => array(
				'new_type_questions' => 0,
				'total_questions'    => 0,
				'pct'                => 0.0,
			),
		);

		$questions_table = Tutor_Schema::quiz_questions_table();
		if ( ! Tutor_Schema::table_exists( $questions_table ) ) {
			return $stats;
		}
		$stats['available'] = true;

		$answers_table = Tutor_Schema::quiz_attempt_answers_table();
		$has_answers   = Tutor_Schema::table_exists( $answers_table );

		// Restrict to a course's quizzes via the post hierarchy when requested.
		$course_join  = '';
		$course_where = '';
		if ( $course_id > 0 ) {
			$course_join  = "INNER JOIN {$wpdb->posts} quiz ON quiz.ID = q.quiz_id
				INNER JOIN {$wpdb->posts} topic ON topic.ID = quiz.post_parent";
			$course_where = $wpdb->prepare( ' AND topic.post_parent = %d', $course_id );
		}

		// Per-type question counts.
		$type_rows = $wpdb->get_results(
			"SELECT q.question_type AS question_type, COUNT(*) AS questions
			FROM {$questions_table} q
			{$course_join}
			WHERE 1 = 1
				{$course_where}
			GROUP BY q.question_type
			ORDER BY questions DESC",
			ARRAY_A
		);

		// Per-type answer stats (correct rate + achieved-mark %) joined on question ids.
		$answer_stats = array();
		if ( $has_answers ) {
			$ans_rows = $wpdb->get_results(
				"SELECT q.question_type AS question_type,
						COUNT(*) AS answers,
						AVG(a.is_correct) * 100 AS correct_rate,
						AVG(CASE WHEN a.question_mark > 0 THEN a.achieved_mark / a.question_mark * 100 END) AS avg_mark_pct
				FROM {$answers_table} a
				INNER JOIN {$questions_table} q ON q.question_id = a.question_id
				{$course_join}
				WHERE 1 = 1
					{$course_where}
				GROUP BY q.question_type",
				ARRAY_A
			);
			foreach ( (array) $ans_rows as $row ) {
				$row  = (array) $row;
				$type = (string) ( $row['question_type'] ?? '' );
				$answer_stats[ $type ] = array(
					'answers'      => (int) ( $row['answers'] ?? 0 ),
					'correct_rate' => isset( $row['correct_rate'] ) && null !== $row['correct_rate'] ? round( (float) $row['correct_rate'], 1 ) : null,
					'avg_mark_pct' => isset( $row['avg_mark_pct'] ) && null !== $row['avg_mark_pct'] ? round( (float) $row['avg_mark_pct'], 1 ) : null,
				);
			}
		}

		$new_types         = Tutor_Schema::new_v4_question_types();
		$labels            = $this->type_labels();
		$new_type_count    = 0;
		$total_count       = 0;

		foreach ( (array) $type_rows as $row ) {
			$row       = (array) $row;
			$type      = (string) ( $row['question_type'] ?? '' );
			$questions = (int) ( $row['questions'] ?? 0 );
			$is_new    = in_array( $type, $new_types, true );

			$total_count += $questions;
			if ( $is_new ) {
				$new_type_count += $questions;
			}

			$stats['types'][] = array(
				'type'         => $type,
				'label'        => $labels[ $type ] ?? $type,
				'questions'    => $questions,
				'answers'      => $answer_stats[ $type ]['answers'] ?? 0,
				'correct_rate' => $answer_stats[ $type ]['correct_rate'] ?? null,
				'avg_mark_pct' => $answer_stats[ $type ]['avg_mark_pct'] ?? null,
				'is_new_v4'    => $is_new,
			);
		}

		$stats['new_v4_adoption']['new_type_questions'] = $new_type_count;
		$stats['new_v4_adoption']['total_questions']    = $total_count;
		$stats['new_v4_adoption']['pct']                = $total_count > 0
			? round( $new_type_count / $total_count * 100, 1 )
			: 0.0;

		return $stats;
	}

	/**
	 * Thai display labels keyed by question_type.
	 *
	 * @return array<string,string>
	 */
	private function type_labels(): array {
		return array(
			'true_false'        => __( 'ถูก/ผิด', 'tutorlms-analytics' ),
			'single_choice'     => __( 'เลือกตอบข้อเดียว', 'tutorlms-analytics' ),
			'multiple_choice'   => __( 'เลือกหลายคำตอบ', 'tutorlms-analytics' ),
			'open_ended'        => __( 'อัตนัย', 'tutorlms-analytics' ),
			'fill_in_the_blank' => __( 'เติมคำในช่องว่าง', 'tutorlms-analytics' ),
			'short_answer'      => __( 'ตอบสั้น', 'tutorlms-analytics' ),
			'matching'          => __( 'จับคู่', 'tutorlms-analytics' ),
			'image_matching'    => __( 'จับคู่รูปภาพ', 'tutorlms-analytics' ),
			'image_answering'   => __( 'ตอบจากรูปภาพ', 'tutorlms-analytics' ),
			'ordering'          => __( 'เรียงลำดับ', 'tutorlms-analytics' ),
			'draw_image'        => __( 'วาดบนรูปภาพ (ใหม่ 4.0)', 'tutorlms-analytics' ),
			'pin_image'         => __( 'ปักหมุดบนรูปภาพ (ใหม่ 4.0)', 'tutorlms-analytics' ),
			'coordinates'       => __( 'กราฟ/พิกัด (ใหม่ 4.0)', 'tutorlms-analytics' ),
			'scale'             => __( 'สเกลช่วงค่า (ใหม่ 4.0)', 'tutorlms-analytics' ),
			'puzzle'            => __( 'พัซเซิล (ใหม่ 4.0)', 'tutorlms-analytics' ),
		);
	}
}
