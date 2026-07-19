<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emit the server-rendered initial section payload so the first tab paints
 * without an extra round trip. Everything else is fetched lazily.
 *
 * @var array $initial_data
 */
?>
<script>
	window.TutorLMSAnalyticsInitial = <?php echo wp_json_encode( $initial_data ); ?>;
</script>
