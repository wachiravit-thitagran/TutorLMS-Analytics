<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

/**
 * Immutable date-range value object used by providers so that the
 * dashboard date picker can drive every query (replaces hardcoded 30/90 day windows).
 */
class Date_Range {

	private string $from; // Y-m-d (site timezone)
	private string $to;   // Y-m-d (site timezone)

	public function __construct( string $from, string $to ) {
		$from = self::sanitize_date( $from );
		$to   = self::sanitize_date( $to );
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}
		$this->from = $from;
		$this->to   = $to;
	}

	/**
	 * Build from request params with a default of the last N days.
	 */
	public static function from_request( ?string $from, ?string $to, int $default_days = 30 ): self {
		$today = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		$to    = $to ? self::sanitize_date( $to, $today ) : $today;
		if ( $from ) {
			$from = self::sanitize_date( $from, gmdate( 'Y-m-d', strtotime( $to . ' -' . ( $default_days - 1 ) . ' days' ) ) );
		} else {
			$from = gmdate( 'Y-m-d', strtotime( $to . ' -' . ( $default_days - 1 ) . ' days' ) );
		}
		return new self( $from, $to );
	}

	public static function last_days( int $days ): self {
		return self::from_request( null, null, max( 1, $days ) );
	}

	public function from(): string {
		return $this->from;
	}

	public function to(): string {
		return $this->to;
	}

	/** Inclusive number of days in the range. */
	public function days(): int {
		return (int) ( ( strtotime( $this->to ) - strtotime( $this->from ) ) / DAY_IN_SECONDS ) + 1;
	}

	/** The equally-sized period immediately before this one (for KPI deltas). */
	public function previous_period(): self {
		$days = $this->days();
		$to   = gmdate( 'Y-m-d', strtotime( $this->from . ' -1 day' ) );
		$from = gmdate( 'Y-m-d', strtotime( $to . ' -' . ( $days - 1 ) . ' days' ) );
		return new self( $from, $to );
	}

	/** Start boundary for SQL comparison against local-time columns (post_date, comment_date). */
	public function start_sql(): string {
		return $this->from . ' 00:00:00';
	}

	/** End boundary for SQL comparison against local-time columns. */
	public function end_sql(): string {
		return $this->to . ' 23:59:59';
	}

	/** Start boundary for GMT columns (events table created_at, *_gmt columns). */
	public function start_sql_gmt(): string {
		return function_exists( 'get_gmt_from_date' ) ? get_gmt_from_date( $this->start_sql() ) : $this->start_sql();
	}

	/** End boundary for GMT columns. */
	public function end_sql_gmt(): string {
		return function_exists( 'get_gmt_from_date' ) ? get_gmt_from_date( $this->end_sql() ) : $this->end_sql();
	}

	/** Cache-key fragment. */
	public function key(): string {
		return $this->from . '_' . $this->to;
	}

	private static function sanitize_date( string $date, string $fallback = '' ): string {
		$date = trim( $date );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && strtotime( $date ) !== false ) {
			return $date;
		}
		if ( '' !== $fallback ) {
			return $fallback;
		}
		return function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
	}
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
