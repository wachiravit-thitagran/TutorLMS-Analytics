<?php
/**
 * WordPress integration smoke test for advanced learning analytics.
 *
 * Run with: wp eval-file bin/wp-integration-advanced-analytics.php --allow-root
 */


if ( ! defined( 'ABSPATH' ) ) {
	echo "This script must run inside WordPress.\n";
	exit( 1 );
}

function tla_fail( string $message ): void {
	echo "Advanced analytics integration failed: {$message}\n";
	exit( 1 );
}

function tla_assert_contains( string $haystack, string $needle ): void {
	if ( strpos( $haystack, $needle ) === false ) {
		tla_fail( "Expected dashboard output to contain: {$needle}" );
	}
}

function tla_insert_event( int $user_id, int $course_id, int $lesson_id, string $event_type, string $event_value = '', ?string $created_at = null ): void {
	global $wpdb;
	$table = \TutorLMS_Analytics\Database::get_events_table_name();
	$inserted = $wpdb->insert(
		$table,
		array(
			'user_id'     => $user_id,
			'course_id'   => $course_id,
			'lesson_id'   => $lesson_id,
			'event_type'  => $event_type,
			'event_value' => $event_value,
			'user_agent'  => 'Mozilla/5.0 Integration Test Chrome',
			'device_type' => 'desktop',
			'browser'     => 'Chrome',
			'created_at'  => $created_at ?: current_time( 'mysql' ),
		),
		array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		tla_fail( 'Could not insert analytics event: ' . $wpdb->last_error );
	}
}

function tla_create_tutor_quiz_tables(): void {
	global $wpdb;
	$charset = $wpdb->get_charset_collate();
	$attempts = $wpdb->prefix . 'tutor_quiz_attempts';
	$answers  = $wpdb->prefix . 'tutor_quiz_attempt_answers';

	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$attempts} (
		attempt_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		quiz_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		earned_marks decimal(10,2) NOT NULL DEFAULT 0,
		total_marks decimal(10,2) NOT NULL DEFAULT 0,
		PRIMARY KEY (attempt_id),
		KEY quiz_id (quiz_id),
		KEY user_id (user_id)
	) {$charset}" );

	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$answers} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		quiz_attempt_id bigint(20) unsigned NOT NULL,
		question_id bigint(20) unsigned NOT NULL,
		achieved_mark decimal(10,2) NOT NULL DEFAULT 0,
		given_answer text,
		PRIMARY KEY (id),
		KEY quiz_attempt_id (quiz_attempt_id),
		KEY question_id (question_id)
	) {$charset}" );
}

function tla_insert_quiz_attempt( int $attempt_id, int $quiz_id, int $user_id, float $earned, float $total ): void {
	global $wpdb;
	$table = $wpdb->prefix . 'tutor_quiz_attempts';
	$wpdb->insert(
		$table,
		array(
			'attempt_id'   => $attempt_id,
			'quiz_id'      => $quiz_id,
			'user_id'      => $user_id,
			'earned_marks' => $earned,
			'total_marks'  => $total,
		),
		array( '%d', '%d', '%d', '%f', '%f' )
	);
}

function tla_insert_quiz_answer( int $attempt_id, int $question_id, float $achieved_mark, string $given_answer ): void {
	global $wpdb;
	$table = $wpdb->prefix . 'tutor_quiz_attempt_answers';
	$wpdb->insert(
		$table,
		array(
			'quiz_attempt_id' => $attempt_id,
			'question_id'     => $question_id,
			'achieved_mark'   => $achieved_mark,
			'given_answer'    => $given_answer,
		),
		array( '%d', '%d', '%f', '%s' )
	);
}

\TutorLMS_Analytics\Database::create_tables();
tla_create_tutor_quiz_tables();

$now = current_time( 'timestamp' );
$course_id = wp_insert_post(
	array(
		'post_type'   => 'courses',
		'post_status' => 'publish',
		'post_title'  => 'WP Integration Analytics Course',
	)
);
if ( is_wp_error( $course_id ) || ! $course_id ) {
	tla_fail( 'Could not create course.' );
}

$topic_id = wp_insert_post(
	array(
		'post_type'   => 'topics',
		'post_status' => 'publish',
		'post_title'  => 'Integration Topic',
		'post_parent' => $course_id,
		'menu_order'  => 1,
	)
);
$lesson_id = wp_insert_post(
	array(
		'post_type'   => 'lesson',
		'post_status' => 'publish',
		'post_title'  => 'Integration Lesson',
		'post_parent' => $topic_id,
		'menu_order'  => 1,
	)
);
$quiz_id = wp_insert_post(
	array(
		'post_type'   => 'tutor_quiz',
		'post_status' => 'publish',
		'post_title'  => 'Integration Quiz',
		'post_parent' => $topic_id,
		'menu_order'  => 2,
	)
);
$question_id = wp_insert_post(
	array(
		'post_type'   => 'tutor_quiz_question',
		'post_status' => 'publish',
		'post_title'  => 'Integration Question',
		'post_parent' => $quiz_id,
	)
);
update_post_meta( $quiz_id, '_tutor_quiz_passing_grade', '80' );

$user_ids = array();
foreach ( array( 'stuck', 'power', 'highscore', 'retry' ) as $suffix ) {
	$user_id = wp_create_user( 'tla_' . $suffix . '_' . wp_generate_password( 6, false ), wp_generate_password( 12, true ), 'tla_' . $suffix . '@example.test' );
	if ( is_wp_error( $user_id ) ) {
		tla_fail( 'Could not create user: ' . $user_id->get_error_message() );
	}
	$user_ids[ $suffix ] = (int) $user_id;
}

