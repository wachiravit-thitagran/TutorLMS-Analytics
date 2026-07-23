<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

/**
 * Thin transient-based cache so heavy provider queries only run
 * once per TTL per (scope, section, course, date-range) combination.
 */
class Stats_Cache {

	private const PREFIX      = 'tla_stats_';
	private const VERSION_KEY = 'tla_stats_cache_version';
	public const DEFAULT_TTL  = 600; // 10 minutes.

	/**
	 * Return cached value or compute + store it.
	 *
	 * @param string   $key      Unique key (already namespaced by caller).
	 * @param callable $callback Producer executed on cache miss.
	 * @param int      $ttl      Seconds to keep.
	 * @return mixed
	 */
	public static function remember( string $key, callable $callback, int $ttl = self::DEFAULT_TTL ) {
		if ( ! function_exists( 'get_transient' ) ) {
			return $callback();
		}

		$transient_key = self::build_key( $key );
		$cached        = get_transient( $transient_key );
		if ( false !== $cached && is_array( $cached ) && array_key_exists( 'payload', $cached ) ) {
			return $cached['payload'];
		}

		$value = $callback();
		// Wrap so that a legitimate `false`/empty payload is still a cache hit.
		set_transient( $transient_key, array( 'payload' => $value ), $ttl );

		return $value;
	}

	/**
	 * Invalidate everything by bumping the version salt (O(1), no table scans).
	 */
	public static function flush(): void {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::VERSION_KEY, (string) time(), false );
		}
	}

	private static function build_key( string $key ): string {
		$version = function_exists( 'get_option' ) ? (string) get_option( self::VERSION_KEY, '1' ) : '1';
		// Fold the plugin version into the salt so shipping new code (new
		// provider fields, changed queries) auto-invalidates stale payloads
		// without needing a manual flush.
		$code_version = defined( 'TUTORLMS_ANALYTICS_VERSION' ) ? TUTORLMS_ANALYTICS_VERSION : '0';
		return self::PREFIX . md5( $version . '|' . $code_version . '|' . $key );
	}
}
