<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared date-range picker. Submits via GET so the server re-renders the
 * initial section for the new range; the JS layer re-fetches other sections.
 *
 * @var \TutorLMS_Analytics\Date_Range $range
 */
$page      = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'tutorlms-analytics';
$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
?>
<form id="tla-range-form" class="tla-range" method="get" action="">
	<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>" />
	<?php if ( $course_id > 0 ) : ?>
		<input type="hidden" name="course_id" value="<?php echo esc_attr( (string) $course_id ); ?>" />
	<?php endif; ?>

	<div class="tla-presets" role="group" aria-label="<?php esc_attr_e( 'ช่วงเวลาสำเร็จรูป', 'tutorlms-analytics' ); ?>">
		<button type="button" class="tla-preset" data-preset="7"><?php esc_html_e( '7 วัน', 'tutorlms-analytics' ); ?></button>
		<button type="button" class="tla-preset" data-preset="30"><?php esc_html_e( '30 วัน', 'tutorlms-analytics' ); ?></button>
		<button type="button" class="tla-preset" data-preset="90"><?php esc_html_e( '90 วัน', 'tutorlms-analytics' ); ?></button>
	</div>

	<label for="tla-from"><?php esc_html_e( 'จาก', 'tutorlms-analytics' ); ?></label>
	<input type="date" id="tla-from" name="from" value="<?php echo esc_attr( $range->from() ); ?>" />
	<label for="tla-to"><?php esc_html_e( 'ถึง', 'tutorlms-analytics' ); ?></label>
	<input type="date" id="tla-to" name="to" value="<?php echo esc_attr( $range->to() ); ?>" />

	<button type="submit" class="tla-btn"><?php esc_html_e( 'ใช้ช่วงเวลานี้', 'tutorlms-analytics' ); ?></button>
</form>
