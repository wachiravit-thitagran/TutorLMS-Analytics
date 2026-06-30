<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\Providers\Cohort_Provider;

class CohortProviderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$wpdb->mock_results = array();
		$wpdb->mock_var     = array();
		$wpdb->default_var  = 0;
	}

	public function test_get_cohort_analytics_returns_expected_empty_state_keys(): void {
		$provider = new Cohort_Provider();

		$analytics = $provider->get_cohort_analytics( 123 );

		$this->assertIsArray( $analytics );
		$this->assertArrayHasKey( 'completion_by_enrollment_cohort', $analytics );
		$this->assertArrayHasKey( 'retention_by_week', $analytics );
		$this->assertSame( array(), $analytics['completion_by_enrollment_cohort'] );
		$this->assertSame( array(), $analytics['retention_by_week'] );
	}

	public function test_completion_by_enrollment_cohort_maps_counts_and_rates(): void {
		global $wpdb;
		$wpdb->mock_results['GROUP BY cohort'] = array(
			array( 'cohort' => '2026-01', 'enrolled' => 10, 'completed' => 4 ),
			array( 'cohort' => '2026-02', 'enrolled' => 5, 'completed' => 0 ),
		);

		$provider = new Cohort_Provider();
		$cohorts  = $provider->get_completion_by_enrollment_cohort( 123, 2 );

		$this->assertSame( '2026-01', $cohorts[0]['cohort'] );
		$this->assertSame( 10, $cohorts[0]['enrolled'] );
		$this->assertSame( 4, $cohorts[0]['completed'] );
		$this->assertSame( 40.0, $cohorts[0]['completion_rate'] );
		$this->assertSame( 0.0, $cohorts[1]['completion_rate'] );
	}

	public function test_retention_by_week_maps_active_learners_to_rates(): void {
		global $wpdb;
		$wpdb->mock_var['SHOW TABLES LIKE'] = 'wp_tutorlms_analytics_events';
		$wpdb->mock_var['COUNT(DISTINCT e.post_author)'] = 20;
		$wpdb->mock_results['week_number'] = array(
			array( 'week_number' => 1, 'active_learners' => 16 ),
			array( 'week_number' => 2, 'active_learners' => 10 ),
		);

		$provider  = new Cohort_Provider();
		$retention = $provider->get_retention_by_week( 123, 3 );

		$this->assertSame( 'Week 1', $retention[0]['week'] );
		$this->assertSame( 16, $retention[0]['active_learners'] );
		$this->assertSame( 80.0, $retention[0]['retention_rate'] );
		$this->assertSame( 'Week 3', $retention[2]['week'] );
		$this->assertSame( 0, $retention[2]['active_learners'] );
		$this->assertSame( 0.0, $retention[2]['retention_rate'] );
	}
}
