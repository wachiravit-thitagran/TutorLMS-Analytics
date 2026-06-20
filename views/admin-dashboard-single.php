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
				<i class="ti ti-arrow-left mr-1"></i> Back to Global Overview
			</a>
		</div>
		<h1 class="!text-3xl !font-bold !m-0 text-gray-800 flex items-center gap-3">
			<?php echo esc_html( $course_title ); ?>
			<span class="text-sm bg-blue-100 text-blue-800 px-3 py-1 rounded-full font-medium tracking-wide">Course Analytics</span>
		</h1>
	</div>
	
	<!-- Tabs Navigation -->
	<div class="border-b border-gray-200 mb-6 bg-white rounded-t-lg px-4 pt-4 shadow-sm">
		<nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
			<a href="#" @click.prevent="tab = 'insights'" :class="tab === 'insights' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Learning Insights</a>
			<a href="#" @click.prevent="tab = 'learners'" :class="tab === 'learners' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Students Roster</a>
			<a href="#" @click.prevent="tab = 'alerts'" :class="tab === 'alerts' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">
				Action Center
				<?php if ( ! empty( $stats['alerts'] ) ) : ?>
					<span class="bg-red-100 text-red-600 ml-1 py-0.5 px-2 rounded-full text-xs"><?php echo count( $stats['alerts'] ); ?></span>
				<?php endif; ?>
			</a>
		</nav>
	</div>

	<!-- TAB 1: Insights -->
	<div x-show="tab === 'insights'" x-cloak>
		<!-- Highlight Cards -->
		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-blue-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Enrolled Students</h3>
				<p class="text-2xl font-bold text-gray-900"><?php echo number_format( $stats['total_students'] ); ?></p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-green-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Completion Rate</h3>
				<p class="text-2xl font-bold text-gray-900"><?php echo esc_html( $stats['course_performance'][0]['completion_rate'] ?? 0 ); ?>%</p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-yellow-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Avg Quiz Score</h3>
				<p class="text-2xl font-bold text-gray-900"><?php echo esc_html( $stats['quiz_performance']['avg_score'] ?? 0 ); ?>%</p>
			</div>
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-5 border-l-4 border-l-red-500">
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Avg Drop-off Rate</h3>
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
		</div>

		<!-- Deep Insights Charts -->
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 relative">
				<div class="absolute top-4 right-4 bg-blue-50 text-blue-700 text-xs px-2 py-1 rounded font-medium">Critical Metric</div>
				<h3 class="text-lg font-semibold text-gray-800 mb-1">Kaplan-Meier Survival Curve</h3>
				<p class="text-sm text-gray-500 mb-4">Shows exactly where students drop off. A steep drop means content is too hard or boring.</p>
				<div class="relative h-72 w-full">
					<canvas id="survivalChart"></canvas>
				</div>
			</div>
			
			<div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
				<h3 class="text-lg font-semibold text-gray-800 mb-1">Progress Distribution</h3>
				<p class="text-sm text-gray-500 mb-4">Breakdown of current student progress statuses.</p>
				<div class="relative h-72 w-full">
					<canvas id="progressChart"></canvas>
				</div>
			</div>
		</div>
	</div>

	<!-- TAB 2: Learners -->
	<div x-show="tab === 'learners'" x-cloak class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
		<div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
			<h3 class="text-lg font-semibold text-gray-800">Student Progress Roster</h3>
		</div>
		<div class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-200">
				<thead class="bg-gray-50">
					<tr>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
						<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
						<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
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
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- TAB 3: Alerts -->
	<div x-show="tab === 'alerts'" x-cloak>
		<h3 class="text-lg font-semibold text-gray-800 mb-4">Action Center for <?php echo esc_html( $course_title ); ?></h3>
		<?php if ( empty( $stats['alerts'] ) ) : ?>
			<div class="bg-green-50 text-green-800 p-4 rounded-lg flex items-center gap-3">
				<i class="ti ti-check text-xl"></i>
				<span>All clear! No pending alerts or high drop-off points detected for this course.</span>
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
							Take Action
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
});
</script>
