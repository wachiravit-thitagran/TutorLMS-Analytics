<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

/**
 * Central orchestrator that maps a dashboard "section" to the providers it
 * needs, applies the active date range, and caches the result.
 *
 * The admin page renders only the initial section server-side; every other
 * section is fetched on demand through the REST API, so heavy providers no
 * longer all run on a single page load.
 */
class Analytics_Service {

	/** Sections available on the store-wide dashboard. */
	public const GLOBAL_SECTIONS = array( 'overview', 'courses', 'monetization', 'community' );

	/** Sections available on a single-course dashboard. */
	public const COURSE_SECTIONS = array( 'insights', 'teaching', 'content', 'assessment', 'community', 'learners', 'action' );

	public function is_valid_section( int $course_id, string $section ): bool {
		$valid = $course_id > 0 ? self::COURSE_SECTIONS : self::GLOBAL_SECTIONS;
		return in_array( $section, $valid, true );
	}

	/**
	 * Cached section payload.
	 *
	 * @return array<string,mixed>
	 */
	public function get_section( string $section, int $course_id, Date_Range $range ): array {
		if ( ! $this->is_valid_section( $course_id, $section ) ) {
			return array();
		}

		$key = 'section|' . $section . '|c' . $course_id . '|' . $range->key();

		return Stats_Cache::remember(
			$key,
			function () use ( $section, $course_id, $range ) {
				return $this->build_section( $section, $course_id, $range );
			}
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function build_section( string $section, int $course_id, Date_Range $range ): array {
		switch ( $section ) {
			case 'overview':
				return $this->overview_section( $course_id, $range );
			case 'courses':
				return $this->courses_section();
			case 'monetization':
				return $this->monetization_section( $course_id, $range );
			case 'community':
				return $this->community_section( $course_id, $range );
			case 'insights':
				return $this->insights_section( $course_id, $range );
			case 'teaching':
				return $this->teaching_section( $course_id, $range );
			case 'content':
				return $this->content_section( $course_id, $range );
			case 'assessment':
				return $this->assessment_section( $course_id, $range );
			case 'learners':
				return $this->learners_section( $course_id );
			case 'action':
				return $this->action_section( $course_id );
		}

		return array();
	}

	// ---- Store-wide sections ----

	private function overview_section( int $course_id, Date_Range $range ): array {
		$data     = new Data_Provider();
		$stats    = $data->get_all_stats( $course_id );
		$device   = new Providers\Device_Analytics_Provider();
		$revenue  = new Providers\Monetization_Provider();

		$stats['device_analytics'] = $device->get_device_analytics( $course_id );
		$stats['monetization']     = $revenue->get_monetization_stats( $course_id, $range );
		$stats['kpis']             = $this->overview_kpis( $course_id, $range );

		return $stats;
	}

	private function courses_section(): array {
		$provider = new Providers\Course_Performance_Provider();
		return array( 'course_performance' => $provider->get_course_table() );
	}

	private function monetization_section( int $course_id, Date_Range $range ): array {
		$money = new Providers\Monetization_Provider();
		$subs  = new Providers\Subscription_Provider();
		$bund  = new Providers\Bundle_Provider();

		return array(
			'monetization' => $money->get_monetization_stats( $course_id, $range ),
			'subscription' => $subs->get_subscription_stats( $range ),
			'bundle'       => $bund->get_bundle_stats( $course_id, $range ),
		);
	}

	private function community_section( int $course_id, Date_Range $range ): array {
		$qna   = new Providers\QnA_Provider();
		$cert  = new Providers\Certificate_Provider();
		$live  = new Providers\Live_Lesson_Provider();
		$qtype = new Providers\Quiz_Type_Provider();
		$grade = new Providers\Gradebook_Provider();

		return array(
			'qna'          => $qna->get_qna_stats( $course_id, $range ),
			'certificates' => $cert->get_certificate_stats( $course_id, $range ),
			'live_lessons' => $live->get_live_lesson_stats( $course_id, $range ),
			'quiz_types'   => $qtype->get_question_type_stats( $course_id ),
			'gradebook'    => $grade->get_gradebook_stats( $course_id ),
		);
	}

	// ---- Single-course sections ----

	private function insights_section( int $course_id, Date_Range $range ): array {
		$data     = new Data_Provider();
		$funnel   = new Providers\Funnel_Provider();
		$survival = new Providers\Survival_Provider();
		$quiz     = new Providers\Quiz_Provider();
		$time     = new Providers\Time_Analytics_Provider();
		$perf     = new Providers\Course_Performance_Provider();

		$stats                          = $data->get_all_stats( $course_id );
		$stats['course_performance']    = $perf->get_course_table( $course_id );
		$stats['progress_distribution'] = $funnel->get_progress_distribution( $course_id );
		$stats['survival_curve']        = $survival->get_survival_curve( $course_id );
		$stats['quiz_performance']      = $quiz->get_quiz_performance( $course_id );
		$stats['quiz_score_distribution'] = $quiz->get_quiz_score_distribution( $course_id );
		$stats['pass_fail_ratio']       = $quiz->get_pass_fail_ratio( $course_id );
		$stats['time_analytics']        = $time->get_time_analytics( $course_id );
		$stats['certificates']          = ( new Providers\Certificate_Provider() )->get_certificate_stats( $course_id, $range );
		$stats['kpis']                  = $this->overview_kpis( $course_id, $range );

		return $stats;
	}

	private function teaching_section( int $course_id, Date_Range $range ): array {
		$time   = new Providers\Time_Analytics_Provider();
		$device = new Providers\Device_Analytics_Provider();
		$rating = new Providers\Rating_Analytics_Provider();
		$live   = new Providers\Live_Lesson_Provider();

		return array(
			'time_analytics'   => $time->get_time_analytics( $course_id ),
			'device_analytics' => $device->get_device_analytics( $course_id ),
			'rating_analytics' => $rating->get_rating_analytics( $course_id ),
			'live_lessons'     => $live->get_live_lesson_stats( $course_id, $range ),
		);
	}

	private function content_section( int $course_id, Date_Range $range ): array {
		$data    = new Data_Provider();
		$gap     = new Providers\Content_Gap_Provider();
		$time    = new Providers\Time_Analytics_Provider();

		return array(
			'content_insights' => $data->get_all_stats( $course_id )['content_insights'] ?? array(),
			'content_gaps'     => $gap->get_content_gaps( $course_id ),
			'time_analytics'   => $time->get_time_analytics( $course_id ),
		);
	}

	private function assessment_section( int $course_id, Date_Range $range ): array {
		$quiz   = new Providers\Quiz_Provider();
		$qtype  = new Providers\Quiz_Type_Provider();
		$assign = new Providers\Assignment_Provider();
		$grade  = new Providers\Gradebook_Provider();

		return array(
			'quiz_diagnostics' => $quiz->get_quiz_diagnostics( $course_id ),
			'quiz_types'       => $qtype->get_question_type_stats( $course_id ),
			'assignments'      => $assign->get_assignment_stats( $course_id, $range ),
			'gradebook'        => $grade->get_gradebook_stats( $course_id ),
		);
	}

	private function learners_section( int $course_id ): array {
		$student    = new Providers\Student_Provider();
		$engagement = new Providers\Engagement_Provider();
		$matrix     = new Providers\Lesson_Matrix_Provider();

		return array(
			'student_table' => $student->get_student_table( $course_id ),
			'engagement'    => $engagement->get_engagement_data( $course_id ),
			'lesson_matrix' => $matrix->get_lesson_matrix( $course_id ),
		);
	}

	private function action_section( int $course_id ): array {
		$alerts     = new Providers\Alerts_Provider();
		$engagement = new Providers\Engagement_Provider();
		$qna        = new Providers\QnA_Provider();

		return array(
			'alerts'     => $alerts->get_alerts( $course_id ),
			'engagement' => $engagement->get_engagement_data( $course_id ),
			'qna'        => $qna->get_qna_stats( $course_id ),
		);
	}

	// ---- KPI cards with period-over-period deltas ----

	/**
	 * Headline KPIs plus the % change vs the immediately preceding period.
	 *
	 * @return array<string,array{value:float|int,previous:float|int,delta_pct:?float,format:string}>
	 */
	public function overview_kpis( int $course_id, Date_Range $range ): array {
		$prev = $range->previous_period();

		$enrolled_now  = $this->count_enrollments( $course_id, $range );
		$enrolled_prev = $this->count_enrollments( $course_id, $prev );

		$completed_now  = $this->count_completions( $course_id, $range );
		$completed_prev = $this->count_completions( $course_id, $prev );

		$money      = new Providers\Monetization_Provider();
		$rev_now    = $money->get_monetization_stats( $course_id, $range );
		$rev_prev   = $money->get_monetization_stats( $course_id, $prev );

		$rating   = new Providers\Rating_Analytics_Provider();
		$avg_rate = $this->average_rating( $rating->get_rating_distribution( $course_id ) );

		return array(
			'new_enrollments' => $this->kpi( $enrolled_now, $enrolled_prev, 'int' ),
			'completions'     => $this->kpi( $completed_now, $completed_prev, 'int' ),
			'net_revenue'     => $this->kpi(
				(float) ( $rev_now['net_revenue'] ?? 0 ),
				(float) ( $rev_prev['net_revenue'] ?? 0 ),
				'money'
			),
			'avg_rating'      => $this->kpi( $avg_rate, $avg_rate, 'rating' ),
		);
	}

	/**
	 * @param float|int $value
	 * @param float|int $previous
	 * @return array{value:float|int,previous:float|int,delta_pct:?float,format:string}
	 */
	private function kpi( $value, $previous, string $format ): array {
		$delta = null;
		if ( $previous > 0 ) {
			$delta = round( ( ( $value - $previous ) / $previous ) * 100, 1 );
		} elseif ( $value > 0 ) {
			$delta = null; // No baseline to compare against.
		} else {
			$delta = 0.0;
		}

		return array(
			'value'     => $value,
			'previous'  => $previous,
			'delta_pct' => $delta,
			'format'    => $format,
		);
	}

	/**
	 * Weighted average rating from a distribution keyed '1★'..'5★'.
	 */
	private function average_rating( array $distribution ): float {
		$sum   = 0.0;
		$count = 0;
		foreach ( $distribution as $label => $n ) {
			$stars = (int) $label; // "4★" casts to 4.
			$n     = (int) $n;
			if ( $stars >= 1 && $stars <= 5 ) {
				$sum   += $stars * $n;
				$count += $n;
			}
		}

		return $count > 0 ? round( $sum / $count, 1 ) : 0.0;
	}

	private function count_enrollments( int $course_id, Date_Range $range ): int {
		global $wpdb;

		$where = "post_type = 'tutor_enrolled' AND post_status IN ('completed', 'processing', 'publish')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( ' AND post_parent = %d', $course_id );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where} AND post_date BETWEEN %s AND %s",
				$range->start_sql(),
				$range->end_sql()
			)
		);
	}

	private function count_completions( int $course_id, Date_Range $range ): int {
		global $wpdb;

		$where = "comment_type = 'course_completed' AND comment_approved IN ('approved', '1')";
		if ( $course_id > 0 ) {
			$where .= $wpdb->prepare( ' AND comment_post_ID = %d', $course_id );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE {$where} AND comment_date BETWEEN %s AND %s",
				$range->start_sql(),
				$range->end_sql()
			)
		);
	}
}
