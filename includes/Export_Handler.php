<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

class Export_Handler {

	public const NONCE_ACTION = 'tutorlms_analytics_export';

	public function register(): void {
		add_action( 'admin_post_tutorlms_export_revenue', array( $this, 'export_revenue' ) );
		add_action( 'admin_post_tutorlms_export_courses', array( $this, 'export_courses' ) );
		add_action( 'admin_post_tutorlms_export_students', array( $this, 'export_students' ) );
	}

	/**
	 * Nonced export URL for use in views.
	 */
	public static function url( string $action ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . $action ),
			self::NONCE_ACTION
		);
	}

	private function guard(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_tutor' ) && ! current_user_can( 'tutor_instructor' ) ) {
			wp_die( esc_html__( 'ไม่มีสิทธิ์เข้าถึง', 'tutorlms-analytics' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'ลิงก์ส่งออกหมดอายุ กรุณาลองใหม่', 'tutorlms-analytics' ), '', array( 'response' => 403 ) );
		}
	}

	private function send_headers( string $filename ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		// UTF-8 BOM so Excel opens Thai text correctly.
		echo "\xEF\xBB\xBF";
	}

	public function export_revenue(): void {
		$this->guard();

		$provider = new Providers\Monetization_Provider();
		$stats    = $provider->get_monetization_stats( 0, Date_Range::last_days( 30 ) );

		$this->send_headers( 'tutorlms-revenue-30days.csv' );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Date', 'Net Revenue (THB)' ) );
		$labels = $stats['trend']['labels'] ?? array();
		foreach ( $labels as $index => $date ) {
			fputcsv( $output, array( $date, $stats['trend']['data'][ $index ] ?? 0 ) );
		}
		fclose( $output );
		exit;
	}

	public function export_courses(): void {
		$this->guard();

		$provider = new Providers\Course_Performance_Provider();
		$courses  = $provider->get_course_table();

		$this->send_headers( 'tutorlms-course-performance.csv' );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Course ID', 'Title', 'Learners', 'Completion Rate (%)', 'Avg Rating', 'Revenue (THB)', 'Health Score' ) );
		foreach ( $courses as $c ) {
			fputcsv( $output, array( $c['id'], $c['title'], $c['learners'], $c['completion_rate'], $c['avg_rating'], $c['revenue'], $c['health'] ) );
		}
		fclose( $output );
		exit;
	}

	public function export_students(): void {
		$this->guard();

		$provider = new Providers\Student_Provider();
		$students = $provider->get_student_table( 0, 500 );

		$this->send_headers( 'tutorlms-student-roster.csv' );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'User ID', 'Name', 'Email', 'Courses Taken', 'Avg Progress (%)', 'Last Activity', 'Status' ) );
		foreach ( $students as $s ) {
			fputcsv( $output, array( $s['user_id'], $s['display_name'], $s['email'], $s['courses_taken'], $s['avg_progress'], $s['last_activity'], $s['status'] ) );
		}
		fclose( $output );
		exit;
	}
}
