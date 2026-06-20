<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

class Admin_Menu {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu_page(): void {
		add_submenu_page(
			'tutor',
			__( 'Tutor Analytics', 'tutorlms-analytics' ),
			__( 'สถิติเชิงลึก', 'tutorlms-analytics' ),
			'manage_tutor',
			'tutorlms-analytics',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ): void {
		if ( 'tutor-lms_page_tutorlms-analytics' !== $hook ) {
			return;
		}

		// Enqueue Chart.js, Tailwind, and Tabler Icons via CDN for rapid admin UI.
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true );
		wp_enqueue_script( 'tailwindcss', 'https://cdn.tailwindcss.com', array(), '3.4.0', false );
		wp_enqueue_style( 'tabler-icons', 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css', array(), 'latest' );
	}

	public function render_page(): void {
		$course_id         = isset( $_GET['course_id'] ) ? (int) $_GET['course_id'] : 0;
		$provider          = new Data_Provider();
		$revenue_provider  = new Providers\Revenue_Provider();
		$course_perf_prov  = new Providers\Course_Performance_Provider();
		$student_provider  = new Providers\Student_Provider();
		$alerts_provider   = new Providers\Alerts_Provider();
		
		// Legacy providers for specific tabs
		$funnel_provider   = new Providers\Funnel_Provider();
		$survival_provider = new Providers\Survival_Provider();
		$quiz_provider     = new Providers\Quiz_Provider();

		$courses = $provider->get_all_courses();
		$stats   = $provider->get_all_stats( $course_id );
		
		// New BI Data
		$stats['revenue']            = $revenue_provider->get_revenue_stats( $course_id );
		$stats['course_performance'] = $course_perf_prov->get_course_table( $course_id );
		$stats['student_table']      = $student_provider->get_student_table( $course_id );
		$stats['alerts']             = $alerts_provider->get_alerts( $course_id );

		// Retained Advanced Data
		$stats['progress_distribution'] = $funnel_provider->get_progress_distribution( $course_id );
		$stats['quiz_performance']      = $quiz_provider->get_quiz_performance( $course_id );
		
		$top_course_id = $course_id;
		if ( $top_course_id === 0 ) {
			if ( ! empty( $stats['top_courses'][0]['id'] ) ) {
				$top_course_id = $stats['top_courses'][0]['id'];
			} else {
				global $wpdb;
				$top_course_id = (int) $wpdb->get_var( "SELECT comment_post_ID FROM {$wpdb->comments} WHERE comment_type = 'tutor_enrolled' GROUP BY comment_post_ID ORDER BY COUNT(comment_ID) DESC LIMIT 1" );
			}
		}
		
		$stats['survival_curve'] = $top_course_id > 0 ? $survival_provider->get_survival_curve( $top_course_id ) : array('labels'=>[], 'data'=>[]);
		$stats['survival_course_name'] = $top_course_id > 0 ? get_the_title( $top_course_id ) : '';
		
		require TUTORLMS_ANALYTICS_DIR . 'views/admin-dashboard.php';
	}
}
