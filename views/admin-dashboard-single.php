<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single-course analytics dashboard (shell only — sections are fetched and
 * rendered by the JS layer). Consolidated from ten tabs to seven.
 *
 * @var array                          $initial_data
 * @var string                         $initial_section
 * @var int                            $course_id
 * @var string                         $course_title
 * @var \TutorLMS_Analytics\Date_Range $range
 */
require_once TUTORLMS_ANALYTICS_DIR . 'views/partials/helpers.php';

$tabs = array(
	'insights'   => __( 'ภาพรวมการเรียนรู้', 'tutorlms-analytics' ),
	'teaching'   => __( 'ประสิทธิผลการสอน', 'tutorlms-analytics' ),
	'content'    => __( 'เนื้อหาและจุดปรับปรุง', 'tutorlms-analytics' ),
	'assessment' => __( 'การประเมินผล', 'tutorlms-analytics' ),
	'community'  => __( 'การมีส่วนร่วม', 'tutorlms-analytics' ),
	'learners'   => __( 'ผู้เรียน', 'tutorlms-analytics' ),
	'action'     => __( 'ศูนย์จัดการ', 'tutorlms-analytics' ),
);
?>
<div class="wrap tla-app">
	<div class="tla-header">
		<div>
			<a class="tla-back" href="<?php echo esc_url( admin_url( 'admin.php?page=tutorlms-analytics' ) ); ?>">
				&larr; <?php esc_html_e( 'กลับไปหน้าภาพรวม', 'tutorlms-analytics' ); ?>
			</a>
			<h1 class="tla-title">
				<?php echo esc_html( $course_title ); ?>
				<span class="tla-course-badge"><?php esc_html_e( 'สถิติรายคอร์ส', 'tutorlms-analytics' ); ?></span>
			</h1>
		</div>
		<?php require TUTORLMS_ANALYTICS_DIR . 'views/partials/date-range.php'; ?>
	</div>

	<div class="tla-tablist" role="tablist" aria-label="<?php esc_attr_e( 'หมวดสถิติของคอร์ส', 'tutorlms-analytics' ); ?>">
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

	<!-- Insights -->
	<section id="panel-insights" role="tabpanel" aria-labelledby="tab-insights" tabindex="0"
		data-initial="<?php echo 'insights' === $initial_section ? '1' : '0'; ?>" hidden>
		<div data-section-body>
			<div class="tla-grid cols-4" id="tla-kpis"></div>
			<div class="tla-grid cols-2">
				<?php
				tla_chart_card( 'chart-survival', __( 'กราฟการเรียนต่อ (Survival Curve)', 'tutorlms-analytics' ), __( 'จุดที่กราฟตกชัน = บทเรียนที่ผู้เรียนทิ้งคอร์สมาก ควรตรวจสอบเนื้อหานั้น', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-progress', __( 'สัดส่วนความคืบหน้า', 'tutorlms-analytics' ), __( 'การกระจายตัวของสถานะการเรียนปัจจุบัน', 'tutorlms-analytics' ) );
				?>
			</div>
			<div class="tla-grid cols-2">
				<?php
				tla_chart_card( 'chart-quiz-dist', __( 'การกระจายคะแนนสอบ', 'tutorlms-analytics' ), __( 'ใช้วัดว่าข้อสอบยากหรือง่ายเกินไป', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-passfail', __( 'สัดส่วนสอบผ่าน/ตก', 'tutorlms-analytics' ), __( 'อัตราการสอบผ่านเทียบกับการเข้าสอบทั้งหมด', 'tutorlms-analytics' ) );
				?>
			</div>
			<div class="tla-grid cols-2">
				<?php
				tla_chart_card( 'chart-enrollment', __( 'แนวโน้มการสมัครเรียน', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-completions', __( 'ผู้ที่เรียนจบ', 'tutorlms-analytics' ) );
				?>
			</div>
		</div>
	</section>

	<!-- Teaching effectiveness -->
	<section id="panel-teaching" role="tabpanel" aria-labelledby="tab-teaching" tabindex="0" hidden>
		<div data-section-body>
			<div class="tla-grid cols-2">
				<?php
				tla_chart_card( 'chart-time-content', __( 'เวลาที่ใช้ต่อบทเรียน', 'tutorlms-analytics' ), __( 'บทที่ใช้เวลานานผิดปกติอาจยากหรือยาวเกินไป', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-hourly', __( 'ช่วงเวลาที่เข้าเรียน', 'tutorlms-analytics' ), __( 'กิจกรรมแยกตามชั่วโมง', 'tutorlms-analytics' ) );
				?>
			</div>
			<div class="tla-grid cols-2">
				<?php
				tla_chart_card( 'chart-device', __( 'อุปกรณ์ที่ใช้เรียน', 'tutorlms-analytics' ), '', 'sm' );
				tla_chart_card( 'chart-rating-dist', __( 'การกระจายรีวิว', 'tutorlms-analytics' ), __( 'จำนวนรีวิวแยกตามดาว', 'tutorlms-analytics' ), 'sm' );
				?>
			</div>
			<div class="tla-grid cols-2">
				<?php tla_chart_card( 'chart-rating-trend', __( 'แนวโน้มคะแนนรีวิว', 'tutorlms-analytics' ), __( 'ค่าเฉลี่ยรายเดือน (6 เดือน)', 'tutorlms-analytics' ), 'sm' ); ?>
				<div class="tla-card">
					<h3 class="tla-card-title"><?php esc_html_e( 'ความพึงพอใจ (NPS)', 'tutorlms-analytics' ); ?></h3>
					<p class="tla-card-desc"><?php esc_html_e( '4-5★ = Promoter, 3★ = Passive, 1-2★ = Detractor', 'tutorlms-analytics' ); ?></p>
					<div class="tla-facts" id="tla-nps"></div>
				</div>
			</div>
		</div>
	</section>

	<!-- Content & gaps -->
	<section id="panel-content" role="tabpanel" aria-labelledby="tab-content" tabindex="0" hidden>
		<div data-section-body>
			<div class="tla-grid cols-2">
				<?php
				tla_panel_card( 'tla-dropoff', __( 'บทเรียนที่มี Drop-off สูงสุด', 'tutorlms-analytics' ), __( 'จุดที่ผู้เรียนหยุดเรียนมากที่สุด', 'tutorlms-analytics' ) );
				tla_panel_card( 'tla-hardest', __( 'ควิซที่ยากที่สุด', 'tutorlms-analytics' ), __( 'ควิซที่มี Pass Rate ต่ำที่สุด', 'tutorlms-analytics' ) );
				?>
			</div>
			<div class="tla-grid cols-2">
				<?php
				tla_panel_card( 'tla-exit', __( 'บทเรียนสุดท้ายก่อนหายไป (Exit)', 'tutorlms-analytics' ) );
				tla_panel_card( 'tla-revisit', __( 'บทเรียนที่เปิดซ้ำบ่อย', 'tutorlms-analytics' ), __( 'อาจเป็นเนื้อหาที่เข้าใจยาก', 'tutorlms-analytics' ) );
				?>
			</div>
			<?php tla_panel_card( 'tla-curriculum', __( 'สถิติรายบทเรียนตามโครงสร้างหลักสูตร', 'tutorlms-analytics' ) ); ?>
		</div>
	</section>

	<!-- Assessment -->
	<section id="panel-assessment" role="tabpanel" aria-labelledby="tab-assessment" tabindex="0" hidden>
		<div data-section-body>
			<div class="tla-grid cols-2">
				<?php
				tla_panel_card( 'tla-q-difficulty', __( 'ความยากรายข้อ', 'tutorlms-analytics' ), __( 'ข้อที่ตอบถูกน้อย = ยากหรือกำกวม', 'tutorlms-analytics' ) );
				tla_panel_card( 'tla-q-wrong', __( 'คำตอบผิดยอดนิยม', 'tutorlms-analytics' ) );
				?>
			</div>
			<div class="tla-card">
				<h3 class="tla-card-title"><?php esc_html_e( 'ผลการตอบรายข้อ (ถูก/ผิด)', 'tutorlms-analytics' ); ?></h3>
				<p class="tla-card-desc"><?php esc_html_e( 'แท่งละข้อ สีเขียว = ตอบถูก สีแดง = ตอบผิด', 'tutorlms-analytics' ); ?></p>
				<div class="tla-chart" id="chart-quiz-types"></div>
			</div>
			<div class="tla-card">
				<h3 class="tla-card-title"><?php esc_html_e( 'งานที่มอบหมาย (Assignments)', 'tutorlms-analytics' ); ?></h3>
				<div class="tla-facts" id="tla-assign-facts"></div>
			</div>
			<?php tla_chart_card( 'chart-gradebook', __( 'การกระจายเกรด (Gradebook)', 'tutorlms-analytics' ), __( 'ต้องเปิดใช้ addon Gradebook', 'tutorlms-analytics' ) ); ?>
		</div>
	</section>

	<!-- Community -->
	<section id="panel-community" role="tabpanel" aria-labelledby="tab-community" tabindex="0" hidden>
		<div data-section-body>
			<div class="tla-card">
				<h3 class="tla-card-title"><?php esc_html_e( 'ภาพรวมการมีส่วนร่วม', 'tutorlms-analytics' ); ?></h3>
				<div class="tla-facts" id="tla-community-facts"></div>
			</div>
			<div class="tla-grid cols-2">
				<?php
				tla_panel_card( 'tla-qna-unanswered', __( 'คำถามค้างตอบล่าสุด (Q&A)', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-cert-trend', __( 'ใบรับรองที่ออกรายเดือน', 'tutorlms-analytics' ) );
				?>
			</div>
			<div class="tla-grid cols-2">
				<div class="tla-card">
					<h3 class="tla-card-title"><?php esc_html_e( 'ประเภทคำถามควิซ', 'tutorlms-analytics' ); ?></h3>
					<div class="tla-chart" id="chart-quiz-types-comm"></div>
					<p class="tla-note" id="tla-v4-adoption-comm"></p>
				</div>
				<?php tla_chart_card( 'chart-gradebook-comm', __( 'การกระจายเกรด (Gradebook)', 'tutorlms-analytics' ) ); ?>
			</div>
			<?php tla_panel_card( 'tla-live-upcoming', __( 'Live Lesson ที่กำลังจะมาถึง', 'tutorlms-analytics' ) ); ?>
		</div>
	</section>

	<!-- Learners -->
	<section id="panel-learners" role="tabpanel" aria-labelledby="tab-learners" tabindex="0" hidden>
		<div data-section-body>
			<div class="tla-card">
				<h3 class="tla-card-title"><?php esc_html_e( 'สถานะปัจจุบันของผู้เรียนรายบทเรียน', 'tutorlms-analytics' ); ?></h3>
				<p class="tla-card-desc"><?php esc_html_e( 'จุดสี = บทเรียนที่เรียนจบแล้ว วงแหวนเรืองแสง = บทเรียนล่าสุด', 'tutorlms-analytics' ); ?></p>
				<div id="tla-lesson-matrix"></div>
			</div>
			<div class="tla-card">
				<h3 class="tla-card-title"><?php esc_html_e( 'ตารางความคืบหน้าของผู้เรียน', 'tutorlms-analytics' ); ?></h3>
				<p class="tla-card-desc"><?php esc_html_e( 'ค้นหา / จัดเรียงได้ทุกคอลัมน์', 'tutorlms-analytics' ); ?></p>
				<div id="tla-learner-table"></div>
			</div>
			<div class="tla-grid cols-2">
				<?php
				tla_chart_card( 'chart-cohort', __( 'อัตราการเรียนจบตามกลุ่มผู้สมัคร (Cohort)', 'tutorlms-analytics' ) );
				tla_chart_card( 'chart-retention', __( 'อัตราการเข้าเรียนอย่างต่อเนื่องรายสัปดาห์ (Retention)', 'tutorlms-analytics' ) );
				?>
			</div>
		</div>
	</section>

	<!-- Action center -->
	<section id="panel-action" role="tabpanel" aria-labelledby="tab-action" tabindex="0" hidden>
		<div data-section-body>
			<div class="tla-card">
				<h3 class="tla-card-title"><?php esc_html_e( 'การแจ้งเตือน', 'tutorlms-analytics' ); ?></h3>
				<div id="tla-alerts"></div>
			</div>
			<?php tla_panel_card( 'tla-atrisk', __( 'ผู้เรียนกลุ่มเสี่ยง (At-Risk)', 'tutorlms-analytics' ), __( 'Engagement ต่ำและความคืบหน้าน้อย', 'tutorlms-analytics' ) ); ?>
		</div>
	</section>

	<?php require TUTORLMS_ANALYTICS_DIR . 'views/partials/initial-data.php'; ?>
</div>
