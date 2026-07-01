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

	public function test_single_course_dashboard_displays_seeded_advanced_statistics(): void {
		$course_id = 123;
		$stats = $this->seedDashboardStats();

		ob_start();
		require TUTORLMS_ANALYTICS_DIR . 'views/admin-dashboard-single.php';
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Seeded Course', $html );

		// Cohort & retention block.
		$this->assertStringContainsString( 'อัตราการเรียนจบตามกลุ่มผู้สมัคร (Cohort)', $html );
		$this->assertStringContainsString( 'อัตราการเข้าเรียนอย่างต่อเนื่องรายสัปดาห์ (Retention)', $html );
		$this->assertStringContainsString( '2026-01', $html );
		$this->assertStringContainsString( '10', $html );
		$this->assertStringContainsString( '4', $html );
		$this->assertStringContainsString( '40%', $html );
		$this->assertStringContainsString( 'Week 1', $html );
		$this->assertStringContainsString( '80%', $html );
		$this->assertStringContainsString( '8 คน', $html );

		// Learner segment block.
		$this->assertStringContainsString( 'Engagement Trend ต่อผู้เรียน', $html );
		$this->assertStringContainsString( 'High Intent but Stuck', $html );
		$this->assertStringContainsString( 'Fast Learners / Power Learners', $html );
		$this->assertStringContainsString( 'Low Engagement but High Score', $html );
		$this->assertStringContainsString( 'Ada Learner', $html );
		$this->assertStringContainsString( 'up 100%', $html );
		$this->assertStringContainsString( 'Ben Stuck', $html );
		$this->assertStringContainsString( 'Progress 20% / 22 events', $html );
		$this->assertStringContainsString( 'Cara Power', $html );
		$this->assertStringContainsString( '92.5% / 4 วัน', $html );
		$this->assertStringContainsString( 'Dee Highscore', $html );
		$this->assertStringContainsString( 'Engagement 23 / Quiz 90%', $html );

		// Content quality block.
		$this->assertStringContainsString( 'Lesson Rewatch / Revisit Rate', $html );
		$this->assertStringContainsString( 'Time-to-Complete per Lesson', $html );
		$this->assertStringContainsString( 'Exit Lesson', $html );
		$this->assertStringContainsString( 'Content Difficulty Index', $html );
		$this->assertStringContainsString( 'Content Engagement Index', $html );
		$this->assertStringContainsString( 'บทเรียน Seeded Revisit', $html );
		$this->assertStringContainsString( 'Revisit 50%', $html );
		$this->assertStringContainsString( '12.5 นาที', $html );
		$this->assertStringContainsString( 'Exit 30%', $html );
		$this->assertStringContainsString( 'Score 60/100', $html );
		$this->assertStringContainsString( 'Score 75/100', $html );

		// Quiz diagnostic block.
		$this->assertStringContainsString( 'Question Difficulty', $html );
		$this->assertStringContainsString( 'Most Common Wrong Answers', $html );
		$this->assertStringContainsString( 'Attempts Before Pass', $html );
		$this->assertStringContainsString( 'Quiz Retry Behavior', $html );
		$this->assertStringContainsString( 'คำถาม Seeded', $html );
		$this->assertStringContainsString( 'ข้อสอบ Seeded', $html );
		$this->assertStringContainsString( 'Correct 40%', $html );
		$this->assertStringContainsString( 'ตัวเลือกที่เข้าใจผิด', $html );
		$this->assertStringContainsString( 'เลือกผิด 6 ครั้ง', $html );
		$this->assertStringContainsString( 'เฉลี่ย 2.5 ครั้ง', $html );
		$this->assertStringContainsString( 'Retry 60%', $html );
	}

	private function seedDashboardStats(): array {
		return array(
			'total_students' => 10,
			'total_completions' => 4,
			'course_performance' => array( array( 'completion_rate' => 40 ) ),
			'quiz_performance' => array( 'avg_score' => 70, 'pass_rate' => 60 ),
			'time_analytics' => array(
				'total_learning_time' => 3600,
				'avg_days_to_complete' => 5,
				'time_per_content' => array(),
				'lesson_revisit_rate' => array( array( 'lesson_id' => 501, 'title' => 'บทเรียน Seeded Revisit', 'unique_learners' => 10, 'total_views' => 15, 'revisit_rate' => 50.0 ) ),
				'time_to_complete_per_lesson' => array( array( 'lesson_id' => 501, 'title' => 'บทเรียน Seeded Revisit', 'avg_minutes' => 12.5, 'sample_count' => 8 ) ),
			),
			'cohort_analytics' => array(
				'completion_by_enrollment_cohort' => array( array( 'cohort' => '2026-01', 'enrolled' => 10, 'completed' => 4, 'completion_rate' => 40.0 ) ),
				'retention_by_week' => array( array( 'week' => 'Week 1', 'active_learners' => 8, 'retention_rate' => 80.0 ) ),
			),
			'engagement' => array(
				'at_risk_count' => 0,
				'scores' => array(),
				'engagement_trends' => array( array( 'user_id' => 10, 'display_name' => 'Ada Learner', 'events_7d' => 8, 'events_prev_7d' => 4, 'trend' => 'up', 'change_pct' => 100.0 ) ),
				'high_intent_stuck' => array( array( 'user_id' => 11, 'display_name' => 'Ben Stuck', 'events_14d' => 22, 'progress_pct' => 20.0, 'last_activity' => '2026-06-30 09:00:00' ) ),
				'power_learners' => array( array( 'user_id' => 12, 'display_name' => 'Cara Power', 'progress_pct' => 100.0, 'quiz_avg_score' => 92.5, 'days_to_complete' => 4 ) ),
				'low_engagement_high_score' => array( array( 'user_id' => 13, 'display_name' => 'Dee Highscore', 'score' => 23, 'quiz_avg_score' => 90.0 ) ),
			),
			'content_gaps' => array(
				'highest_dropoff_lessons' => array(),
				'hardest_quizzes' => array(),
				'lesson_quiz_correlation' => array(),
				'exit_lessons' => array( array( 'lesson_id' => 501, 'title' => 'บทเรียน Seeded Revisit', 'exit_count' => 3, 'exit_rate' => 30.0 ) ),
				'difficulty_index' => array( array( 'content_id' => 501, 'title' => 'บทเรียน Seeded Revisit', 'type' => 'lesson', 'score' => 60, 'signals' => array() ) ),
				'engagement_index' => array( array( 'content_id' => 501, 'title' => 'บทเรียน Seeded Revisit', 'type' => 'lesson', 'score' => 75, 'signals' => array() ) ),
			),
			'quiz_diagnostics' => array(
				'question_difficulty' => array( array( 'question_id' => 701, 'title' => 'คำถาม Seeded', 'quiz_title' => 'ข้อสอบ Seeded', 'correct_rate' => 40.0, 'attempts' => 10 ) ),
				'common_wrong_answers' => array( array( 'question_id' => 701, 'answer' => 'ตัวเลือกที่เข้าใจผิด', 'selected_count' => 6 ) ),
				'attempts_before_pass' => array( array( 'quiz_id' => 601, 'title' => 'ข้อสอบ Seeded', 'avg_attempts_before_pass' => 2.5, 'passed_users' => 4 ) ),
				'retry_behavior' => array( array( 'quiz_id' => 601, 'title' => 'ข้อสอบ Seeded', 'failed_users' => 10, 'retried_users' => 6, 'retry_rate' => 60.0 ) ),
			),
			'alerts' => array(),
			'survival_curve' => array( 'labels' => array(), 'data' => array() ),
			'progress_distribution' => array(),
			'quiz_score_distribution' => array(),
			'pass_fail_ratio' => array(),
			'enrollment_trend' => array( 'labels' => array(), 'data' => array() ),
			'active_students_trend' => array( 'labels' => array(), 'data' => array() ),
			'completion_trend' => array( 'labels' => array(), 'data' => array() ),
			'content_insights' => array(),
			'student_table' => array(),
			'device_analytics' => array( 'device_distribution' => array(), 'browser_distribution' => array(), 'hourly_activity' => array() ),
			'rating_analytics' => array(
				'rating_distribution' => array(),
				'nps_score' => array( 'score' => 0, 'promoters' => 0, 'passives' => 0, 'detractors' => 0, 'total' => 0 ),
				'review_response_rate' => array( 'rate' => 0, 'reviews' => 0, 'completions' => 0 ),
				'rating_trend' => array( 'labels' => array(), 'data' => array() ),
			),
		);
	}
}
