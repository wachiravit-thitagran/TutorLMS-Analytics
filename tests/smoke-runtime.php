<?php
/**
 * Self-contained runtime smoke test for the new Tutor LMS 4.0 providers and
 * the Analytics_Service / Date_Range / Stats_Cache infrastructure.
 *
 * It defines just enough WordPress + $wpdb surface to instantiate the classes,
 * runs each provider through both its "unavailable" and a mocked "available"
 * path, and asserts the documented return shape. Results are written as JSON to
 * the path given in the TLA_SMOKE_OUT env var (or php://stdout).
 *
 * Designed to run under a bare PHP CLI or php-wasm — no PHPUnit, no WP.
 */
declare(strict_types=1);

error_reporting( E_ALL );

$RESULTS = array( 'pass' => 0, 'fail' => 0, 'cases' => array() );

function check( string $name, bool $cond ): void {
	global $RESULTS;
	$RESULTS[ $cond ? 'pass' : 'fail' ]++;
	$RESULTS['cases'][] = array( 'name' => $name, 'ok' => $cond );
}

function has_keys( $arr, array $keys ): bool {
	if ( ! is_array( $arr ) ) {
		return false;
	}
	foreach ( $keys as $k ) {
		if ( ! array_key_exists( $k, $arr ) ) {
			return false;
		}
	}
	return true;
}

/* ---------------- Minimal WP + $wpdb surface ---------------- */

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/tmp/' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }

