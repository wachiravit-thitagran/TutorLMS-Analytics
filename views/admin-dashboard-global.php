<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store-wide analytics dashboard (shell only — the JS layer fetches and renders
 * each section on demand). Consolidated to four tabs.
 *
 * @var array                          $initial_data
 * @var string                         $initial_section
 * @var array                          $courses
 * @var \TutorLMS_Analytics\Date_Range $range
 */
require_once TUTORLMS_ANALYTICS_DIR . 'views/partials/helpers.php';

$tabs = array(
	'overview'     => __( 'ภาพรวม', 'tutorlms-analytics' ),
	'courses'      => __( 'ประสิทธิภาพคอร์ส', 'tutorlms-analytics' ),
	'monetization' => __( 'รายได้และการขาย', 'tutorlms-analytics' ),
	'community'    => __( 'คุณภาพและการมีส่วนร่วม', 'tutorlms-analytics' ),
);
?>
<div class="wrap tla-app">
	<div class="tla-header">
		<div>
			<h1 class="tla-title"><?php esc_html_e( 'Tutor Analytics', 'tutorlms-analytics' ); ?></h1>
			<p class="tla-subtitle"><?php esc_html_e( 'ภาพรวมการเรียนรู้ของทั้งแพลตฟอร์ม', 'tutorlms-analytics' ); ?></p>
		</div>
		<?php require TUTORLMS_ANALYTICS_DIR . 'views/partials/date-range.php'; ?>
	</div>

	<div class="tla-tablist" role="tablist" aria-label="<?php esc_attr_e( 'หมวดสถิติ', 'tutorlms-analytics' ); ?>">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<button type="button" class="tla-tab" role="tab"
				id="tab-<?php echo esc_attr( $key ); ?>"
				aria-controls="panel-<?php echo esc_attr( $key ); ?>"
				aria-selected="false"
				tabindex="-1"
				data-section="<?php echo esc_attr( $key ); ?>"
				<?php echo $key === $initial_section ? 'data-initial="1"' : ''; ?>>
				<?php echo esc_html( $label ); ?>
			</button>
		<?php endforeach; ?>
	</div>

	<!-- Overview -->
	<section id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" tabindex="0"
		data-initial="<?php echo 'overview' === $initial_section ? '1' : '0'; ?>" hidden>
		<div data-section-body>
			<div class="tla-grid cols-4" id="tla-kpis"></div>
			<div class="tla-grid cols-3">
				<?php
				tla_chart_card( 'chart-enrollment', __( 'แนวโน้มการสมัครเรียน', 'tutorlms-analytics' ), __( 'ผู้สมัครใหม่ต่อวันในช่วงที่เลือก', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-active', __( 'ผู้เข้าเรียน (Active)', 'tutorlms-analytics' ), __( 'จำนวนผู้เรียนที่มีกิจกรรมต่อวัน', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-completions', __( 'ผู้ที่เรียนจบ', 'tutorlms-analytics' ), __( 'จำนวนการเรียนจบต่อวัน', 'tutorlms-analytics' ) );
				?>
			</div>
			<div class="tla-grid cols-2">
				<?php
				tla_chart_card( 'chart-popularity', __( 'สัดส่วนความนิยมของคอร์ส', 'tutorlms-analytics' ), __( 'คอร์สที่มีผู้ลงทะเบียนมากที่สุด', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-activity-day', __( 'กิจกรรมแยกตามวัน', 'tutorlms-analytics' ), __( 'วันที่ผู้เรียนมีกิจกรรมมากที่สุด', 'tutorlms-analytics' ) );
				?>
			</div>
			<div class="tla-grid cols-3">
				<?php
				tla_chart_card( 'chart-device', __( 'อุปกรณ์ที่ใช้เรียน', 'tutorlms-analytics' ), '', 'sm' );
				tla_chart_card( 'chart-browser', __( 'Browser', 'tutorlms-analytics' ), '', 'sm' );
				tla_chart_card( 'chart-hourly', __( 'ช่วงเวลาที่เข้าเรียน', 'tutorlms-analytics' ), '', 'sm' );
				?>
			</div>
		</div>
	</section>

	<!-- Course performance -->
	<section id="panel-courses" role="tabpanel" aria-labelledby="tab-courses" tabindex="0" hidden>
		<div data-section-body>
			<div class="tla-card">
				<div class="tla-card-head">
					<div>
						<h3 class="tla-card-title"><?php esc_html_e( 'ประสิทธิภาพคอร์ส', 'tutorlms-analytics' ); ?></h3>
						<p class="tla-card-desc"><?php esc_html_e( 'Health = อัตราเรียนจบ (50) + คะแนนรีวิว (40) + มีผู้เรียน (10) — คลิกหัวคอลัมน์เพื่อจัดเรียง', 'tutorlms-analytics' ); ?></p>
					</div>
					<div class="tla-export">
						<a class="tla-btn" href="<?php echo esc_url( \TutorLMS_Analytics\Export_Handler::url( 'tutorlms_export_courses' ) ); ?>"><?php esc_html_e( 'ส่งออกคอร์ส (CSV)', 'tutorlms-analytics' ); ?></a>
						<a class="tla-btn" href="<?php echo esc_url( \TutorLMS_Analytics\Export_Handler::url( 'tutorlms_export_students' ) ); ?>"><?php esc_html_e( 'ส่งออกผู้เรียน', 'tutorlms-analytics' ); ?></a>
						<a class="tla-btn" href="<?php echo esc_url( \TutorLMS_Analytics\Export_Handler::url( 'tutorlms_export_revenue' ) ); ?>"><?php esc_html_e( 'ส่งออกรายได้', 'tutorlms-analytics' ); ?></a>
					</div>
				</div>
				<div id="tla-course-table"></div>
			</div>
		</div>
	</section>

	<!-- Monetization -->
	<section id="panel-monetization" role="tabpanel" aria-labelledby="tab-monetization" tabindex="0" hidden>
		<div data-section-body>
			<div class="tla-grid cols-4" id="tla-money-kpis"></div>
			<div class="tla-grid cols-3">
				<?php
				tla_chart_card( 'chart-revenue-trend', __( 'แนวโน้มรายได้สุทธิ', 'tutorlms-analytics' ), __( 'รายได้สุทธิรายวัน (รวม native + WooCommerce)', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-order-type', __( 'รายได้ตามประเภทคำสั่งซื้อ', 'tutorlms-analytics' ), __( 'ซื้อครั้งเดียว / สมาชิก / ต่ออายุ', 'tutorlms-analytics' ), 'sm' );
				tla_chart_card( 'chart-enroll-source', __( 'ที่มาของการลงทะเบียน', 'tutorlms-analytics' ), __( 'Bundle / สมาชิก / ในระบบ / ภายนอก / เพิ่มเอง', 'tutorlms-analytics' ), 'sm' );
				?>
			</div>
			<div class="tla-card">
				<h3 class="tla-card-title"><?php esc_html_e( 'สมาชิก (Subscriptions)', 'tutorlms-analytics' ); ?></h3>
				<p class="tla-card-desc"><?php esc_html_e( 'MRR เป็นค่าประมาณจากรายได้สมาชิกในช่วงที่เลือก', 'tutorlms-analytics' ); ?></p>
				<div class="tla-facts" id="tla-sub-facts"></div>
			</div>
			<div class="tla-grid cols-2">
				<?php
				tla_panel_card( 'tla-coupons', __( 'คูปองที่ใช้บ่อย', 'tutorlms-analytics' ) );
				tla_panel_card( 'tla-bundles', __( 'Course Bundles', 'tutorlms-analytics' ) );
				?>
			</div>
		</div>
	</section>

	<!-- Community & quality -->
	<section id="panel-community" role="tabpanel" aria-labelledby="tab-community" tabindex="0" hidden>
		<div data-section-body>
			<div class="tla-card">
				<h3 class="tla-card-title"><?php esc_html_e( 'ภาพรวมการมีส่วนร่วม', 'tutorlms-analytics' ); ?></h3>
				<div class="tla-facts" id="tla-community-facts"></div>
			</div>
			<div class="tla-grid cols-2">
				<?php
				tla_panel_card( 'tla-qna-unanswered', __( 'คำถามค้างตอบล่าสุด (Q&A)', 'tutorlms-analytics' ), __( 'ตอบเพื่อลดการทิ้งคอร์ส', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-cert-trend', __( 'ใบรับรองที่ออกรายเดือน', 'tutorlms-analytics' ), __( 'ย้อนหลัง 6 เดือน', 'tutorlms-analytics' ) );
				?>
			</div>
			<div class="tla-grid cols-2">
				<div class="tla-card">
					<h3 class="tla-card-title"><?php esc_html_e( 'ประเภทคำถามควิซ', 'tutorlms-analytics' ); ?></h3>
					<p class="tla-card-desc"><?php esc_html_e( 'สีส้ม = คำถามแบบใหม่ใน Tutor LMS 4.0', 'tutorlms-analytics' ); ?></p>
					<div class="tla-chart" id="chart-quiz-types-comm"></div>
					<p class="tla-note" id="tla-v4-adoption-comm"></p>
				</div>
				<?php tla_chart_card( 'chart-gradebook-comm', __( 'การกระจายเกรด (Gradebook)', 'tutorlms-analytics' ), __( 'ต้องเปิดใช้ addon Gradebook', 'tutorlms-analytics' ) ); ?>
			</div>
			<?php tla_panel_card( 'tla-live-upcoming', __( 'Live Lesson ที่กำลังจะมาถึง', 'tutorlms-analytics' ) ); ?>
		</div>
	</section>

	<?php require TUTORLMS_ANALYTICS_DIR . 'views/partials/initial-data.php'; ?>
</div>
