<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $stats
 * @var int   $course_id
 */
$course_title = get_the_title( $course_id );
?>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="wrap" x-data="{ tab: 'insights' }">
	<div class="mb-6 mt-4 flex flex-col gap-2">
		<div>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tutorlms-analytics' ) ); ?>" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
				<i class="ti ti-arrow-left mr-1"></i> กลับไปหน้าภาพรวม
			</a>
		</div>
		<h1 class="!text-3xl !font-bold !m-0 text-gray-800 flex items-center gap-3">
			<?php echo esc_html( $course_title ); ?>
			<span class="text-sm bg-blue-100 text-blue-800 px-3 py-1 rounded-full font-medium tracking-wide">สถิติรายคอร์ส</span>
		</h1>
	</div>
	
	<!-- Tabs Navigation -->
	<div class="border-b border-gray-200 mb-6 bg-white rounded-t-lg px-4 pt-4 shadow-sm">
		<nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
			<a href="#" @click.prevent="tab = 'insights'" :class="tab === 'insights' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">ข้อมูลเชิงลึกการเรียนรู้</a>
			<a href="#" @click.prevent="tab = 'teaching'" :class="tab === 'teaching' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">ประสิทธิผลการสอน</a>
			<a href="#" @click.prevent="tab = 'content-gaps'" :class="tab === 'content-gaps' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">จุดปรับปรุง</a>
			<a href="#" @click.prevent="tab = 'content-insights'" :class="tab === 'content-insights' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">สถิติรายบทเรียน</a>
			<a href="#" @click.prevent="tab = 'learners'" :class="tab === 'learners' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">รายชื่อผู้เรียน</a>
			<a href="#" @click.prevent="tab = 'alerts'" :class="tab === 'alerts' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">
				ศูนย์จัดการ (Action Center)
				<?php 
					$alert_count = count( $stats['alerts'] ?? [] );
					$at_risk_count = $stats['engagement']['at_risk_count'] ?? 0;
					$total_badge = $alert_count + $at_risk_count;
				?>
				<?php if ( $total_badge > 0 ) : ?>
					<span class="bg-red-100 text-red-600 ml-1 py-0.5 px-2 rounded-full text-xs"><?php echo $total_badge; ?></span>
				<?php endif; ?>
			</a>
		</nav>
	</div>

	<!-- TAB 1: Insights -->
	<div x-show="tab === 'insights'" x-cloak>
		<!-- Highlight Cards -->
		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-blue-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">ผู้เรียนที่สมัคร</h3>
				<p class="text-2xl font-bold text-gray-900"><?php echo number_format( $stats['total_students'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-green-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">อัตราการเรียนจบ</h3>
				<p class="text-2xl font-bold text-gray-900"><?php echo esc_html( $stats['course_performance'][0]['completion_rate'] ?? 0 ); ?>%</p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-yellow-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">คะแนนควิซเฉลี่ย</h3>
				<p class="text-2xl font-bold text-gray-900"><?php echo esc_html( $stats['quiz_performance']['avg_score'] ?? 0 ); ?>%</p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-red-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">อัตราการทิ้งคอร์ส</h3>
				<?php 
					$survival_data = $stats['survival_curve']['data'] ?? [];
					$drop_off = 0;
					if ( ! empty( $survival_data ) ) {
						$last_survival = end( $survival_data );
						$drop_off = 100 - $last_survival;
					}
				?>
				<p class="text-2xl font-bold text-gray-900"><?php echo esc_html( $drop_off ); ?>%</p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-purple-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">เวลาเรียนเฉลี่ย</h3>
				<?php
					$avg_time = $stats['time_analytics']['total_learning_time'] ?? 0;
					$hours = floor( $avg_time / 3600 );
					$mins  = floor( ($avg_time % 3600) / 60 );
					$time_display = $hours > 0 ? "{$hours} ชม. {$mins} นาที" : "{$mins} นาที";
				?>
				<p class="text-2xl font-bold text-gray-900"><?php echo esc_html( $avg_time > 0 ? $time_display : 'ไม่มีข้อมูล' ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-indigo-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">วันเฉลี่ยจนเรียนจบ</h3>
				<?php $avg_days = $stats['time_analytics']['avg_days_to_complete'] ?? 0; ?>
				<p class="text-2xl font-bold text-gray-900"><?php echo $avg_days > 0 ? esc_html( $avg_days ) . ' วัน' : 'ไม่มีข้อมูล'; ?></p>
			</div>
		</div>

		<!-- Deep Insights Charts -->
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 relative">
				<div class="absolute top-4 right-4 bg-blue-50 text-blue-700 text-xs px-2 py-1 rounded font-medium">สถิติสำคัญ</div>
				<h3 class="text-lg font-semibold text-gray-800 mb-1">กราฟการเรียนต่อ (Survival Curve)</h3>
				<p class="text-sm text-gray-500 mb-4">แสดงจุดที่ผู้เรียนทิ้งคอร์ส หากกราฟตกชันมาก อาจแปลว่าเนื้อหานั้นยากหรือน่าเบื่อเกินไป</p>
				<div class="relative h-72 w-full">
					<canvas id="survivalChart"></canvas>
				</div>
			</div>
			
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">สัดส่วนความคืบหน้า</h3>
				<p class="text-sm text-gray-500 mb-4">การกระจายตัวของสถานะการเรียนปัจจุบัน</p>
				<div class="relative h-72 w-full">
					<canvas id="progressChart"></canvas>
				</div>
			</div>
		</div>

		<!-- Quiz Performance Charts -->
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">การกระจายตัวของคะแนนสอบ</h3>
				<p class="text-sm text-gray-500 mb-4">แสดงผลคะแนนควิซเพื่อวัดระดับความยากง่ายของข้อสอบ</p>
				<div class="relative h-72 w-full">
					<canvas id="quizScoreDistributionChart"></canvas>
				</div>
			</div>
			
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">สัดส่วนการสอบผ่าน/ตก</h3>
				<p class="text-sm text-gray-500 mb-4">แสดงอัตราการสอบผ่านเมื่อเทียบกับการเข้าสอบทั้งหมด</p>
				<div class="relative h-72 w-full">
					<canvas id="passFailRatioChart"></canvas>
				</div>
			</div>
		</div>

		<!-- Daily Trends Charts -->
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
			<!-- Enrollment Graph -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">แนวโน้มการสมัครเรียน (30 วัน)</h3>
				<div class="relative h-72 w-full">
					<canvas id="enrollmentTrendChart"></canvas>
				</div>
			</div>
			<!-- Active Students Graph -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">นักเรียนที่เข้าเรียน (Active 30 วัน)</h3>
				<div class="relative h-72 w-full">
					<canvas id="activeStudentsTrendChart"></canvas>
				</div>
			</div>
			<!-- Completion Graph -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">ผู้ที่เรียนจบ (30 วัน)</h3>
				<div class="relative h-72 w-full">
					<canvas id="completionTrendChart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB: Teaching Effectiveness -->
	<div x-show="tab === 'teaching'" x-cloak>
		<!-- Time Analytics -->
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">เวลาที่ใช้ต่อบทเรียน</h3>
				<p class="text-sm text-gray-500 mb-4">เวลาเฉลี่ยที่ผู้เรียนใช้ในแต่ละบทเรียน (นาที)</p>
				<div class="relative" style="min-height: 300px;">
					<canvas id="timePerContentChart"></canvas>
				</div>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">ช่วงเวลาที่ผู้เรียนเข้าเรียน</h3>
				<p class="text-sm text-gray-500 mb-4">กิจกรรมแยกตามชั่วโมง (90 วันล่าสุด)</p>
				<div class="relative h-72 w-full">
					<canvas id="hourlyActivityChart"></canvas>
				</div>
			</div>
		</div>

		<!-- Device & Rating Analytics -->
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">สัดส่วนอุปกรณ์</h3>
				<p class="text-sm text-gray-500 mb-4">Desktop vs Mobile vs Tablet</p>
				<div class="relative h-64 w-full">
					<canvas id="deviceDistChart"></canvas>
				</div>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">สัดส่วน Browser</h3>
				<p class="text-sm text-gray-500 mb-4">Browser ที่ผู้เรียนใช้มากที่สุด</p>
				<div class="relative h-64 w-full">
					<canvas id="browserDistChart"></canvas>
				</div>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">การกระจายตัวของรีวิว</h3>
				<p class="text-sm text-gray-500 mb-4">จำนวนรีวิวแยกตามดาว (1-5★)</p>
				<div class="relative h-64 w-full">
					<canvas id="ratingDistChart"></canvas>
				</div>
			</div>
		</div>

		<!-- NPS + Review Rate + Rating Trend -->
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
			<!-- NPS Card -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-2">Net Promoter Score (NPS)</h3>
				<p class="text-sm text-gray-500 mb-4">4-5★ = Promoter, 3★ = Passive, 1-2★ = Detractor</p>
				<?php 
					$nps = $stats['rating_analytics']['nps_score'] ?? array('score' => 0, 'promoters' => 0, 'passives' => 0, 'detractors' => 0, 'total' => 0);
					$nps_color = $nps['score'] >= 50 ? 'text-green-600' : ($nps['score'] >= 0 ? 'text-yellow-600' : 'text-red-600');
				?>
				<div class="text-center mb-4">
					<span class="text-5xl font-bold <?php echo $nps_color; ?>"><?php echo esc_html( $nps['score'] ); ?></span>
				</div>
				<div class="grid grid-cols-3 gap-2 text-center text-xs">
					<div class="bg-green-50 rounded p-2"><span class="block font-bold text-green-700"><?php echo esc_html( $nps['promoters'] ); ?></span>Promoters</div>
					<div class="bg-gray-50 rounded p-2"><span class="block font-bold text-gray-700"><?php echo esc_html( $nps['passives'] ); ?></span>Passives</div>
					<div class="bg-red-50 rounded p-2"><span class="block font-bold text-red-700"><?php echo esc_html( $nps['detractors'] ); ?></span>Detractors</div>
				</div>
			</div>
			<!-- Review Response Rate -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-2">อัตราการรีวิว</h3>
				<p class="text-sm text-gray-500 mb-4">จำนวนผู้รีวิว / จำนวนผู้เรียนจบ</p>
				<?php $rr = $stats['rating_analytics']['review_response_rate'] ?? array('rate' => 0, 'reviews' => 0, 'completions' => 0); ?>
				<div class="text-center mb-4">
					<span class="text-5xl font-bold text-indigo-600"><?php echo esc_html( $rr['rate'] ); ?>%</span>
				</div>
				<div class="grid grid-cols-2 gap-2 text-center text-xs">
					<div class="bg-indigo-50 rounded p-2"><span class="block font-bold text-indigo-700"><?php echo esc_html( $rr['reviews'] ); ?></span>รีวิวทั้งหมด</div>
					<div class="bg-gray-50 rounded p-2"><span class="block font-bold text-gray-700"><?php echo esc_html( $rr['completions'] ); ?></span>ผู้เรียนจบ</div>
				</div>
			</div>
			<!-- Rating Trend -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">แนวโน้มคะแนนรีวิว</h3>
				<p class="text-sm text-gray-500 mb-4">ค่าเฉลี่ยรีวิวรายเดือน (6 เดือนล่าสุด)</p>
				<div class="relative h-52 w-full">
					<canvas id="ratingTrendChart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB: Content Gaps -->
	<div x-show="tab === 'content-gaps'" x-cloak>
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
			<!-- Highest Drop-off Lessons -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
				<div class="px-6 py-4 border-b border-gray-100">
					<h3 class="text-lg font-semibold text-gray-800"><i class="ti ti-trending-down text-red-500 mr-1"></i> บทเรียนที่มี Drop-off สูงสุด</h3>
					<p class="text-sm text-gray-500">บทเรียนที่ผู้เรียนหยุดเรียนมากที่สุด (Top 3)</p>
				</div>
				<?php $dropoffs = $stats['content_gaps']['highest_dropoff_lessons'] ?? []; ?>
				<?php if ( empty( $dropoffs ) ) : ?>
					<div class="p-6 text-center text-gray-500">ไม่มีข้อมูลเพียงพอ</div>
				<?php else : ?>
					<ul class="divide-y divide-gray-100 m-0 p-0 list-none">
						<?php foreach ( $dropoffs as $i => $df ) : ?>
							<li class="px-6 py-4 flex items-center justify-between">
								<div class="flex items-center gap-3">
									<span class="w-8 h-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-sm font-bold"><?php echo $i + 1; ?></span>
									<div>
										<span class="text-sm font-medium text-gray-800"><?php echo esc_html( $df['title'] ); ?></span>
										<span class="block text-xs text-gray-400">บทที่ <?php echo $df['position']; ?></span>
									</div>
								</div>
								<div class="text-right">
									<span class="text-lg font-bold text-red-600">-<?php echo esc_html( $df['drop_pct'] ); ?>%</span>
									<span class="block text-xs text-gray-400">หายไป <?php echo $df['drop_count']; ?> คน</span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<!-- Hardest Quizzes -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
				<div class="px-6 py-4 border-b border-gray-100">
					<h3 class="text-lg font-semibold text-gray-800"><i class="ti ti-alert-triangle text-yellow-500 mr-1"></i> ควิซที่ยากที่สุด</h3>
					<p class="text-sm text-gray-500">ควิซที่มี Pass Rate ต่ำที่สุด (Top 3)</p>
				</div>
				<?php $hardest = $stats['content_gaps']['hardest_quizzes'] ?? []; ?>
				<?php if ( empty( $hardest ) ) : ?>
					<div class="p-6 text-center text-gray-500">ไม่มีข้อมูลเพียงพอ</div>
				<?php else : ?>
					<ul class="divide-y divide-gray-100 m-0 p-0 list-none">
						<?php foreach ( $hardest as $i => $hq ) : ?>
							<li class="px-6 py-4 flex items-center justify-between">
								<div class="flex items-center gap-3">
									<span class="w-8 h-8 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center text-sm font-bold"><?php echo $i + 1; ?></span>
									<div>
										<span class="text-sm font-medium text-gray-800"><?php echo esc_html( $hq['title'] ); ?></span>
										<span class="block text-xs text-gray-400">เกณฑ์ผ่าน: <?php echo $hq['passing_grade']; ?>% | เข้าสอบ: <?php echo $hq['total_attempts']; ?> ครั้ง</span>
									</div>
								</div>
								<div class="text-right">
									<span class="text-lg font-bold <?php echo $hq['pass_rate'] < 50 ? 'text-red-600' : 'text-yellow-600'; ?>"><?php echo esc_html( $hq['pass_rate'] ); ?>%</span>
									<span class="block text-xs text-gray-400">Pass Rate | เฉลี่ย <?php echo $hq['avg_score']; ?>%</span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>

		<!-- Lesson-Quiz Correlation -->
		<?php $correlation = $stats['content_gaps']['lesson_quiz_correlation'] ?? []; ?>
		<?php if ( ! empty( $correlation ) ) : ?>
		<div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden mb-6">
			<div class="px-6 py-4 border-b border-gray-100">
				<h3 class="text-lg font-semibold text-gray-800"><i class="ti ti-chart-dots text-blue-500 mr-1"></i> ความสัมพันธ์ Lesson ↔ Quiz</h3>
				<p class="text-sm text-gray-500">เปรียบเทียบ: ผู้ที่เรียนจบบทเรียน vs คะแนนควิซในแต่ละ Topic</p>
			</div>
			<div class="overflow-x-auto">
				<table class="min-w-full divide-y divide-gray-200">
					<thead class="bg-gray-50">
						<tr>
							<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Topic</th>
							<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Lesson Completion %</th>
							<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Quiz Avg Score</th>
						</tr>
					</thead>
					<tbody class="bg-white divide-y divide-gray-200">
						<?php foreach ( $correlation as $c ) : ?>
						<tr>
							<td class="px-6 py-3 text-sm font-medium text-gray-800"><?php echo esc_html( $c['topic_title'] ); ?></td>
							<td class="px-6 py-3 text-sm text-right">
								<span class="font-medium <?php echo $c['lesson_completion_pct'] >= 70 ? 'text-green-600' : 'text-yellow-600'; ?>"><?php echo esc_html( $c['lesson_completion_pct'] ); ?>%</span>
							</td>
							<td class="px-6 py-3 text-sm text-right">
								<span class="font-medium <?php echo $c['quiz_avg_score'] >= 70 ? 'text-green-600' : ($c['quiz_avg_score'] >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>"><?php echo esc_html( $c['quiz_avg_score'] ); ?>%</span>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php endif; ?>

		<!-- At-Risk Students -->
		<?php $at_risk = $stats['engagement']['at_risk_students'] ?? []; ?>
		<?php if ( ! empty( $at_risk ) ) : ?>
		<div class="bg-white rounded-lg shadow-sm border border-gray-100 border-l-4 border-l-red-500 overflow-hidden mb-6">
			<div class="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
				<i class="ti ti-alert-circle text-red-500 text-xl"></i>
				<div>
					<h3 class="text-lg font-semibold text-gray-800">ผู้เรียนที่ต้องการความช่วยเหลือ (At-Risk)</h3>
					<p class="text-sm text-gray-500">Engagement Score ต่ำกว่า 30 และ Progress ต่ำกว่า 50%</p>
				</div>
			</div>
			<ul class="divide-y divide-gray-100 m-0 p-0 list-none">
				<?php foreach ( array_slice( $at_risk, 0, 5 ) as $ars ) : ?>
				<li class="px-6 py-3 flex items-center justify-between">
					<div class="flex items-center gap-3">
						<span class="w-8 h-8 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-xs font-bold"><?php echo $ars['score']; ?></span>
						<span class="text-sm font-medium text-gray-800"><?php echo esc_html( $ars['display_name'] ); ?></span>
					</div>
					<div class="flex items-center gap-4 text-sm">
						<span class="text-gray-500">Progress: <span class="font-medium text-red-600"><?php echo $ars['progress_pct']; ?>%</span></span>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>
	</div>

	<!-- TAB 1.5: Content Insights -->
	<div x-show="tab === 'content-insights'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden mb-6">
		<div class="px-6 py-4 border-b border-gray-100">
			<h3 class="text-lg font-semibold text-gray-800">สถิติรายบทเรียนและแบบทดสอบ</h3>
			<p class="text-sm text-gray-500">เรียงตามโครงสร้างหลักสูตร (Topic -> Lesson/Quiz)</p>
		</div>
		<div class="p-6">
			<?php if ( empty( $stats['content_insights'] ) ) : ?>
				<div class="text-center py-8 text-gray-500">
					ไม่มีข้อมูลโครงสร้างหลักสูตรสำหรับคอร์สนี้
				</div>
			<?php else : ?>
				<div class="space-y-4">
					<?php foreach ( $stats['content_insights'] as $topic ) : ?>
						<div class="border border-gray-200 rounded-lg overflow-hidden">
							<!-- Topic Header -->
							<div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex items-center justify-between">
								<h4 class="font-semibold text-gray-800 m-0"><?php echo esc_html( $topic['title'] ); ?></h4>
								<span class="text-xs text-gray-500"><?php echo count( $topic['contents'] ); ?> รายการ</span>
							</div>
							
							<!-- Lessons & Quizzes -->
							<ul class="divide-y divide-gray-100 m-0 p-0 list-none">
								<?php if ( empty( $topic['contents'] ) ) : ?>
									<li class="px-4 py-3 text-sm text-gray-400">ไม่มีบทเรียนในหัวข้อนี้</li>
								<?php else : ?>
									<?php foreach ( $topic['contents'] as $content ) : ?>
										<li class="px-4 py-3 hover:bg-gray-50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
											<div class="flex items-center gap-3">
												<?php if ( $content['type'] === 'tutor_quiz' ) : ?>
													<span class="w-8 h-8 rounded bg-yellow-100 text-yellow-600 flex items-center justify-center shrink-0">
														<i class="ti ti-help-hexagon text-lg"></i>
													</span>
												<?php else : ?>
													<span class="w-8 h-8 rounded bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
														<i class="ti ti-file-text text-lg"></i>
													</span>
												<?php endif; ?>
												<span class="text-sm font-medium text-gray-700"><?php echo esc_html( $content['title'] ); ?></span>
											</div>
											
											<div class="flex items-center gap-4 text-sm text-gray-600 shrink-0">
												<?php if ( $content['type'] === 'lesson' ) : ?>
													<div class="flex items-center gap-1 bg-green-50 text-green-700 px-2 py-1 rounded">
														<i class="ti ti-check"></i>
														<span>เรียนจบแล้ว <?php echo (int) $content['completed_count']; ?> คน</span>
													</div>
												<?php elseif ( $content['type'] === 'tutor_quiz' ) : ?>
													<div class="flex items-center gap-4 bg-yellow-50 text-yellow-800 px-3 py-1 rounded">
														<div class="flex flex-col text-xs text-center border-r border-yellow-200 pr-3">
															<span class="font-bold"><?php echo esc_html( $content['avg_score'] ); ?>%</span>
															<span class="text-yellow-600" style="font-size: 10px;">คะแนนเฉลี่ย</span>
														</div>
														<div class="flex flex-col text-xs text-center">
															<span class="font-bold"><?php echo esc_html( $content['avg_attempts_per_user'] ); ?> ครั้ง</span>
															<span class="text-yellow-600" style="font-size: 10px;">เฉลี่ยต่อคน</span>
														</div>
													</div>
												<?php endif; ?>
											</div>
										</li>
									<?php endforeach; ?>
								<?php endif; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- TAB 2: Learners -->
	<div x-show="tab === 'learners'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
		<div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
			<h3 class="text-lg font-semibold text-gray-800">ตารางความคืบหน้าของผู้เรียน</h3>
		</div>
		<div class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-200">
				<thead class="bg-gray-50">
					<tr>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ผู้เรียน</th>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ</th>
						<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Engagement</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ความคืบหน้า</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">เข้าเรียนล่าสุด</th>
					</tr>
				</thead>
				<tbody class="bg-white divide-y divide-gray-200" x-data="{ expandedUser: null }">
					<?php foreach ( $stats['student_table'] as $s ) : ?>
					<tr @click="expandedUser = expandedUser === <?php echo $s['user_id']; ?> ? null : <?php echo $s['user_id']; ?>" class="cursor-pointer hover:bg-gray-50 transition-colors">
						<td class="px-6 py-4 whitespace-nowrap">
							<div class="flex items-center gap-3">
								<i class="ti ti-chevron-down text-gray-400 transition-transform" :class="expandedUser === <?php echo $s['user_id']; ?> ? 'rotate-180' : ''"></i>
								<div>
									<div class="text-sm font-medium text-gray-900"><?php echo esc_html( $s['display_name'] ); ?></div>
									<div class="text-sm text-gray-500"><?php echo esc_html( $s['email'] ); ?></div>
								</div>
							</div>
						</td>
						<td class="px-6 py-4 whitespace-nowrap">
							<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $s['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
								<?php echo esc_html( $s['status'] ); ?>
							</span>
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-center">
						<?php
							$eng_scores_list = $stats['engagement']['scores'] ?? [];
							$eng_score_val = 0;
							foreach ( $eng_scores_list as $es_item ) {
								if ( $es_item['user_id'] === $s['user_id'] ) {
									$eng_score_val = $es_item['score'];
									break;
								}
							}
							$eng_c = $eng_score_val >= 70 ? 'text-green-600 bg-green-100' : ($eng_score_val >= 40 ? 'text-yellow-600 bg-yellow-100' : 'text-red-600 bg-red-100');
						?>
						<span class="inline-flex items-center justify-center w-10 h-10 rounded-full text-sm font-bold <?php echo $eng_c; ?>">
							<?php echo $eng_score_val; ?>
						</span>
					</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
							<div class="flex items-center justify-end gap-2">
								<div class="w-24 bg-gray-200 rounded-full h-2">
									<div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo esc_attr( $s['avg_progress'] ); ?>%"></div>
								</div>
								<span><?php echo esc_html( $s['avg_progress'] ); ?>%</span>
							</div>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
							<?php echo esc_html( date( 'M j, Y', strtotime( $s['last_activity'] ) ) ); ?>
						</td>
					</tr>
					<!-- Expanded Details Row -->
					<tr x-show="expandedUser === <?php echo $s['user_id']; ?>" x-cloak class="bg-gray-50/50">

						<td colspan="5" class="px-6 py-4">
							<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
								<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
									<h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
										<i class="ti ti-book text-blue-500"></i> ความคืบหน้าบทเรียน
									</h4>
									<div class="flex justify-between items-center mb-2 text-sm text-gray-600">
										<span>เรียนสำเร็จแล้ว:</span>
										<span class="font-medium"><?php echo esc_html( $s['completed_lesson'] ); ?> / <?php echo esc_html( $s['total_lesson'] ); ?> บทเรียน</span>
									</div>
									<div class="w-full bg-gray-200 rounded-full h-2.5">
										<div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo esc_attr( $s['avg_progress'] ); ?>%"></div>
									</div>
								</div>
								
								<div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100">
									<h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
										<i class="ti ti-brain text-purple-500"></i> ประสิทธิภาพแบบทดสอบ
									</h4>
									<div class="flex justify-between items-center text-sm text-gray-600 mb-2">
										<span>จำนวนครั้งที่ทำแบบทดสอบ:</span>
										<span class="font-medium"><?php echo esc_html( $s['quiz_attempts'] ); ?> ครั้ง</span>
									</div>
									<div class="flex justify-between items-center text-sm text-gray-600">
										<span>คะแนนเฉลี่ย:</span>
										<span class="font-medium <?php echo $s['quiz_avg_score'] >= 80 ? 'text-green-600' : ($s['quiz_avg_score'] >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
											<?php echo esc_html( $s['quiz_avg_score'] ); ?>%
										</span>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- TAB 3: Alerts -->
	<div x-show="tab === 'alerts'" x-cloak>
		<h3 class="text-lg font-semibold text-gray-800 mb-4">ศูนย์จัดการสำหรับ <?php echo esc_html( $course_title ); ?></h3>
		<?php if ( empty( $stats['alerts'] ) ) : ?>
			<div class="bg-green-50 text-green-800 p-4 rounded-lg flex items-center gap-3">
				<i class="ti ti-check text-xl"></i>
				<span>ยอดเยี่ยม! ไม่พบปัญหาหรือจุดที่มีคนทิ้งคอร์สเยอะในคอร์สนี้</span>
			</div>
		<?php else : ?>
			<div class="grid grid-cols-1 gap-4">
				<?php foreach ( $stats['alerts'] as $alert ) : ?>
					<div class="bg-white border-l-4 <?php echo $alert['type'] === 'danger' ? 'border-red-500' : 'border-yellow-500'; ?> rounded-r-lg shadow-sm p-4 flex justify-between items-center">
						<div class="flex items-start gap-3">
							<div class="mt-1">
								<?php if ( $alert['type'] === 'danger' ) : ?>
									<i class="ti ti-alert-triangle text-red-500 text-xl"></i>
								<?php else : ?>
									<i class="ti ti-info-circle text-yellow-500 text-xl"></i>
								<?php endif; ?>
							</div>
							<div>
								<h4 class="text-md font-bold text-gray-900"><?php echo esc_html( $alert['title'] ); ?></h4>
								<p class="text-sm text-gray-600 mt-1"><?php echo esc_html( $alert['message'] ); ?></p>
							</div>
						</div>
						<a href="<?php echo esc_url( $alert['action'] ); ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium rounded transition">
							จัดการเลย
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>[x-cloak] { display: none !important; }</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Survival Curve
	const survData = <?php echo wp_json_encode( $stats['survival_curve'] ?? array('labels'=>[], 'data'=>[]) ); ?>;
	if(document.getElementById('survivalChart') && survData.labels) {
		new Chart(document.getElementById('survivalChart').getContext('2d'), {
			type: 'line',
			data: {
				labels: survData.labels,
				datasets: [{
					label: 'Survival (%)',
					data: survData.data,
					borderColor: 'rgb(239, 68, 68)',
					backgroundColor: 'rgba(239, 68, 68, 0.1)',
					borderWidth: 2, fill: true, stepped: true
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } } }
		});
	}

	// Progress Distribution
	const progData = <?php echo wp_json_encode( $stats['progress_distribution'] ?? [] ); ?>;
	if(document.getElementById('progressChart') && Object.keys(progData).length > 0) {
		new Chart(document.getElementById('progressChart').getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: Object.keys(progData),
				datasets: [{
					data: Object.values(progData),
					backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'],
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
		});
	}

	// Quiz Score Distribution Chart
	const quizDistData = <?php echo wp_json_encode( $stats['quiz_score_distribution'] ?? [] ); ?>;
	if(document.getElementById('quizScoreDistributionChart') && Object.keys(quizDistData).length > 0) {
		new Chart(document.getElementById('quizScoreDistributionChart').getContext('2d'), {
			type: 'bar',
			data: {
				labels: Object.keys(quizDistData),
				datasets: [{
					label: 'จำนวนผู้เข้าสอบ',
					data: Object.values(quizDistData),
					backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'],
				}]
			},
			options: { responsive: true, maintainAspectRatio: false }
		});
	}

	// Pass/Fail Ratio Chart
	const passFailData = <?php echo wp_json_encode( $stats['pass_fail_ratio'] ?? [] ); ?>;
	if(document.getElementById('passFailRatioChart') && Object.keys(passFailData).length > 0) {
		new Chart(document.getElementById('passFailRatioChart').getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: Object.keys(passFailData),
				datasets: [{
					data: Object.values(passFailData),
					backgroundColor: ['#10b981', '#ef4444'], // Green for Pass, Red for Fail
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
		});
	}

	// Enrollment Chart
	const enrData = <?php echo wp_json_encode( $stats['enrollment_trend'] ?? array('labels'=>[], 'data'=>[]) ); ?>;
	if(document.getElementById('enrollmentTrendChart') && enrData.labels) {
		new Chart(document.getElementById('enrollmentTrendChart').getContext('2d'), {
			type: 'line',
			data: {
				labels: enrData.labels,
				datasets: [{
					label: 'ผู้สมัครเรียนใหม่',
					data: enrData.data,
					borderColor: 'rgb(59, 130, 246)',
					backgroundColor: 'rgba(59, 130, 246, 0.1)',
					borderWidth: 2, fill: true, tension: 0.3
				}]
			},
			options: { responsive: true, maintainAspectRatio: false }
		});
	}

	// Active Students Chart
	const actData = <?php echo wp_json_encode( $stats['active_students_trend'] ?? array('labels'=>[], 'data'=>[]) ); ?>;
	if(document.getElementById('activeStudentsTrendChart') && actData.labels) {
		new Chart(document.getElementById('activeStudentsTrendChart').getContext('2d'), {
			type: 'line',
			data: {
				labels: actData.labels,
				datasets: [{
					label: 'ผู้เข้าเรียน (Active)',
					data: actData.data,
					borderColor: 'rgb(245, 158, 11)',
					backgroundColor: 'rgba(245, 158, 11, 0.1)',
					borderWidth: 2, fill: true, tension: 0.3
				}]
			},
			options: { responsive: true, maintainAspectRatio: false }
		});
	}

	// Completion Chart
	const compData = <?php echo wp_json_encode( $stats['completion_trend'] ?? array('labels'=>[], 'data'=>[]) ); ?>;
	if(document.getElementById('completionTrendChart') && compData.labels) {
		new Chart(document.getElementById('completionTrendChart').getContext('2d'), {
			type: 'bar',
			data: {
				labels: compData.labels,
				datasets: [{
					label: 'ผู้ที่เรียนจบ',
					data: compData.data,
					backgroundColor: 'rgba(16, 185, 129, 0.8)',
				}]
			},
			options: { responsive: true, maintainAspectRatio: false }
		});
	}

	// === NEW CHARTS ===

	// Time Per Content (Horizontal Bar)
	const timeData = <?php echo wp_json_encode( $stats['time_analytics']['time_per_content'] ?? [] ); ?>;
	if(document.getElementById('timePerContentChart') && timeData.length > 0) {
		new Chart(document.getElementById('timePerContentChart').getContext('2d'), {
			type: 'bar',
			data: {
				labels: timeData.map(d => d.title.length > 30 ? d.title.substring(0, 27) + '...' : d.title),
				datasets: [{
					label: 'เวลาเฉลี่ย (นาที)',
					data: timeData.map(d => Math.round(d.avg_seconds / 60)),
					backgroundColor: 'rgba(139, 92, 246, 0.8)',
					borderRadius: 4,
				}]
			},
			options: { 
				indexAxis: 'y', 
				responsive: true, 
				maintainAspectRatio: false,
				plugins: { legend: { display: false } }
			}
		});
	}

	// Hourly Activity Chart
	const hourlyData = <?php echo wp_json_encode( $stats['device_analytics']['hourly_activity'] ?? [] ); ?>;
	if(document.getElementById('hourlyActivityChart') && Object.keys(hourlyData).length > 0) {
		const hours = Object.keys(hourlyData);
		const counts = Object.values(hourlyData);
		const maxCount = Math.max(...counts);
		new Chart(document.getElementById('hourlyActivityChart').getContext('2d'), {
			type: 'bar',
			data: {
				labels: hours,
				datasets: [{
					label: 'กิจกรรม',
					data: counts,
					backgroundColor: counts.map(c => {
						const intensity = maxCount > 0 ? c / maxCount : 0;
						return `rgba(59, 130, 246, ${0.2 + intensity * 0.8})`;
					}),
					borderRadius: 2,
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
		});
	}

	// Device Distribution Chart
	const deviceData = <?php echo wp_json_encode( $stats['device_analytics']['device_distribution'] ?? [] ); ?>;
	if(document.getElementById('deviceDistChart') && Object.keys(deviceData).length > 0) {
		new Chart(document.getElementById('deviceDistChart').getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: Object.keys(deviceData),
				datasets: [{
					data: Object.values(deviceData),
					backgroundColor: ['#3b82f6', '#f59e0b', '#10b981'],
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
		});
	}

	// Browser Distribution Chart
	const browserData = <?php echo wp_json_encode( $stats['device_analytics']['browser_distribution'] ?? [] ); ?>;
	if(document.getElementById('browserDistChart') && Object.keys(browserData).length > 0) {
		new Chart(document.getElementById('browserDistChart').getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: Object.keys(browserData),
				datasets: [{
					data: Object.values(browserData),
					backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#6b7280'],
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
		});
	}

	// Rating Distribution Chart
	const ratingDistData = <?php echo wp_json_encode( $stats['rating_analytics']['distribution'] ?? [] ); ?>;
	if(document.getElementById('ratingDistChart') && Object.keys(ratingDistData).length > 0) {
		new Chart(document.getElementById('ratingDistChart').getContext('2d'), {
			type: 'bar',
			data: {
				labels: Object.keys(ratingDistData),
				datasets: [{
					label: 'จำนวนรีวิว',
					data: Object.values(ratingDistData),
					backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#10b981'],
					borderRadius: 4,
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
		});
	}

	// Rating Trend Chart
	const ratingTrendData = <?php echo wp_json_encode( $stats['rating_analytics']['rating_trend'] ?? array('labels'=>[], 'data'=>[]) ); ?>;
	if(document.getElementById('ratingTrendChart') && ratingTrendData.labels && ratingTrendData.labels.length > 0) {
		new Chart(document.getElementById('ratingTrendChart').getContext('2d'), {
			type: 'line',
			data: {
				labels: ratingTrendData.labels,
				datasets: [{
					label: 'คะแนนเฉลี่ย',
					data: ratingTrendData.data,
					borderColor: 'rgb(245, 158, 11)',
					backgroundColor: 'rgba(245, 158, 11, 0.1)',
					borderWidth: 2, fill: true, tension: 0.3
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 5 } } }
		});
	}
});
</script>