$enroll_dates = array(
	'stuck'     => gmdate( 'Y-m-d H:i:s', $now - 10 * DAY_IN_SECONDS ),
	'power'     => gmdate( 'Y-m-d H:i:s', $now - 6 * DAY_IN_SECONDS ),
	'highscore' => gmdate( 'Y-m-d H:i:s', $now - 5 * DAY_IN_SECONDS ),
);

foreach ( array( 'stuck', 'power', 'highscore' ) as $suffix ) {
	$user_id = $user_ids[ $suffix ];
	wp_insert_post(
		array(
			'post_type'    => 'tutor_enrolled',
			'post_status'  => 'publish',
			'post_title'   => 'Enrollment ' . $suffix,
			'post_parent'  => $course_id,
			'post_author'  => $user_id,
			'post_date'    => $enroll_dates[ $suffix ],
			'post_date_gmt'=> get_gmt_from_date( $enroll_dates[ $suffix ] ),
		)
	);
}

update_user_meta( $user_ids['stuck'], '_tutor_course_progress', array( $course_id => array( 'completed_lesson' => 2, 'total_lesson' => 10 ) ) );
update_user_meta( $user_ids['power'], '_tutor_course_progress', array( $course_id => array( 'completed_lesson' => 10, 'total_lesson' => 10 ) ) );
update_user_meta( $user_ids['highscore'], '_tutor_course_progress', array( $course_id => array( 'completed_lesson' => 0, 'total_lesson' => 10 ) ) );

wp_insert_comment(
	array(
		'comment_post_ID'  => $course_id,
		'user_id'          => $user_ids['power'],
		'comment_type'     => 'course_completed',
		'comment_approved' => 'approved',
		'comment_date'     => gmdate( 'Y-m-d H:i:s', strtotime( $enroll_dates['power'] ) + 4 * DAY_IN_SECONDS ),
	)
);
wp_insert_comment(
	array(
		'comment_post_ID'  => $lesson_id,
		'user_id'          => $user_ids['power'],
		'comment_type'     => 'lesson_completed',
		'comment_approved' => 'approved',
	)
);

// Stuck learner: many recent events, low progress.
for ( $i = 0; $i < 12; $i++ ) {
	tla_insert_event( $user_ids['stuck'], $course_id, $lesson_id, 'lesson_view', '', gmdate( 'Y-m-d H:i:s', $now - HOUR_IN_SECONDS * ( $i + 1 ) ) );
}

// Revisit/time-to-complete/retention events.
tla_insert_event( $user_ids['stuck'], $course_id, $lesson_id, 'page_exit', '600', gmdate( 'Y-m-d H:i:s', $now - HOUR_IN_SECONDS ) );
tla_insert_event( $user_ids['power'], $course_id, $lesson_id, 'lesson_view', '', gmdate( 'Y-m-d H:i:s', $now - 2 * HOUR_IN_SECONDS ) );
tla_insert_event( $user_ids['power'], $course_id, $lesson_id, 'page_exit', '900', gmdate( 'Y-m-d H:i:s', $now - 90 * MINUTE_IN_SECONDS ) );
tla_insert_event( $user_ids['highscore'], $course_id, $lesson_id, 'page_exit', '300', gmdate( 'Y-m-d H:i:s', $now - 30 * MINUTE_IN_SECONDS ) );

// Quiz attempts: power has a high first-pass score; a separate learner exercises retry behavior.
tla_insert_quiz_attempt( 101, $quiz_id, $user_ids['power'], 92.5, 100 );
tla_insert_quiz_attempt( 102, $quiz_id, $user_ids['highscore'], 90, 100 );
tla_insert_quiz_attempt( 103, $quiz_id, $user_ids['retry'], 40, 100 );
tla_insert_quiz_attempt( 104, $quiz_id, $user_ids['retry'], 90, 100 );
tla_insert_quiz_answer( 101, $question_id, 1, 'Correct answer' );
tla_insert_quiz_answer( 102, $question_id, 1, 'Correct answer' );
tla_insert_quiz_answer( 103, $question_id, 0, 'Common misconception' );
tla_insert_quiz_answer( 104, $question_id, 1, 'Correct answer' );

$_GET['course_id'] = $course_id;
$menu = new \TutorLMS_Analytics\Admin_Menu();
ob_start();
$menu->render_page();
$output = ob_get_clean();

$expected = array(
	'WP Integration Analytics Course',
	'Completion Rate by Enrollment Cohort',
	'33.3%',
	'Retention by Week',
	'100%',
	'High Intent but Stuck',
	'Progress 20% /',
	'Fast Learners / Power Learners',
	'92.5% / 4 วัน',
	'Low Engagement but High Score',
	'Quiz 90%',
	'Lesson Rewatch / Revisit Rate',
	'Revisit',
	'Time-to-Complete per Lesson',
	'10 นาที',
	'Exit Lesson',
	'Exit',
	'Content Difficulty Index',
	'Score',
	'Question Difficulty',
	'Integration Question',
	'Most Common Wrong Answers',
	'Common misconception',
	'Attempts Before Pass',
	'เฉลี่ย',
	'Quiz Retry Behavior',
	'Retry 100%',
);

foreach ( $expected as $needle ) {
	tla_assert_contains( $output, $needle );
}

echo "Advanced analytics WordPress integration test passed.\n";
