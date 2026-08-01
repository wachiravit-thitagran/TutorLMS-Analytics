<?php
declare(strict_types=1);

namespace TutorLMS_Analytics;

/**
 * Self-hosted plugin updates from this repository's GitHub releases.
 *
 * The Plugins/Updates screen offers an update whenever the repository's latest
 * release tag is newer than the installed version, and installs the release's
 * built `tutorlms-analytics.zip` asset (produced by `.github/workflows/release.yml`)
 * so the plugin folder keeps its name. GitHub's source zipball is only used as a
 * fallback, because it extracts to `owner-repo-<sha>/`.
 *
 * The GitHub API answers a misspelled `owner/repo` with the very same 404 as a repo
 * that has no releases, so a typo in the slug disables updates silently — see
 * `GithubUpdaterTest::test_default_github_repo_matches_the_plugin_uri`, which pins
 * the shipped default to the `Plugin URI` header.
 */
class Github_Updater {

	/**
	 * Transient prefix for the cached release payload.
	 */
	private const CACHE_PREFIX = 'tla_gh_release_';

	/**
	 * How long a successful API response is reused, in seconds.
	 */
	private const CACHE_TTL = 21600; // 6 hours.

	/**
	 * How long a failed / non-200 response is remembered, in seconds.
	 */
	private const FAILURE_TTL = 3600; // 1 hour.

	/**
	 * Mirrors the "Requires PHP" plugin header.
	 */
	private const REQUIRES_PHP = '7.4';

	/**
	 * Absolute path to the plugin's main file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Plugin slug / folder name.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * GitHub "owner/repo".
	 *
	 * @var string
	 */
	private $repo;

	/**
	 * Installed version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Preferred release asset filename.
	 *
	 * @var string
	 */
	private $asset;

	/**
	 * @param string $file    Absolute path to the plugin's main file.
	 * @param string $slug    Plugin slug / folder name.
	 * @param string $repo    GitHub "owner/repo".
	 * @param string $version Installed version.
	 * @param string $asset   Preferred release asset filename (defaults to "<slug>.zip").
	 */
	public function __construct( string $file, string $slug, string $repo, string $version, string $asset = '' ) {
		$this->file    = $file;
		$this->slug    = $slug;
		$this->repo    = trim( $repo, '/ ' );
		$this->version = $version;
		$this->asset   = '' !== $asset ? $asset : $slug . '.zip';
	}

