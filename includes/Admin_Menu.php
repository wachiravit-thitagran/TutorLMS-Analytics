<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

class Admin_Menu {

	public const HOOK_SUFFIX = 'tutor-lms_page_tutorlms-analytics';

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
		if ( self::HOOK_SUFFIX !== $hook ) {
			return;
		}

		$dist = TUTORLMS_ANALYTICS_DIR . 'assets/dist/';
		$url  = TUTORLMS_ANALYTICS_URL . 'assets/dist/';

		// Vendored libraries (pinned versions, no CDN dependency).
		$chart_path = $dist . 'chart.umd.min.js';
		wp_enqueue_script(
			'tutorlms-analytics-chartjs',
			$url . 'chart.umd.min.js',
			array(),
			file_exists( $chart_path ) ? (string) filemtime( $chart_path ) : '4.4.9',
			true
		);

		$css_path = $dist . 'dashboard.css';
		wp_enqueue_style(
			'tutorlms-analytics-dashboard',
			$url . 'dashboard.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : TUTORLMS_ANALYTICS_VERSION
		);

		$app_path = $dist . 'dashboard.js';
		wp_enqueue_script(
			'tutorlms-analytics-dashboard',
			$url . 'dashboard.js',
			array( 'tutorlms-analytics-chartjs' ),
			file_exists( $app_path ) ? (string) filemtime( $app_path ) : TUTORLMS_ANALYTICS_VERSION,
			true
		);

		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
		wp_localize_script(
			'tutorlms-analytics-dashboard',
			'TutorLMSAnalyticsConfig',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'tutor-analytics/v1/section' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'courseId'  => $course_id,
				'locale'    => get_locale(),
				'i18n'      => $this->js_strings(),
			)
		);
	}

	/**
	 * Strings the JS layer renders (charts/empty states) — wrapped for i18n.
	 *
	 * @return array<string,string>
	 */
	private function js_strings(): array {
		return array(
			'loading'        => __( 'กำลังโหลดข้อมูล…', 'tutorlms-analytics' ),
			'error'          => __( 'โหลดข้อมูลไม่สำเร็จ กรุณาลองใหม่', 'tutorlms-analytics' ),
			'retry'          => __( 'ลองใหม่', 'tutorlms-analytics' ),
			'noData'         => __( 'ยังไม่มีข้อมูลในช่วงเวลานี้', 'tutorlms-analytics' ),
			'vsPrevious'     => __( 'เทียบช่วงก่อนหน้า', 'tutorlms-analytics' ),
			'notEnoughData'  => __( 'ข้อมูลไม่เพียงพอสำหรับการวิเคราะห์', 'tutorlms-analytics' ),
		);
	}

	public function render_page(): void {
		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;

		// Active date range from the picker (defaults to last 30 days).
		$from  = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : null;
		$to    = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : null;
		$range = Date_Range::from_request( $from, $to );

		$service = new Analytics_Service();
		$courses = ( new Data_Provider() )->get_all_courses();

		if ( $course_id > 0 ) {
			$initial_section = 'insights';
			$sections        = Analytics_Service::COURSE_SECTIONS;
			$initial_data    = $service->get_section( $initial_section, $course_id, $range );
			$course_title    = get_the_title( $course_id );
			require TUTORLMS_ANALYTICS_DIR . 'views/admin-dashboard-single.php';
		} else {
			$initial_section = 'overview';
			$sections        = Analytics_Service::GLOBAL_SECTIONS;
			$initial_data    = $service->get_section( $initial_section, $course_id, $range );
			require TUTORLMS_ANALYTICS_DIR . 'views/admin-dashboard-global.php';
		}
	}
}
