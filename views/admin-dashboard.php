<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $stats
 * @var array $courses
 * @var int   $course_id
 */
?>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="wrap" x-data="{ tab: 'overview' }">
	<div class="flex justify-between items-center mb-6 mt-4">
		<h1 class="wp-heading-inline !text-3xl !font-bold !m-0 text-gray-800">
			<?php esc_html_e( 'TutorLMS Analytics BI', 'tutorlms-analytics' ); ?>
		</h1>
		
		<form method="GET" action="">
			<input type="hidden" name="page" value="tutorlms-analytics">
			<select name="course_id" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500 py-2 pl-3 pr-10">
				<option value="0"><?php esc_html_e( '-- ทุกคอร์ส (Global) --', 'tutorlms-analytics' ); ?></option>
				<?php foreach ( $courses as $c ) : ?>
					<option value="<?php echo esc_attr( $c['ID'] ); ?>" <?php selected( $course_id, $c['ID'] ); ?>>
						<?php echo esc_html( $c['post_title'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>
	
	<!-- Tabs Navigation -->
	<div class="border-b border-gray-200 mb-6 bg-white rounded-t-lg px-4 pt-4 shadow-sm">
		<nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
			<a href="#" @click.prevent="tab = 'overview'" :class="tab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Overview</a>
			<a href="#" @click.prevent="tab = 'courses'" :class="tab === 'courses' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Course Performance</a>
			<a href="#" @click.prevent="tab = 'learners'" :class="tab === 'learners' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Students & Progress</a>
			<a href="#" @click.prevent="tab = 'engagement'" :class="tab === 'engagement' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Engagement Funnels</a>
			<a href="#" @click.prevent="tab = 'alerts'" :class="tab === 'alerts' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">
				Action Center
				<?php if ( ! empty( $stats['alerts'] ) ) : ?>
					<span class="bg-red-100 text-red-600 ml-1 py-0.5 px-2 rounded-full text-xs"><?php echo count( $stats['alerts'] ); ?></span>
				<?php endif; ?>
			</a>
			<a href="#" @click.prevent="tab = 'export'" :class="tab === 'export' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Export</a>
		</nav>
	</div>

	<!-- TAB 1: Overview -->
	<div x-show="tab === 'overview'" x-cloak>
		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Gross Revenue (30d)</h3>
				<p class="text-2xl font-bold text-gray-900">฿<?php echo number_format( $stats['revenue']['gross_revenue'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Net Revenue (30d)</h3>
				<p class="text-2xl font-bold text-green-600">฿<?php echo number_format( $stats['revenue']['net_revenue'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">New Enrollments</h3>
				<p class="text-2xl font-bold text-gray-900"><?php echo number_format( $stats['total_students'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Completions</h3>
				<p class="text-2xl font-bold text-gray-900"><?php echo number_format( $stats['total_completions'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Avg Global Rating</h3>
				<p class="text-2xl font-bold text-yellow-500"><i class="ti ti-star-filled text-yellow-500"></i> <?php echo esc_html( $stats['quiz_performance']['avg_score'] > 0 ? 'Active' : 'N/A' ); ?></p>
			</div>
		</div>

		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
			<!-- Revenue Graph -->
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">Revenue Trend (30 Days)</h3>
				<div class="relative h-72 w-full">
					<canvas id="revenueChart"></canvas>
				</div>
			</div>
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
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
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
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><i class="ti ti-star-filled text-yellow-400"></i> <?php echo esc_html( $cp['avg_rating'] ); ?></td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium text-right">฿<?php echo esc_html( number_format( $cp['revenue'] ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- TAB 3: Learners -->
	<div x-show="tab === 'learners'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
		<div class="px-6 py-4 border-b border-gray-100">
			<h3 class="text-lg font-semibold text-gray-800">Student Progress & Status</h3>
		</div>
		<div class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-200">
				<thead class="bg-gray-50">
					<tr>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Courses</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Progress</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
					</tr>
				</thead>
				<tbody class="bg-white divide-y divide-gray-200">
					<?php foreach ( $stats['student_table'] as $s ) : ?>
					<tr>
						<td class="px-6 py-4 whitespace-nowrap">
							<div class="text-sm font-medium text-gray-900"><?php echo esc_html( $s['display_name'] ); ?></div>
							<div class="text-sm text-gray-500"><?php echo esc_html( $s['email'] ); ?></div>
						</td>
						<td class="px-6 py-4 whitespace-nowrap">
							<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $s['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
								<?php echo esc_html( $s['status'] ); ?>
							</span>
						</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><?php echo esc_html( $s['courses_taken'] ); ?></td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><?php echo esc_html( $s['avg_progress'] ); ?>%</td>
						<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right"><?php echo esc_html( date( 'M j, Y', strtotime( $s['last_activity'] ) ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- TAB 4: Engagement (Existing Advanced Tools) -->
	<div x-show="tab === 'engagement'" x-cloak>
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-4">Progress Distribution</h3>
				<div class="relative h-72 w-full">
					<canvas id="progressChart"></canvas>
				</div>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">Kaplan-Meier Survival</h3>
				<p class="text-xs text-gray-500 mb-4"><?php echo esc_html( $stats['survival_course_name'] ); ?></p>
				<div class="relative h-72 w-full">
					<canvas id="survivalChart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 5: Alerts (Action Center) -->
	<div x-show="tab === 'alerts'" x-cloak>
		<h3 class="text-lg font-semibold text-gray-800 mb-4">Action Center</h3>
		<?php if ( empty( $stats['alerts'] ) ) : ?>
			<div class="bg-green-50 text-green-800 p-4 rounded-lg">All clear! No pending alerts.</div>
		<?php else : ?>
			<div class="grid grid-cols-1 gap-4">
				<?php foreach ( $stats['alerts'] as $alert ) : ?>
					<div class="bg-white border-l-4 <?php echo $alert['type'] === 'danger' ? 'border-red-500' : 'border-yellow-500'; ?> rounded-r-lg shadow-sm p-4 flex justify-between items-center">
						<div>
							<h4 class="text-md font-bold text-gray-900"><?php echo esc_html( $alert['title'] ); ?></h4>
							<p class="text-sm text-gray-600 mt-1"><?php echo esc_html( $alert['message'] ); ?></p>
						</div>
						<a href="<?php echo esc_url( $alert['action'] ); ?>" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 text-sm font-medium rounded transition">
							Take Action
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<!-- TAB 6: Export -->
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
	// Revenue Chart
	const revData = <?php echo wp_json_encode( $stats['revenue']['trend'] ); ?>;
	if(document.getElementById('revenueChart') && revData.labels.length > 0) {
		new Chart(document.getElementById('revenueChart').getContext('2d'), {
			type: 'bar',
			data: {
				labels: revData.labels,
				datasets: [{
					label: 'Net Revenue (฿)',
					data: revData.data,
					backgroundColor: 'rgba(16, 185, 129, 0.8)',
					borderRadius: 4
				}]
			},
			options: { responsive: true, maintainAspectRatio: false }
		});
	}

	// Enrollment Chart
	const enrData = <?php echo wp_json_encode( $stats['enrollment_trend'] ); ?>;
	if(document.getElementById('enrollmentTrendChart')) {
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

	// Progress Distribution
	const progData = <?php echo wp_json_encode( $stats['progress_distribution'] ); ?>;
	if(document.getElementById('progressChart')) {
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

	// Survival Curve
	const survData = <?php echo wp_json_encode( $stats['survival_curve'] ?? array() ); ?>;
	if(document.getElementById('survivalChart') && survData.labels) {
		new Chart(document.getElementById('survivalChart').getContext('2d'), {
			type: 'line',
			data: {
				labels: survData.labels,
				datasets: [{
					label: 'Survival (%)',
					data: survData.data,
					borderColor: 'rgb(239, 68, 68)',
					borderWidth: 2, fill: false, stepped: true
				}]
			},
			options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } } }
		});
	}
});
</script>
