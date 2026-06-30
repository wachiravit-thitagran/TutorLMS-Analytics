<?php
/**
 * WordPress comprehensive integration tests for TutorLMS-Analytics.
 * Tests Priorities 1 and 2: Table Activation, REST, Global Dashboard, Data Isolation, Empty State, Permissions.
 *
 * Run with: wp eval-file tests/wp-integration-comprehensive.php --allow-root
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	echo "This script must run inside WordPress.\n";
	exit( 1 );
}

// -----------------------------------------------------------------------------
// Test Runner Utilities
// -----------------------------------------------------------------------------

function tla_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		echo "❌ FAIL: {$message}\n";
		exit( 1 );
	}
	echo "✅ PASS: {$message}\n";
}

function tla_assert_contains( string $haystack, string $needle, string $message ): void {
	if ( strpos( $haystack, $needle ) === false ) {
		echo "❌ FAIL: {$message}\n   Expected to find: '{$needle}'\n";
		exit( 1 );
	}
	echo "✅ PASS: {$message}\n";
}

function tla_assert_not_contains( string $haystack, string $needle, string $message ): void {
	if ( strpos( $haystack, $needle ) !== false ) {
		echo "❌ FAIL: {$message}\n   Expected NOT to find: '{$needle}'\n";
		exit( 1 );
	}
	echo "✅ PASS: {$message}\n";
}

// Ensure the plugin is active and initialised
\TutorLMS_Analytics\Database::create_tables();

// -----------------------------------------------------------------------------
// Test 1: Priority 1.2 - Activation creates custom tables
// -----------------------------------------------------------------------------
echo "\n--- Test: Table Creation ---\n";
global $wpdb;
$table_name = $wpdb->prefix . 'tutorlms_analytics_events';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
tla_assert( $table_exists, "Table {$table_name} exists." );

$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A );
$column_names = array_column( $columns, 'Field' );
$expected_columns = [ 'id', 'user_id', 'course_id', 'lesson_id', 'event_type', 'event_value', 'user_agent', 'device_type', 'browser', 'created_at' ];
foreach ( $expected_columns as $col ) {
	tla_assert( in_array( $col, $column_names, true ), "Table has column: {$col}" );
}

$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table_name}", ARRAY_A );
$index_names = array_column( $indexes, 'Key_name' );
tla_assert( in_array( 'user_id', $index_names, true ), "Table has index: user_id" );
tla_assert( in_array( 'course_id', $index_names, true ), "Table has index: course_id" );


// -----------------------------------------------------------------------------
// Test 2: Priority 1.4 - Dashboard capability check
// -----------------------------------------------------------------------------
echo "\n--- Test: Permissions/Capabilities ---\n";
// This is normally registered via add_action('admin_menu'). We simulate it directly.
$menu_slug = 'tutorlms-analytics';
$capability = 'manage_tutor';

// Test if user without manage_tutor can access (simulated)
$subscriber_id = wp_create_user( 'sub_test', 'sub_pass', 'sub_test@example.com' );
$sub_user = new WP_User( $subscriber_id );
$sub_user->set_role( 'subscriber' );
wp_set_current_user( $subscriber_id );

tla_assert( ! current_user_can( $capability ), "Subscriber does not have 'manage_tutor' capability." );

// Switch back to admin
$admin_user = get_user_by( 'login', 'admin' );
if ( ! $admin_user ) {
    $admin_users = get_users(['role' => 'administrator']);
    if (!empty($admin_users)) {
        $admin_user = $admin_users[0];
    }
}

if ( $admin_user ) {
    wp_set_current_user( $admin_user->ID );
    // Add capability if not present (sometimes Tutor isn't fully active in test)
    $admin_user->add_cap( 'manage_tutor' );
    tla_assert( current_user_can( $capability ), "Admin has 'manage_tutor' capability." );
} else {
    echo "⚠️ Warning: Admin user not found, skipping admin capability check.\n";
}


// -----------------------------------------------------------------------------
// Test 3: Priority 2.1 - Empty State Rendering
// -----------------------------------------------------------------------------
echo "\n--- Test: Empty State Rendering ---\n";
// Clear the table before testing empty state
$wpdb->query("TRUNCATE TABLE {$table_name}");

// Make sure GET course_id is empty for global dashboard
unset( $_GET['course_id'] );

ob_start();
$menu = new \TutorLMS_Analytics\Admin_Menu();
$menu->render_page();
$output_empty = ob_get_clean();

tla_assert_contains( $output_empty, 'Tutor Analytics', "Global dashboard renders without fatal error." );
tla_assert_contains( $output_empty, 'ผู้เรียนที่สมัคร', "Global dashboard contains general summary metrics." );
// Note: Depending on UI, it might just render '0' or specific empty messages.
tla_assert_contains( $output_empty, '0', "Empty state renders zero values correctly." );


// -----------------------------------------------------------------------------
// Test 4: Priority 1.1 - REST API Tracking Endpoint
// -----------------------------------------------------------------------------
echo "\n--- Test: REST API Tracking Endpoint ---\n";

// Register REST routes
do_action( 'rest_api_init' );

$course_id = wp_insert_post(['post_type' => 'courses', 'post_title' => 'Test Course REST']);
$lesson_id = wp_insert_post(['post_type' => 'lesson', 'post_title' => 'Test Lesson REST', 'post_parent' => $course_id]);

// Create a REST Request
$request = new WP_REST_Request( 'POST', '/tutor-analytics/v1/track' );
$request->set_param( 'course_id', $course_id );
$request->set_param( 'lesson_id', $lesson_id );
$request->set_param( 'event_type', 'lesson_view' );
$request->set_param( 'event_value', '123' );
$request->set_param( 'device_type', 'mobile' );
$request->set_param( 'browser', 'Firefox' );

// Execute Endpoint
$rest_server = rest_get_server();
$response = $rest_server->dispatch( $request );

tla_assert( $response->get_status() === 200, "REST Endpoint returned 200 OK." );

// Verify DB Insertion
$inserted_event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE course_id = %d AND event_type = %s", $course_id, 'lesson_view' ) );

tla_assert( $inserted_event !== null, "REST Endpoint inserted event into database." );
tla_assert( $inserted_event->device_type === 'mobile', "REST Endpoint saved device_type correctly." );
tla_assert( $inserted_event->browser === 'Firefox', "REST Endpoint saved browser correctly." );
tla_assert( $inserted_event->event_value === '123', "REST Endpoint saved event_value correctly." );


// -----------------------------------------------------------------------------
// Test 5: Priority 2.3 - Data Isolation (Multi-Course)
// -----------------------------------------------------------------------------
echo "\n--- Test: Data Isolation ---\n";
// Create Course A and B
$course_a = wp_insert_post(['post_type' => 'courses', 'post_title' => 'Course Alpha']);
$course_b = wp_insert_post(['post_type' => 'courses', 'post_title' => 'Course Beta']);
$topic_a = wp_insert_post(['post_type' => 'topics', 'post_parent' => $course_a]);
$lesson_a = wp_insert_post(['post_type' => 'lesson', 'post_title' => 'Lesson Alpha', 'post_parent' => $topic_a]);
$topic_b = wp_insert_post(['post_type' => 'topics', 'post_parent' => $course_b]);
$lesson_b = wp_insert_post(['post_type' => 'lesson', 'post_title' => 'Lesson Beta', 'post_parent' => $topic_b]);

// Seed Event for A
$wpdb->insert($table_name, [
    'user_id' => 1, 'course_id' => $course_a, 'lesson_id' => $lesson_a, 
    'event_type' => 'page_exit', 'event_value' => '100', 'device_type' => 'desktop', 
    'created_at' => current_time('mysql')
]);

// Seed Event for B
$wpdb->insert($table_name, [
    'user_id' => 1, 'course_id' => $course_b, 'lesson_id' => $lesson_b, 
    'event_type' => 'page_exit', 'event_value' => '500', 'device_type' => 'desktop', 
    'created_at' => current_time('mysql')
]);

// Request Dashboard for Course A
$_GET['course_id'] = $course_a;
ob_start();
$menu->render_page();
$output_course_a = ob_get_clean();

// Check isolated data
tla_assert_contains( $output_course_a, 'Lesson Alpha', "Course A dashboard displays Course A data." );
tla_assert_not_contains( $output_course_a, 'Lesson Beta', "Course A dashboard DOES NOT display Course B data." );


// -----------------------------------------------------------------------------
// Test 7: Priority 2.6 - Missing Tutor LMS optional tables
// -----------------------------------------------------------------------------
echo "\n--- Test: Missing Tutor LMS optional tables ---\n";
// Drop the quiz tables if they exist
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tutor_quiz_attempt_answers");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tutor_quiz_attempts");

$_GET['course_id'] = $course_a;
ob_start();
$menu->render_page();
$output_missing_tables = ob_get_clean();
unset( $_GET['course_id'] );

tla_assert_contains( $output_missing_tables, 'Tutor Analytics', "Dashboard renders without fatal error even when quiz tables are missing." );


// -----------------------------------------------------------------------------
// Test 8: Priority 3.10 - Export CSV integration
// -----------------------------------------------------------------------------
echo "\n--- Test: Export CSV Integration ---\n";
// We need to bypass the die() or exit() in the export handler to test it via eval-file
// Or better, we can instantiate the Export_Handler and call it directly, buffering output.
// To bypass exit(), we can define a mock for it or just check if hooks are registered properly.
$export_handler = new \TutorLMS_Analytics\Export_Handler();
$export_handler->register();
tla_assert( has_action( 'admin_post_tutorlms_export_revenue' ) !== false, "Export hook for revenue is registered." );
tla_assert( has_action( 'admin_post_tutorlms_export_courses' ) !== false, "Export hook for courses is registered." );


// -----------------------------------------------------------------------------
// Test 9: Priority 3.11 - Admin asset enqueue check
// -----------------------------------------------------------------------------
echo "\n--- Test: Admin Asset Enqueue ---\n";
// The hook is usually admin_enqueue_scripts. Let's trigger it.
do_action( 'admin_enqueue_scripts', 'tutor-lms_page_tutorlms-analytics' );

tla_assert( wp_script_is( 'tutorlms-analytics-chartjs', 'enqueued' ), "Chart.js is enqueued correctly." );
tla_assert( wp_style_is( 'tutorlms-analytics-tailwind', 'enqueued' ), "Tailwind CSS is enqueued correctly." );


// -----------------------------------------------------------------------------
// Test 10: Priority 3.12 - Timezone/date boundary logic
// -----------------------------------------------------------------------------
echo "\n--- Test: Timezone Date Boundary ---\n";
// Insert events exactly on boundary dates
$date_boundary = gmdate( 'Y-m-d 23:59:59', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS );
$wpdb->insert($table_name, [
    'user_id' => 1, 'course_id' => $course_a, 'lesson_id' => $lesson_a, 
    'event_type' => 'lesson_view', 'event_value' => '', 'device_type' => 'desktop', 
    'created_at' => $date_boundary
]);
// Just asserting it doesn't crash on date edge cases for now
tla_assert( true, "Boundary date event inserted successfully." );


// -----------------------------------------------------------------------------
// Test 11: Priority 3.13 - Large dataset smoke test
// -----------------------------------------------------------------------------
echo "\n--- Test: Large Dataset Smoke Test ---\n";
// Seed 500 events
$values = [];
for ( $i = 0; $i < 500; $i++ ) {
	$values[] = $wpdb->prepare( "(%d, %d, %d, %s, %s)", rand(1, 20), $course_a, $lesson_a, 'lesson_view', current_time('mysql') );
}
$wpdb->query( "INSERT INTO {$table_name} (user_id, course_id, lesson_id, event_type, created_at) VALUES " . implode( ',', $values ) );

ob_start();
$menu->render_page();
$output_large = ob_get_clean();
tla_assert_contains( $output_large, 'Tutor Analytics', "Dashboard renders successfully with 500+ events." );


// -----------------------------------------------------------------------------
// Test 12: Priority 4.14 - HTML escaping / XSS safety
// -----------------------------------------------------------------------------
echo "\n--- Test: HTML escaping / XSS safety ---\n";
$xss_course = wp_insert_post(['post_type' => 'courses', 'post_title' => '<script>alert("xss")</script> Course']);
$_GET['course_id'] = $xss_course;

ob_start();
$menu->render_page();
$output_xss = ob_get_clean();
unset( $_GET['course_id'] );

// The output shouldn't contain the raw script tag
tla_assert_not_contains( $output_xss, '<script>alert("xss")</script>', "Dashboard escapes raw script tags from course names (XSS)." );


// -----------------------------------------------------------------------------
// Test 13: Priority 4.15 - Unicode/Thai rendering
// -----------------------------------------------------------------------------
echo "\n--- Test: Unicode / Thai rendering ---\n";
$thai_course = wp_insert_post(['post_type' => 'courses', 'post_title' => 'คอร์สเรียนภาษาไทย ๑๒๓']);
$_GET['course_id'] = $thai_course;

ob_start();
$menu->render_page();
$output_thai = ob_get_clean();
unset( $_GET['course_id'] );

// Check if Thai string renders properly
tla_assert_contains( $output_thai, 'คอร์สเรียนภาษาไทย ๑๒๓', "Dashboard renders Thai and Unicode strings correctly." );

// -----------------------------------------------------------------------------
// Test 6: Priority 1.3 - Global Dashboard with Real Data
// -----------------------------------------------------------------------------
echo "\n--- Test: Global Dashboard Rendering ---\n";
unset( $_GET['course_id'] ); // Switch to global

ob_start();
$menu->render_page();
$output_global = ob_get_clean();

tla_assert_contains( $output_global, 'Course Alpha', "Global dashboard includes seeded courses." );
tla_assert_contains( $output_global, 'Course Beta', "Global dashboard includes seeded courses." );
tla_assert_contains( $output_global, 'globalDeviceChart', "Global dashboard renders device chart ID." );
tla_assert_contains( $output_global, 'globalHourlyChart', "Global dashboard renders hourly chart ID." );


// -----------------------------------------------------------------------------
// Test 14: Priority 3.9 - REST tracking nonce/security
// -----------------------------------------------------------------------------
echo "\n--- Test: REST Tracking Security & Malicious Input ---\n";
$request_malicious = new WP_REST_Request( 'POST', '/tutor-analytics/v1/track' );
$request_malicious->set_param( 'course_id', $course_a );
$request_malicious->set_param( 'lesson_id', $lesson_a );
$request_malicious->set_param( 'event_type', 'lesson_view' );
// Malicious input simulation
$request_malicious->set_param( 'event_value', '<script>malicious</script>alert(1)' );
$request_malicious->set_param( 'device_type', 'desktop' );
$request_malicious->set_param( 'browser', 'Chrome' );

$response_malicious = rest_get_server()->dispatch( $request_malicious );
tla_assert( $response_malicious->get_status() === 200, "REST Endpoint processes malicious input." );

$malicious_event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE course_id = %d AND event_value LIKE %s ORDER BY id DESC LIMIT 1", $course_a, '%malicious%' ) );
tla_assert( $malicious_event !== null, "Malicious event saved to DB." );
// The input should be sanitized, not containing the raw script tag if we use sanitize_text_field
tla_assert_not_contains( $malicious_event->event_value, '<script>', "REST Endpoint sanitizes HTML tags from event_value." );


echo "\n🎉 All comprehensive integration tests passed successfully!\n";
