<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

class Export_Handler {

	public function register(): void {
		add_action( 'admin_post_tutorlms_export_revenue', array( $this, 'export_revenue' ) );
		add_action( 'admin_post_tutorlms_export_courses', array( $this, 'export_courses' ) );
		add_action( 'admin_post_tutorlms_export_students', array( $this, 'export_students' ) );
	}

	private function check_permissions(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'tutor_instructor' ) ) {
			wp_die( 'Unauthorized access.' );
		}
	}

	public function export_revenue(): void {
		$this->check_permissions();
		
		$provider = new Providers\Revenue_Provider();
		$stats    = $provider->get_revenue_stats(); // Global stats for export
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tutorlms-revenue-30days.csv' );
		
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Date', 'Net Revenue (THB)' ) );
		
		foreach ( $stats['trend']['labels'] as $index => $date ) {
			fputcsv( $output, array( $date, $stats['trend']['data'][$index] ) );
		}
		fclose( $output );
		exit;
	}

	public function export_courses(): void {
		$this->check_permissions();
		
		$provider = new Providers\Course_Performance_Provider();
		$courses  = $provider->get_course_table();
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tutorlms-course-performance.csv' );
		
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Course ID', 'Title', 'Learners', 'Completion Rate (%)', 'Avg Rating', 'Revenue (THB)', 'Health Score' ) );
		
		foreach ( $courses as $c ) {
			fputcsv( $output, array( $c['id'], $c['title'], $c['learners'], $c['completion_rate'], $c['avg_rating'], $c['revenue'], $c['health'] ) );
		}
		fclose( $output );
		exit;
	}

	public function export_students(): void {
		$this->check_permissions();
		
		$provider = new Providers\Student_Provider();
		$students = $provider->get_student_table(0, 500); // Export up to 500 for MVP
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tutorlms-student-roster.csv' );
		
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'User ID', 'Name', 'Email', 'Courses Taken', 'Avg Progress (%)', 'Last Activity', 'Status' ) );
		
		foreach ( $students as $s ) {
			fputcsv( $output, array( $s['user_id'], $s['display_name'], $s['email'], $s['courses_taken'], $s['avg_progress'], $s['last_activity'], $s['status'] ) );
		}
		fclose( $output );
		exit;
	}
}
