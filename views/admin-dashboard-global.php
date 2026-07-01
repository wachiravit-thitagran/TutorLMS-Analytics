<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $stats
 * @var array $courses
 */
?>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="wrap" x-data="{ tab: 'overview' }">
	<div class="flex justify-between items-center mb-6 mt-4">
		<div>
			<h1 class="wp-heading-inline !text-3xl !font-bold !m-0 text-gray-800">
				<?php esc_html_e( 'Tutor Analytics', 'tutorlms-analytics' ); ?>
			</h1>
			<p class="text-sm text-gray-500 mt-1">ภาพรวมการเรียนรู้ของทั้งแพลตฟอร์ม</p>
		</div>
	</div>
	
	<!-- Tabs Navigation -->
	<div class="border-b border-gray-200 mb-6 bg-white rounded-t-lg px-4 pt-4 shadow-sm">
		<nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
			<a href="#" @click.prevent="tab = 'overview'" :class="tab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">ภาพรวม (Overview)</a>
			<a href="#" @click.prevent="tab = 'courses'" :class="tab === 'courses' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">ประสิทธิภาพคอร์ส</a>
			<a href="#" @click.prevent="tab = 'revenue'" :class="tab === 'revenue' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">รายได้ (Revenue)</a>
			<a href="#" @click.prevent="tab = 'export'" :class="tab === 'export' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">ส่งออกข้อมูล</a>
		</nav>
	</div>

	<!-- TAB 1: Overview -->
	<div x-show="tab === 'overview'" x-cloak>
		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">ผู้เรียนทั้งหมด</h3>
				<p class="text-2xl font-bold text-gray-900"><i class="ti ti-users text-blue-500"></i> <?php echo number_format( $stats['total_students'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">เรียนจบทั้งหมด</h3>
				<p class="text-2xl font-bold text-green-600"><i class="ti ti-certificate text-green-500"></i> <?php echo number_format( $stats['total_completions'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">คะแนนรีวิวเฉลี่ย</h3>
				<p class="text-2xl font-bold text-gray-900"><i class="ti ti-star-filled text-yellow-500"></i> <?php echo esc_html( $stats['quiz_performance']['avg_score'] > 0 ? 'Active' : 'ไม่มีข้อมูล' ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">รายได้สุทธิ (30 วัน)</h3>
				<p class="text-2xl font-bold text-gray-500 text-opacity-80"><i class="ti ti-report-money text-gray-400"></i> ฿<?php echo number_format( $stats['revenue']['net_revenue'] ); ?></p>
			</div>
		</div>

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

		<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
			<!-- Course Popularity Graph -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">สัดส่วนความนิยมของคอร์ส</h3>
				<div class="relative h-72 w-full">
					<canvas id="coursePopularityChart"></canvas>
				</div>
			</div>
			<!-- Activity by Day Graph -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">ความเคลื่อนไหวแยกตามวัน (90 วัน)</h3>
				<div class="relative h-72 w-full">
					<canvas id="activityByDayChart"></canvas>
				</div>
			</div>
		</div>

		<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">สัดส่วนอุปกรณ์ที่ใช้เรียน</h3>
				<div class="relative h-64 w-full">
					<canvas id="globalDeviceChart"></canvas>
				</div>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">สัดส่วน Browser</h3>
				<div class="relative h-64 w-full">
					<canvas id="globalBrowserChart"></canvas>
				</div>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">ช่วงเวลาที่ผู้เรียนเข้าเรียน</h3>
				<div class="relative h-64 w-full">
					<canvas id="globalHourlyChart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 2: Courses -->
	<div x-show="tab === 'courses'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
		<div class="px-6 py-4 border-b border-gray-100">
			<h3 class="text-lg font-semibold text-gray-800">ประสิทธิภาพคอร์ส (Course Performance)</h3>
		</div>
		<div class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-200">
				<thead class="bg-gray-50">
					<tr>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">คอร์สเรียน</th>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider relative group cursor-help">
							สถานะ (Health) <i class="ti ti-info-circle ml-1"></i>
							<div class="absolute bottom-full left-0 mb-2 hidden group-hover:block w-64 p-3 bg-gray-800 text-white text-xs rounded shadow-lg z-10 whitespace-normal normal-case font-normal leading-relaxed">
								<strong>สูตรคำนวณ Health (เต็ม 100):</strong><br>
								• <strong>อัตราเรียนจบ (50):</strong> <code>% เรียนจบ &times; 0.5</code><br>
								• <strong>คะแนนรีวิว (40):</strong> <code>(ดาว / 5) &times; 40</code><br>
								• <strong>ผู้ลงทะเบียน (10):</strong> <code>มีผู้เรียนอย่างน้อย 1 คน = 10</code><br>
								<em>คะแนนรวม = นำทั้ง 3 ส่วนมาบวกกัน</em>
							</div>
						</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ผู้เรียน</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">เรียนจบ</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
					</tr>
				</thead>
				<tbody class="bg-white divide-y divide-gray-200">
					<?php foreach ( $stats['course_performance'] as $cp ) : ?>
					<tr>
						<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo esc_html( $cp['title'] ); ?></td>
						<td class="px-6 py-4 whitespace-nowrap">
							<div class="w-full bg-gray-200 rounded-full h-2.5">
								<div class="bg-<?php echo $cp['health'] > 75 ? 'green' : ($cp['health'] > 50 ? 'yellow' : 'red'); ?>-500 h-2.5 rounded-full" style="width: <?php echo esc_attr( $cp['health'] ); ?>%"></div>
							</div>
							<span class="text-xs text-gray-500 mt-1 block"><?php echo esc_html( $cp['health'] ); ?>/100</span>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><?php echo esc_html( number_format( $cp['learners'] ) ); ?></td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><?php echo esc_html( $cp['completion_rate'] ); ?>%</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-right">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=tutorlms-analytics&course_id=' . $cp['id'] ) ); ?>" class="text-blue-600 hover:text-blue-900 font-medium inline-flex items-center gap-1">
								<i class="ti ti-chart-bar"></i> ดูข้อมูลเชิงลึก
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- TAB 3: Export -->
	<div x-show="tab === 'export'" x-cloak>
		<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
			<h3 class="text-lg font-semibold text-gray-800 mb-4">ส่งออกข้อมูลสถิติ (CSV)</h3>
			<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=tutorlms_export_revenue' ) ); ?>" class="text-center block border border-gray-200 rounded-lg p-6 hover:bg-blue-50 transition cursor-pointer">
					<span class="block text-2xl mb-2 text-green-600"><i class="ti ti-report-money"></i></span>
					<span class="font-medium text-gray-900 block">รายงานรายได้</span>
					<span class="text-xs text-gray-500">ย้อนหลัง 30 วัน</span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=tutorlms_export_courses' ) ); ?>" class="text-center block border border-gray-200 rounded-lg p-6 hover:bg-blue-50 transition cursor-pointer">
					<span class="block text-2xl mb-2 text-indigo-600"><i class="ti ti-books"></i></span>
					<span class="font-medium text-gray-900 block">ประสิทธิภาพคอร์ส</span>
					<span class="text-xs text-gray-500">คอร์สที่เปิดสอนทั้งหมด</span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=tutorlms_export_students' ) ); ?>" class="text-center block border border-gray-200 rounded-lg p-6 hover:bg-blue-50 transition cursor-pointer">
					<span class="block text-2xl mb-2 text-orange-600"><i class="ti ti-school"></i></span>
					<span class="font-medium text-gray-900 block">รายชื่อผู้เรียน</span>
					<span class="text-xs text-gray-500">ความคืบหน้าและสถานะ</span>
				</a>
			</div>
		</div>
	</div>

	<!-- TAB 4: Revenue -->
	<div x-show="tab === 'revenue'" x-cloak>
		<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 mb-6">
			<h3 class="text-lg font-semibold text-gray-800 mb-4">แนวโน้มรายได้ (30 วันล่าสุด)</h3>
			<div class="relative h-80 w-full">
				<canvas id="revenueTrendChart"></canvas>
			</div>
		</div>
	</div>
</div>

<style>[x-cloak] { display: none !important; }</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
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

	// Course Popularity Chart
	const popData = <?php echo wp_json_encode( $stats['course_popularity'] ?? [] ); ?>;
	if(document.getElementById('coursePopularityChart') && Object.keys(popData).length > 0) {
		new Chart(document.getElementById('coursePopularityChart').getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: Object.keys(popData),
				datasets: [{
					data: Object.values(popData),
					backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#6b7280'],
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
		});
	}

	// Activity by Day Chart
	const actDayData = <?php echo wp_json_encode( $stats['activity_by_day'] ?? [] ); ?>;
	if(document.getElementById('activityByDayChart') && Object.keys(actDayData).length > 0) {
		new Chart(document.getElementById('activityByDayChart').getContext('2d'), {
			type: 'bar',
			data: {
				labels: Object.keys(actDayData),
				datasets: [{
					label: 'กิจกรรม',
					data: Object.values(actDayData),
					backgroundColor: 'rgba(139, 92, 246, 0.8)',
				}]
			},
			options: { responsive: true, maintainAspectRatio: false }
		});
	}

	// Revenue Trend Chart
	const revData = <?php echo wp_json_encode( $stats['revenue']['trend'] ?? array('labels'=>[], 'data'=>[]) ); ?>;
	if(document.getElementById('revenueTrendChart') && revData.labels && revData.labels.length > 0) {
		new Chart(document.getElementById('revenueTrendChart').getContext('2d'), {
			type: 'line',
			data: {
				labels: revData.labels,
				datasets: [{
					label: 'รายได้ (THB)',
					data: revData.data,
					borderColor: 'rgb(16, 185, 129)',
					backgroundColor: 'rgba(16, 185, 129, 0.1)',
					borderWidth: 2, fill: true, tension: 0.3
				}]
			},
			options: { responsive: true, maintainAspectRatio: false }
		});
	}

	// Global Device Distribution
	const gDeviceData = <?php echo wp_json_encode( $stats['device_analytics']['device_distribution'] ?? [] ); ?>;
	if(document.getElementById('globalDeviceChart') && Object.keys(gDeviceData).length > 0) {
		new Chart(document.getElementById('globalDeviceChart').getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: Object.keys(gDeviceData),
				datasets: [{ data: Object.values(gDeviceData), backgroundColor: ['#3b82f6', '#f59e0b', '#10b981'] }]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
		});
	}

	// Global Browser Distribution
	const gBrowserData = <?php echo wp_json_encode( $stats['device_analytics']['browser_distribution'] ?? [] ); ?>;
	if(document.getElementById('globalBrowserChart') && Object.keys(gBrowserData).length > 0) {
		new Chart(document.getElementById('globalBrowserChart').getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: Object.keys(gBrowserData),
				datasets: [{ data: Object.values(gBrowserData), backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#6b7280'] }]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
		});
	}

	// Global Hourly Activity
	const gHourlyData = <?php echo wp_json_encode( $stats['device_analytics']['hourly_activity'] ?? [] ); ?>;
	if(document.getElementById('globalHourlyChart') && Object.keys(gHourlyData).length > 0) {
		const gHrs = Object.keys(gHourlyData);
		const gCts = Object.values(gHourlyData);
		const gMax = Math.max(...gCts);
		new Chart(document.getElementById('globalHourlyChart').getContext('2d'), {
			type: 'bar',
			data: {
				labels: gHrs,
				datasets: [{
					label: 'กิจกรรม',
					data: gCts,
					backgroundColor: gCts.map(c => { const i = gMax > 0 ? c / gMax : 0; return `rgba(139, 92, 246, ${0.2 + i * 0.8})`; }),
					borderRadius: 2,
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
		});
	}
});
</script>