function __( $t, $d = 'default' ) { return $t; }
function esc_html( $t ) { return $t; }
function get_the_title( $id = 0 ) { return 'Course ' . $id; }
function current_time( $type, $gmt = 0 ) { return $type === 'Y-m-d' ? gmdate( 'Y-m-d' ) : gmdate( 'Y-m-d H:i:s' ); }
function get_gmt_from_date( $d, $f = 'Y-m-d H:i:s' ) { return $d; }
function get_transient( $k ) { return $GLOBALS['tr'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['tr'][ $k ] = $v; return true; }
function get_option( $k, $d = false ) { return $GLOBALS['op'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['op'][ $k ] = $v; return true; }
function get_post_meta( $id, $key = '', $single = false ) { return $GLOBALS['pm'][ $id ][ $key ] ?? ( $single ? '' : array() ); }
function maybe_unserialize( $v ) { if ( is_string( $v ) && preg_match( '/^[aOs]:/', trim( $v ) ) ) { $u = @unserialize( trim( $v ) ); if ( $u !== false ) { return $u; } } return $v; }

/**
 * Query-pattern driven mock. mock_var/mock_results are matched as substrings.
 */
class Mock_WPDB {
	public $posts = 'wp_posts';
	public $comments = 'wp_comments';
	public $commentmeta = 'wp_commentmeta';
	public $postmeta = 'wp_postmeta';
	public $prefix = 'wp_';
	public $mock_var = array();
	public $mock_results = array();
	public $mock_col = array();
	public $default_var = 0;

	public function prepare( $q, ...$a ) {
		// Mimic wpdb: %d -> integer, %s -> single-quoted string, %f -> float.
		$i = 0;
		return preg_replace_callback(
			'/%[dsf]/',
			function ( $m ) use ( &$i, $a ) {
				$v = $a[ $i ] ?? '';
				$i++;
				if ( '%d' === $m[0] ) { return (string) (int) $v; }
				if ( '%f' === $m[0] ) { return (string) (float) $v; }
				return "'" . $v . "'";
			},
			$q
		);
	}
	public function get_var( $q ) {
		foreach ( $this->mock_var as $pat => $val ) { if ( strpos( $q, $pat ) !== false ) { return $val; } }
		return $this->default_var;
	}
	public function get_results( $q, $out = 'OBJECT' ) {
		foreach ( $this->mock_results as $pat => $val ) { if ( strpos( $q, $pat ) !== false ) { return $val; } }
		return array();
	}
	public function get_row( $q, $out = 'OBJECT' ) {
		$r = $this->get_results( $q, $out );
		return $r[0] ?? null;
	}
	public function get_col( $q ) {
		foreach ( $this->mock_col as $pat => $val ) { if ( strpos( $q, $pat ) !== false ) { return $val; } }
		return array();
	}
}

$GLOBALS['wpdb'] = new Mock_WPDB();
global $wpdb;

/* ---------------- Load classes under test ---------------- */

define( 'TUTORLMS_ANALYTICS_DIR', getenv( 'TLA_DIR' ) ?: '/plugin/' );
$base = TUTORLMS_ANALYTICS_DIR . 'includes/';

require $base . 'Date_Range.php';
require $base . 'Stats_Cache.php';
require $base . 'Tutor_Schema.php';
foreach ( glob( $base . 'Providers/*.php' ) as $f ) { require $f; }

use TutorLMS_Analytics\Date_Range;
use TutorLMS_Analytics\Stats_Cache;
use TutorLMS_Analytics\Tutor_Schema;
use TutorLMS_Analytics\Providers\Monetization_Provider;
use TutorLMS_Analytics\Providers\Subscription_Provider;
use TutorLMS_Analytics\Providers\Bundle_Provider;
use TutorLMS_Analytics\Providers\QnA_Provider;
use TutorLMS_Analytics\Providers\Certificate_Provider;
use TutorLMS_Analytics\Providers\Quiz_Type_Provider;
use TutorLMS_Analytics\Providers\Live_Lesson_Provider;
use TutorLMS_Analytics\Providers\Gradebook_Provider;
use TutorLMS_Analytics\Providers\Assignment_Provider;

/* ---------------- Date_Range ---------------- */

$r = new Date_Range( '2026-01-01', '2026-01-30' );
check( 'DateRange days inclusive', $r->days() === 30 );
$prev = $r->previous_period();
check( 'DateRange previous ends before start', $prev->to() < $r->from() );
check( 'DateRange previous same length', $prev->days() === 30 );
$swap = new Date_Range( '2026-02-01', '2026-01-01' );
check( 'DateRange normalizes reversed', $swap->from() <= $swap->to() );
$def = Date_Range::from_request( null, null, 7 );
check( 'DateRange default 7d', $def->days() === 7 );

/* ---------------- Stats_Cache ---------------- */

$calls = 0;
$val = Stats_Cache::remember( 'k1', function () use ( &$calls ) { $calls++; return array( 'x' => 1 ); } );
$val2 = Stats_Cache::remember( 'k1', function () use ( &$calls ) { $calls++; return array( 'x' => 2 ); } );
check( 'Cache remembers (callback once)', $calls === 1 && $val2['x'] === 1 );
Stats_Cache::flush();
$val3 = Stats_Cache::remember( 'k1', function () use ( &$calls ) { $calls++; return array( 'x' => 3 ); } );
check( 'Cache flush re-computes', $calls === 2 && $val3['x'] === 3 );

/* ---------------- Providers: unavailable paths ---------------- */

$wpdb->mock_var = array(); // No tables, no rows -> everything unavailable.

$m = ( new Monetization_Provider() )->get_monetization_stats( 0, $r );
check( 'Monetization shape', has_keys( $m, array( 'available', 'gross_revenue', 'net_revenue', 'orders_count', 'refund_amount', 'refund_rate', 'trend', 'by_order_type', 'coupon_usage', 'enrollment_sources', 'gifts' ) ) );
check( 'Monetization unavailable', $m['available'] === false );
check( 'Monetization enrollment_sources keys', has_keys( $m['enrollment_sources'], array( 'bundle', 'subscription', 'native', 'external', 'manual_free' ) ) );

$s = ( new Subscription_Provider() )->get_subscription_stats( $r );
check( 'Subscription shape', has_keys( $s, array( 'available', 'active_subscriptions', 'cancelled_subscriptions', 'churn_rate', 'new_subscriptions', 'renewals', 'subscription_net_revenue', 'mrr_estimate', 'trend', 'plans' ) ) );
check( 'Subscription unavailable + nulls', $s['available'] === false && $s['active_subscriptions'] === null );

$b = ( new Bundle_Provider() )->get_bundle_stats( 0, $r );
check( 'Bundle shape', has_keys( $b, array( 'available', 'total_bundles', 'bundle_enrollments_in_range', 'bundles', 'note_expiry_unavailable' ) ) );
check( 'Bundle unavailable', $b['available'] === false && $b['bundles'] === array() );

$q = ( new QnA_Provider() )->get_qna_stats( 0, $r );
check( 'QnA shape', has_keys( $q, array( 'available', 'total_questions', 'unanswered', 'answered_rate', 'avg_first_response_hours', 'top_courses', 'recent_unanswered' ) ) );
check( 'QnA unavailable', $q['available'] === false );

$c = ( new Certificate_Provider() )->get_certificate_stats( 0, $r );
check( 'Certificate shape', has_keys( $c, array( 'available', 'issued_in_range', 'completion_to_certificate_rate', 'monthly_trend', 'per_course' ) ) );
check( 'Certificate monthly_trend has series keys', has_keys( $c['monthly_trend'], array( 'labels', 'data' ) ) );

$qt = ( new Quiz_Type_Provider() )->get_question_type_stats( 0 );
check( 'QuizType shape', has_keys( $qt, array( 'available', 'types', 'new_v4_adoption' ) ) );
check( 'QuizType adoption keys', has_keys( $qt['new_v4_adoption'], array( 'new_type_questions', 'total_questions', 'pct' ) ) );

$ll = ( new Live_Lesson_Provider() )->get_live_lesson_stats( 0, $r );
check( 'LiveLesson shape', has_keys( $ll, array( 'available', 'total', 'zoom', 'google_meet', 'held_in_range', 'upcoming', 'per_course' ) ) );

$g = ( new Gradebook_Provider() )->get_gradebook_stats( 0 );
check( 'Gradebook shape', has_keys( $g, array( 'available', 'grade_distribution', 'avg_percent', 'results_count', 'per_course' ) ) );

$a = ( new Assignment_Provider() )->get_assignment_stats( 0, $r );
check( 'Assignment shape', has_keys( $a, array( 'available', 'total_assignments', 'submissions', 'in_progress', 'graded', 'pending_review', 'avg_score_pct', 'pass_rate', 'grading_turnaround_hours', 'per_assignment' ) ) );

/* ---------------- Monetization available path ---------------- */

$wpdb2 = new Mock_WPDB();
$wpdb2->mock_var = array(
	'SHOW TABLES LIKE' => 'wp_tutor_orders', // Every table-exists check resolves truthy-ish.
);
// Make the specific orders table check pass and others as needed.
$wpdb2->mock_var = array( 'wp_tutor_orders' => 'wp_tutor_orders' );
$wpdb2->mock_results = array(
	'COALESCE(SUM(o.total_price)' => array( array( 'gross_revenue' => '1000.00', 'net_revenue' => '900.00', 'orders_count' => '10' ) ),
	'refunded_orders' => array( array( 'refund_amount' => '100.00', 'refunded_orders' => '2', 'paid_orders' => '8' ) ),
	'trend_day' => array( array( 'trend_day' => '2026-01-05', 'trend_net' => '300.00' ) ),
	'order_type AS order_type' => array( array( 'order_type' => 'single_order', 'type_net' => '900.00' ) ),
	'src_bundle' => array( array( 'src_bundle' => '3', 'src_subscription' => '1', 'src_native' => '4', 'src_external' => '2', 'src_manual_free' => '5' ) ),
);
$GLOBALS['wpdb'] = $wpdb2;
// Reset Tutor_Schema table cache between scenarios.
( function () {
	$ref = new ReflectionProperty( Tutor_Schema::class, 'table_cache' );
	$ref->setAccessible( true );
	$ref->setValue( array() );
} )();

$m2 = ( new Monetization_Provider() )->get_monetization_stats( 0, $r );
check( 'Monetization available true', $m2['available'] === true );
check( 'Monetization gross computed', abs( $m2['gross_revenue'] - 1000.0 ) < 0.001 );
check( 'Monetization net computed', abs( $m2['net_revenue'] - 900.0 ) < 0.001 );
check( 'Monetization refund_rate 20%', abs( $m2['refund_rate'] - 20.0 ) < 0.001 );
check( 'Monetization enroll sources counted', $m2['enrollment_sources']['native'] === 4 && $m2['enrollment_sources']['manual_free'] === 5 );

/* ---------------- QnA available path ---------------- */

$wpdb3 = new Mock_WPDB();
$wpdb3->mock_var = array(
	"comment_type = 'tutor_q_and_a'" => 1, // availability probe
	'AVG(TIMESTAMPDIFF' => '4.5',
);
$wpdb3->mock_results = array(
	'total_questions' => array( array( 'total_questions' => '10', 'unanswered' => '4' ) ),
);
$GLOBALS['wpdb'] = $wpdb3;
$q2 = ( new QnA_Provider() )->get_qna_stats( 5, $r );
check( 'QnA available true', $q2['available'] === true );
check( 'QnA totals computed', $q2['total_questions'] === 10 && $q2['unanswered'] === 4 );
check( 'QnA answered_rate 60%', abs( $q2['answered_rate'] - 60.0 ) < 0.001 );

/* ---------------- Quiz type new-v4 flagging ---------------- */

$wpdb4 = new Mock_WPDB();
$wpdb4->mock_var = array( 'wp_tutor_quiz_questions' => 'wp_tutor_quiz_questions' );
$wpdb4->mock_results = array(
	'GROUP BY q.question_type' => array(
		array( 'question_type' => 'single_choice', 'questions' => '6' ),
		array( 'question_type' => 'draw_image', 'questions' => '4' ),
	),
);
$GLOBALS['wpdb'] = $wpdb4;
( function () {
	$ref = new ReflectionProperty( Tutor_Schema::class, 'table_cache' );
	$ref->setAccessible( true );
	$ref->setValue( array() );
} )();
$qt2 = ( new Quiz_Type_Provider() )->get_question_type_stats( 0 );
$byType = array();
foreach ( $qt2['types'] as $row ) { $byType[ $row['type'] ] = $row; }
check( 'QuizType classic not new', isset( $byType['single_choice'] ) && $byType['single_choice']['is_new_v4'] === false );
check( 'QuizType draw_image is new v4', isset( $byType['draw_image'] ) && $byType['draw_image']['is_new_v4'] === true );
check( 'QuizType adoption pct 40%', abs( $qt2['new_v4_adoption']['pct'] - 40.0 ) < 0.001 );

/* ---------------- Emit ---------------- */

global $RESULTS;
$json = json_encode( $RESULTS, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
$out  = getenv( 'TLA_SMOKE_OUT' );
if ( $out ) { file_put_contents( $out, $json ); }
echo $json . "\n";
echo 'SUMMARY ' . $RESULTS['pass'] . ' passed, ' . $RESULTS['fail'] . ' failed' . "\n";
