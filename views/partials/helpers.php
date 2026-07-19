<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small view helpers shared by both dashboards. Guarded so the file is safe to
 * include more than once per request.
 */
if ( ! function_exists( 'tla_chart_card' ) ) {
	/**
	 * A titled card whose body is a chart container the JS layer fills.
	 */
	function tla_chart_card( string $id, string $title, string $desc = '', string $size = '' ): void {
		?>
		<div class="tla-card">
			<h3 class="tla-card-title"><?php echo esc_html( $title ); ?></h3>
			<?php if ( '' !== $desc ) : ?>
				<p class="tla-card-desc"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
			<div class="tla-chart <?php echo esc_attr( $size ); ?>" id="<?php echo esc_attr( $id ); ?>"></div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'tla_panel_card' ) ) {
	/**
	 * A titled card whose body is a generic container (table/list) the JS fills.
	 */
	function tla_panel_card( string $id, string $title, string $desc = '' ): void {
		?>
		<div class="tla-card">
			<h3 class="tla-card-title"><?php echo esc_html( $title ); ?></h3>
			<?php if ( '' !== $desc ) : ?>
				<p class="tla-card-desc"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
			<div id="<?php echo esc_attr( $id ); ?>"></div>
		</div>
		<?php
	}
}
