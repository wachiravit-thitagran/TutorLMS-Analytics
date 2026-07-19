<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\Providers\Cohort_Provider;
use TutorLMS_Analytics\Providers\Content_Gap_Provider;
use TutorLMS_Analytics\Providers\Engagement_Provider;
use TutorLMS_Analytics\Providers\Quiz_Provider;
use TutorLMS_Analytics\Providers\Time_Analytics_Provider;

class AdvancedAnalyticsIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->mock_results = array();
		$wpdb->mock_var     = array();
		$wpdb->default_var  = 0;
		$GLOBALS['mock_post_titles'] = array(
			123 => 'Seeded Course',
			501 => 'บทเรียน Seeded Revisit',
			601 => 'ข้อสอบ Seeded',
			701 => 'คำถาม Seeded',
		);
		$GLOBALS['mock_users'] = array(
			10 => (object) array( 'ID' => 10, 'display_name' => 'Ada Learner' ),
			11 => (object) array( 'ID' => 11, 'display_name' => 'Ben Stuck' ),
			12 => (object) array( 'ID' => 12, 'display_name' => 'Cara Power' ),
			13 => (object) array( 'ID' => 13, 'display_name' => 'Dee Highscore' ),
		);
		$GLOBALS['mock_user_meta'] = array();
	}

	public function test_seeded_provider_data_calculates_new_statistics_correctly(): void {
		global $wpdb;
		$wpdb->mock_var['SHOW TABLES LIKE'] = 'wp_tutorlms_analytics_events';
		$wpdb->mock_var['COUNT(DISTINCT e.post_author)'] = 20;
		$wpdb->mock_results['GROUP BY cohort'] = array(
			array( 'cohort' => '2026-01', 'enrolled' => 10, 'completed' => 4 ),
			array( 'cohort' => '2026-02', 'enrolled' => 8, 'completed' => 6 ),
		);
		$wpdb->mock_results['week_number'] = array(
			array( 'week_number' => 1, 'active_learners' => 16 ),
			array( 'week_number' => 2, 'active_learners' => 10 ),
		);
		$wpdb->mock_results['total_views'] = array(
			array( 'lesson_id' => 501, 'total_views' => 15, 'unique_learners' => 10 ),
		);
		$wpdb->mock_results['avg_minutes'] = array(
			array( 'lesson_id' => 501, 'avg_minutes' => 12.5, 'sample_count' => 8 ),
		);
		$wpdb->mock_results['events_7d'] = array(
			array( 'user_id' => 10, 'events_7d' => 8, 'events_prev_7d' => 4, 'last_activity' => '2026-06-30 10:00:00' ),
		);

		$cohort_provider    = new Cohort_Provider();
		$time_provider      = new Time_Analytics_Provider();
		$engagement_provider = new Engagement_Provider();

		$cohorts = $cohort_provider->get_completion_by_enrollment_cohort( 123, 2 );
		$retention = $cohort_provider->get_retention_by_week( 123, 2 );
		$revisit = $time_provider->get_lesson_revisit_rate( 123 );
		$time_to_complete = $time_provider->get_time_to_complete_per_lesson( 123 );
		$trends = $engagement_provider->get_engagement_trends( 123 );

		$this->assertSame( 40.0, $cohorts[0]['completion_rate'] );
		$this->assertSame( 75.0, $cohorts[1]['completion_rate'] );
		$this->assertSame( 80.0, $retention[0]['retention_rate'] );
		$this->assertSame( 50.0, $retention[1]['retention_rate'] );
		$this->assertSame( 50.0, $revisit[0]['revisit_rate'] );
		$this->assertSame( 12.5, $time_to_complete[0]['avg_minutes'] );
		$this->assertSame( 'Ada Learner', $trends[0]['display_name'] );
		$this->assertSame( 'up', $trends[0]['trend'] );
		$this->assertSame( 100.0, $trends[0]['change_pct'] );
	}

	public function test_seeded_engagement_segments_calculate_all_segment_statistics_correctly(): void {
		global $wpdb;
		$wpdb->mock_var["SHOW TABLES LIKE 'wp_tutorlms_analytics_events'"] = 'wp_tutorlms_analytics_events';
		$wpdb->mock_var["SHOW TABLES LIKE 'wp_tutor_quiz_attempts'"] = 'wp_tutor_quiz_attempts';
		$GLOBALS['mock_user_meta'][11]['_tutor_course_progress'] = array( 123 => array( 'completed_lesson' => 2, 'total_lesson' => 10 ) );
		$wpdb->mock_results['events_14d'] = array(
			array( 'user_id' => 11, 'events_14d' => 22, 'last_activity' => '2026-06-30 09:00:00' ),
		);
		$wpdb->mock_results['days_to_complete'] = array(
			array( 'user_id' => 12, 'days_to_complete' => 4, 'quiz_avg_score' => 92.5 ),
		);
		$wpdb->mock_results['SELECT DISTINCT p.post_author'] = array(
			array( 'user_id' => 13 ),
		);
		$wpdb->mock_var['COUNT(*) FROM wp_tutorlms_analytics_events'] = 0;
		$wpdb->mock_var['COUNT(DISTINCT DATE(created_at))'] = 0;
		$wpdb->mock_var['AVG(q.earned_marks / q.total_marks * 100)'] = 90;
		$wpdb->mock_var['AVG(earned_marks / total_marks * 100)'] = 90;

		$provider = new Engagement_Provider();
		$stuck = $provider->get_high_intent_stuck_students( 123 );
		$power = $provider->get_power_learners( 123 );
		$low_high = $provider->get_low_engagement_high_score_students( 123 );

		$this->assertSame( 'Ben Stuck', $stuck[0]['display_name'] );
		$this->assertSame( 22, $stuck[0]['events_14d'] );
		$this->assertSame( 20.0, $stuck[0]['progress_pct'] );
		$this->assertSame( 'Cara Power', $power[0]['display_name'] );
		$this->assertSame( 92.5, $power[0]['quiz_avg_score'] );
		$this->assertSame( 4, $power[0]['days_to_complete'] );
		$this->assertSame( 'Dee Highscore', $low_high[0]['display_name'] );
		$this->assertSame( 90.0, $low_high[0]['quiz_avg_score'] );
	}

	public function test_seeded_exit_lesson_calculates_exit_rate_correctly(): void {
		global $wpdb;
		$wpdb->mock_var["SHOW TABLES LIKE 'wp_tutorlms_analytics_events'"] = 'wp_tutorlms_analytics_events';
		$wpdb->mock_var['COUNT(DISTINCT user_id)'] = 10;
		$wpdb->mock_results['last_events.lesson_id'] = array(
			array( 'lesson_id' => 501, 'exit_count' => 3 ),
		);

		$provider = new Content_Gap_Provider();
		$exit_lessons = $provider->get_exit_lessons( 123 );

		$this->assertSame( 501, $exit_lessons[0]['lesson_id'] );
		$this->assertSame( 'บทเรียน Seeded Revisit', $exit_lessons[0]['title'] );
		$this->assertSame( 3, $exit_lessons[0]['exit_count'] );
		$this->assertSame( 30.0, $exit_lessons[0]['exit_rate'] );
	}

	public function test_seeded_quiz_diagnostics_calculates_assessment_statistics_correctly(): void {
		global $wpdb;
		$wpdb->mock_var["SHOW TABLES LIKE 'wp_tutor_quiz_attempts'"] = 'wp_tutor_quiz_attempts';
		$wpdb->mock_var["SHOW TABLES LIKE 'wp_tutor_quiz_attempt_answers'"] = 'wp_tutor_quiz_attempt_answers';
		$wpdb->mock_results['correct_count'] = array(
			array( 'question_id' => 701, 'quiz_id' => 601, 'attempts' => 10, 'correct_count' => 4 ),
		);
		$wpdb->mock_results['selected_count'] = array(
			array( 'question_id' => 701, 'answer' => 'ตัวเลือกที่เข้าใจผิด', 'selected_count' => 6 ),
		);
		$wpdb->mock_results['avg_attempts_before_pass'] = array(
			array( 'quiz_id' => 601, 'avg_attempts_before_pass' => 2.5, 'passed_users' => 4 ),
		);
		$wpdb->mock_results['retried_users'] = array(
			array( 'quiz_id' => 601, 'failed_users' => 10, 'retried_users' => 6 ),
		);

		$provider = new Quiz_Provider();
		$diagnostics = $provider->get_quiz_diagnostics( 123 );

		$this->assertSame( 40.0, $diagnostics['question_difficulty'][0]['correct_rate'] );
		$this->assertSame( 'คำถาม Seeded', $diagnostics['question_difficulty'][0]['title'] );
		$this->assertSame( 10, $diagnostics['question_difficulty'][0]['attempts'] );
		$this->assertSame( 'ตัวเลือกที่เข้าใจผิด', $diagnostics['common_wrong_answers'][0]['answer'] );
		$this->assertSame( 6, $diagnostics['common_wrong_answers'][0]['selected_count'] );
		$this->assertSame( 'ข้อสอบ Seeded', $diagnostics['attempts_before_pass'][0]['title'] );
		$this->assertSame( 2.5, $diagnostics['attempts_before_pass'][0]['avg_attempts_before_pass'] );
		$this->assertSame( 4, $diagnostics['attempts_before_pass'][0]['passed_users'] );
		$this->assertSame( 'ข้อสอบ Seeded', $diagnostics['retry_behavior'][0]['title'] );
		$this->assertSame( 10, $diagnostics['retry_behavior'][0]['failed_users'] );
		$this->assertSame( 6, $diagnostics['retry_behavior'][0]['retried_users'] );
		$this->assertSame( 60.0, $diagnostics['retry_behavior'][0]['retry_rate'] );
	}

	public function test_single_course_dashboard_shell_renders_tabs_and_initial_payload(): void {
		// The dashboard is now a JS-driven shell: it renders the tab structure
		// and embeds the initial section payload as JSON; per-tab data is fetched
		// lazily via REST. Assert the shell contract rather than server-rendered
		// data rows.
		$course_id       = 123;
		$course_title    = 'Seeded Course';
		$initial_section = 'insights';
		$initial_data    = array( 'total_students' => 10, 'kpis' => array() );
		$range           = \TutorLMS_Analytics\Date_Range::last_days( 30 );

		ob_start();
		require TUTORLMS_ANALYTICS_DIR . 'views/admin-dashboard-single.php';
		$html = ob_get_clean();

		// Course header.
		$this->assertStringContainsString( 'Seeded Course', $html );

		// Accessible tab structure.
		$this->assertStringContainsString( 'role="tablist"', $html );
		$this->assertStringContainsString( 'role="tabpanel"', $html );

		// All seven consolidated sections present as tabs + panels.
		foreach ( array( 'insights', 'teaching', 'content', 'assessment', 'community', 'learners', 'action' ) as $section ) {
			$this->assertStringContainsString( 'data-section="' . $section . '"', $html );
			$this->assertStringContainsString( 'id="panel-' . $section . '"', $html );
		}

		// The initial tab is flagged for the JS controller.
		$this->assertStringContainsString( 'data-initial="1"', $html );

		// Initial payload embedded for a zero-fetch first paint.
		$this->assertStringContainsString( 'TutorLMSAnalyticsInitial', $html );
		$this->assertStringContainsString( '"total_students":10', $html );

		// Date-range picker present.
		$this->assertStringContainsString( 'id="tla-range-form"', $html );
	}

	public function test_global_dashboard_shell_renders_four_sections(): void {
		$initial_section = 'overview';
		$initial_data    = array( 'total_students' => 5 );
		$courses         = array();
		$range           = \TutorLMS_Analytics\Date_Range::last_days( 30 );

		ob_start();
		require TUTORLMS_ANALYTICS_DIR . 'views/admin-dashboard-global.php';
		$html = ob_get_clean();

		foreach ( array( 'overview', 'courses', 'monetization', 'community' ) as $section ) {
			$this->assertStringContainsString( 'data-section="' . $section . '"', $html );
		}
		$this->assertStringContainsString( 'TutorLMSAnalyticsInitial', $html );
	}
}