	/**
	 * Hook into the update machinery. A slug that is not "owner/repo" is ignored
	 * outright so a broken override cannot make WordPress query nonsense URLs.
	 */
	public function register(): void {
		if ( '' === $this->repo || strpos( $this->repo, '/' ) === false ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
	}

	/**
	 * Offer the release as an update when it is newer than what is installed.
	 *
	 * @param mixed $transient The `update_plugins` transient, or something falsy
	 *                         while WordPress is still building it.
	 * @return mixed The transient, untouched unless an update applies.
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->latest_release();
		if ( null === $release ) {
			return $transient;
		}

		$new_version = $this->normalize( (string) $release['tag_name'] );
		if ( '' === $new_version || version_compare( $new_version, $this->normalize( $this->version ), '<=' ) ) {
			// Up to date: say so explicitly, otherwise the Plugins screen keeps
			// reporting the plugin as "unknown" to the update system.
			if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
				$transient->no_update[ $this->basename() ] = $this->build_item( $new_version, '' );
			}

			return $transient;
		}

		$package = $this->package_url( $release );
		if ( '' === $package ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ $this->basename() ] = $this->build_item( $new_version, $package );

		return $transient;
	}

	/**
	 * Fill the "View details" modal, since there is no wordpress.org entry to read.
	 *
	 * @param mixed  $result Result of a previous filter, returned untouched for
	 *                       any plugin but this one.
	 * @param string $action API action being performed.
	 * @param mixed  $args   Request args; only `->slug` is used.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$data    = function_exists( 'get_plugin_data' ) ? get_plugin_data( $this->file, false, false ) : array();
		$release = $this->latest_release();

		$version = $this->normalize( $this->version );
		if ( null !== $release ) {
			$version = $this->normalize( (string) $release['tag_name'] );
		}

		$info = array(
			'name'          => isset( $data['Name'] ) && '' !== $data['Name'] ? $data['Name'] : $this->slug,
			'slug'          => $this->slug,
			'version'       => $version,
			'author'        => isset( $data['Author'] ) ? $data['Author'] : '',
			'homepage'      => $this->repo_url(),
			'download_link' => null !== $release ? $this->package_url( $release ) : '',
			'requires'      => isset( $data['RequiresWP'] ) && '' !== $data['RequiresWP'] ? $data['RequiresWP'] : '5.3',
			'requires_php'  => isset( $data['RequiresPHP'] ) && '' !== $data['RequiresPHP'] ? $data['RequiresPHP'] : self::REQUIRES_PHP,
			'sections'      => array(
				'description' => isset( $data['Description'] ) ? $data['Description'] : '',
				'changelog'   => $this->release_notes( $release ),
			),
		);

		if ( null !== $release && ! empty( $release['published_at'] ) ) {
			$info['last_updated'] = (string) $release['published_at'];
		}

		return (object) $info;
	}

	/**
	 * Make sure the extracted folder is named after the slug, so an update lands on
	 * top of the existing installation instead of next to it. Only needed when the
	 * package is the source zipball.
	 *
	 * @param mixed $source        Directory the package was extracted to.
	 * @param mixed $remote_source Parent of `$source`.
	 * @param mixed $upgrader      WP_Upgrader instance (unused).
	 * @param mixed $hook_extra    Extra args; `['plugin']` identifies the plugin.
	 * @return mixed The directory to install from.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader = null, $hook_extra = array() ) {
		if ( ! is_array( $hook_extra ) || empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename() ) {
			return $source;
		}

		$desired = trailingslashit( (string) $remote_source ) . $this->slug;
		if ( untrailingslashit( (string) $source ) === $desired ) {
			return $source;
		}

		global $wp_filesystem;
		if ( isset( $wp_filesystem ) && is_object( $wp_filesystem ) && $wp_filesystem->move( untrailingslashit( (string) $source ), $desired, true ) ) {
			return trailingslashit( $desired );
		}

		return $source;
	}

	/**
	 * Plugin basename, e.g. "tutorlms-analytics/tutorlms-analytics.php".
	 */
	private function basename(): string {
		return plugin_basename( $this->file );
	}

	/**
	 * Repository landing page.
	 */
	private function repo_url(): string {
		return 'https://github.com/' . $this->repo;
	}

	/**
	 * Fetch the latest release, cached in a transient.
	 *
	 * Anything unexpected — transport error, 404 from a misspelled slug or a repo
	 * without releases, malformed JSON — is reported as "no release" so the Plugins
	 * screen neither warns nor fatals.
	 *
	 * @return array<string,mixed>|null Release payload with a non-empty `tag_name`.
	 */
	private function latest_release(): ?array {
		$cache_key = self::CACHE_PREFIX . md5( $this->repo );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			// An empty array is the negative cache written after a failed request.
			return empty( $cached['tag_name'] ) ? null : $cached;
		}

		/**
		 * Filters the HTTP args used to query the GitHub API, e.g. to add an
		 * `Authorization` header for a private repo or a higher rate limit.
		 *
		 * @param array<string,mixed> $args Args for `wp_remote_get()`.
		 * @param string              $repo GitHub "owner/repo".
		 */
		$args = apply_filters(
			'tutorlms_analytics_github_request_args',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'TutorLMS-Analytics-Updater',
				),
			),
			$this->repo
		);

		$response = wp_remote_get( 'https://api.github.com/repos/' . $this->repo . '/releases/latest', $args );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, array(), self::FAILURE_TTL );
			return null;
		}

		$release = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			set_transient( $cache_key, array(), self::FAILURE_TTL );
			return null;
		}

		set_transient( $cache_key, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Strip the "v" that release tags carry but plugin versions do not.
	 */
	private function normalize( string $version ): string {
		return ltrim( trim( $version ), 'vV' );
	}

	/**
	 * Download URL for a release: the built plugin zip when the release has one,
	 * otherwise the source zipball.
	 *
	 * @param array<string,mixed> $release Release payload.
	 */
	private function package_url( array $release ): string {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( is_array( $asset ) && isset( $asset['name'], $asset['browser_download_url'] ) && $asset['name'] === $this->asset ) {
					return (string) $asset['browser_download_url'];
				}
			}
		}

		return isset( $release['zipball_url'] ) ? (string) $release['zipball_url'] : '';
	}

	/**
	 * The update item WordPress expects in the `update_plugins` transient.
	 *
	 * @param string $new_version Version offered.
	 * @param string $package     Download URL, '' when there is nothing to install.
	 * @return object
	 */
	private function build_item( string $new_version, string $package ) {
		return (object) array(
			'slug'         => $this->slug,
			'plugin'       => $this->basename(),
			'new_version'  => $new_version,
			'url'          => $this->repo_url(),
			'package'      => $package,
			'icons'        => array(),
			'banners'      => array(),
			'tested'       => '',
			'requires_php' => self::REQUIRES_PHP,
		);
	}

	/**
	 * Release notes as HTML for the details modal.
	 *
	 * @param array<string,mixed>|null $release Release payload.
	 */
	private function release_notes( ?array $release ): string {
		if ( null === $release || empty( $release['body'] ) ) {
			return '';
		}

		return wp_kses_post( wpautop( (string) $release['body'] ) );
	}
}
