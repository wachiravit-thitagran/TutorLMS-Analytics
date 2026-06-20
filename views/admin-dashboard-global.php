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
				<?php esc_html_e( 'Global Learning Analytics', 'tutorlms-analytics' ); ?>
			</h1>
			<p class="text-sm text-gray-500 mt-1">ภาพรวมการเรียนรู้ของทั้งแพลตฟอร์ม</p>
		</div>
	</div>
	
	<!-- Tabs Navigation -->
	<div class="border-b border-gray-200 mb-6 bg-white rounded-t-lg px-4 pt-4 shadow-sm">
		<nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
			<a href="#" @click.prevent="tab = 'overview'" :class="tab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Overview</a>
			<a href="#" @click.prevent="tab = 'courses'" :class="tab === 'courses' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Course Performance</a>
			<a href="#" @click.prevent="tab = 'export'" :class="tab === 'export' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Export</a>
		</nav>
	</div>

	<!-- TAB 1: Overview -->
	<div x-show="tab === 'overview'" x-cloak>
		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Total Learners</h3>
				<p class="text-2xl font-bold text-gray-900"><i class="ti ti-users text-blue-500"></i> <?php echo number_format( $stats['total_students'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Total Completions</h3>
				<p class="text-2xl font-bold text-green-600"><i class="ti ti-certificate text-green-500"></i> <?php echo number_format( $stats['total_completions'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Avg Global Rating</h3>
				<p class="text-2xl font-bold text-gray-900"><i class="ti ti-star-filled text-yellow-500"></i> <?php echo esc_html( $stats['quiz_performance']['avg_score'] > 0 ? 'Active' : 'N/A' ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Net Revenue (30d)</h3>
				<p class="text-2xl font-bold text-gray-500 text-opacity-80"><i class="ti ti-report-money text-gray-400"></i> ฿<?php echo number_format( $stats['revenue']['net_revenue'] ); ?></p>
			</div>
		</div>

		<div class="grid grid-cols-1 gap-6 mb-6">
			<!-- Enrollment Graph -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">Enrollment Trend (30 Days)</h3>
				<div class="relative h-72 w-full">
					<canvas id="enrollmentTrendChart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 2: Courses -->
	<div x-show="tab === 'courses'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
		<div class="px-6 py-4 border-b border-gray-100">
			<h3 class="text-lg font-semibold text-gray-800">Course Performance</h3>
		</div>
		<div class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-200">
				<thead class="bg-gray-50">
					<tr>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Health</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Learners</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Completion</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
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
								<i class="ti ti-chart-bar"></i> View Insights
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
			<h3 class="text-lg font-semibold text-gray-800 mb-4">Export Analytics (CSV)</h3>
			<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=tutorlms_export_revenue' ) ); ?>" class="text-center block border border-gray-200 rounded-lg p-6 hover:bg-blue-50 transition cursor-pointer">
					<span class="block text-2xl mb-2 text-green-600"><i class="ti ti-report-money"></i></span>
					<span class="font-medium text-gray-900 block">Revenue Report</span>
					<span class="text-xs text-gray-500">Last 30 Days</span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=tutorlms_export_courses' ) ); ?>" class="text-center block border border-gray-200 rounded-lg p-6 hover:bg-blue-50 transition cursor-pointer">
					<span class="block text-2xl mb-2 text-indigo-600"><i class="ti ti-books"></i></span>
					<span class="font-medium text-gray-900 block">Course Performance</span>
					<span class="text-xs text-gray-500">All published courses</span>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=tutorlms_export_students' ) ); ?>" class="text-center block border border-gray-200 rounded-lg p-6 hover:bg-blue-50 transition cursor-pointer">
					<span class="block text-2xl mb-2 text-orange-600"><i class="ti ti-school"></i></span>
					<span class="font-medium text-gray-900 block">Student Roster</span>
					<span class="text-xs text-gray-500">Progress & Status</span>
				</a>
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
					label: 'New Enrollments',
					data: enrData.data,
					borderColor: 'rgb(59, 130, 246)',
					backgroundColor: 'rgba(59, 130, 246, 0.1)',
					borderWidth: 2, fill: true, tension: 0.3
				}]
			},
			options: { responsive: true, maintainAspectRatio: false }
		});
	}
});
</script>
