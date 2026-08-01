<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TutorLMS_Analytics\Github_Updater;

class GithubUpdaterTest extends TestCase {

	private const SLUG  = 'tutorlms-analytics';
	private const ASSET = 'tutorlms-analytics.zip';
	private const REPO  = 'owner/repo';

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['mock_transients']    = array();
		$GLOBALS['mock_filters']       = array();
		$GLOBALS['mock_http_requests'] = array();
		unset( $GLOBALS['mock_http_response'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['mock_http_response'] );
		parent::tearDown();
	}

	private static function plugin_file(): string {
		return dirname( __DIR__ ) . '/tutorlms-analytics.php';
	}

	private function updater( string $version, string $repo = self::REPO ): Github_Updater {
		return new Github_Updater( self::plugin_file(), self::SLUG, $repo, $version, self::ASSET );
	}

	/**
	 * Reach a private method. The visibility opt-in is only needed (and only
	 * allowed without a deprecation) before PHP 8.1.
	 *
	 * @param object $object Instance to call on.
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function invoke( object $object, string $method, array $args = array() ) {
		$ref = new ReflectionMethod( $object, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}
		return $ref->invokeArgs( $object, $args );
	}

	private function seed_release( array $release, string $repo = self::REPO ): void {
		set_transient( 'tla_gh_release_' . md5( $repo ), $release );
	}

	private static function transient(): object {
		return (object) array(
			'response'  => array(),
			'no_update' => array(),
		);
	}

	/**
	 * Every install checks this one slug for releases, and a typo in it fails
	 * silently: the GitHub API answers a misspelled owner with the same 404 as a
	 * repository that simply has no releases, so nothing is ever offered and no
	 * error surfaces anywhere. Pin it to the `Plugin URI` header so the two cannot
	 * drift apart unnoticed.
	 */
	public function test_default_github_repo_matches_the_plugin_uri(): void {
		$plugin = (string) file_get_contents( self::plugin_file() );

		preg_match( "/define\(\s*'TUTORLMS_ANALYTICS_GITHUB_REPO',\s*'([^']*)'/", $plugin, $m );
		$slug = $m[1] ?? '';

		$this->assertMatchesRegularExpression(
			'#^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?/[A-Za-z0-9._-]+$#',
			$slug,
			'TUTORLMS_ANALYTICS_GITHUB_REPO must be a valid "owner/repo" slug.'
		);

		preg_match( '#Plugin URI:\s*https://github\.com/(\S+?)/?\s*$#m', $plugin, $uri );

		$this->assertSame(
			strtolower( $slug ),
			strtolower( $uri[1] ?? '' ),
			'The Plugin URI header must point at the repository whose releases are installed.'
		);
	}

	/**
	 * The preferred asset must be the zip that the release workflow actually
	 * uploads; otherwise every update silently falls back to GitHub's source
	 * zipball, which extracts to `owner-repo-<sha>/`.
	 */
	public function test_preferred_asset_matches_the_zip_the_build_script_produces(): void {
		$plugin = (string) file_get_contents( self::plugin_file() );
		preg_match( "/new Github_Updater\([^;]*?'([^']+\.zip)'/s", $plugin, $m );

		$build = (string) file_get_contents( dirname( __DIR__ ) . '/bin/build.sh' );
		preg_match( '/PLUGIN_SLUG="([^"]+)"/', $build, $b );

		$this->assertSame( ( $b[1] ?? '' ) . '.zip', $m[1] ?? '' );
	}

	/**
	 * The version advertised to WordPress gates the update on PHP; it must not
	 * claim more than the plugin header does.
	 */
	public function test_requires_php_matches_the_plugin_header(): void {
		preg_match( '/Requires PHP:\s*(\S+)/', (string) file_get_contents( self::plugin_file() ), $m );

		$constant = new ReflectionClass( Github_Updater::class );

		$this->assertSame( $m[1] ?? '', $constant->getConstant( 'REQUIRES_PHP' ) );
	}

	public function test_normalize_strips_the_tag_prefix(): void {
		$updater = $this->updater( '2.0.6' );

		$this->assertSame( '2.0.7', $this->invoke( $updater, 'normalize', array( 'v2.0.7' ) ) );
		$this->assertSame( '2.0.7', $this->invoke( $updater, 'normalize', array( 'V2.0.7' ) ) );
		$this->assertSame( '2.0.7', $this->invoke( $updater, 'normalize', array( ' 2.0.7 ' ) ) );
		$this->assertSame( '', $this->invoke( $updater, 'normalize', array( '' ) ) );
	}

	public function test_package_url_prefers_the_built_plugin_zip(): void {
		$release = array(
			'assets'      => array(
				array(
					'name'                 => 'source-notes.txt',
					'browser_download_url' => 'https://dl/notes.txt',
				),
				array(
					'name'                 => self::ASSET,
					'browser_download_url' => 'https://dl/tutorlms-analytics.zip',
				),
			),
			'zipball_url' => 'https://dl/zipball',
		);

		$this->assertSame(
			'https://dl/tutorlms-analytics.zip',
			$this->invoke( $this->updater( '2.0.6' ), 'package_url', array( $release ) )
		);
	}

	public function test_package_url_falls_back_to_the_zipball(): void {
		$release = array(
			'assets'      => array(
				array(
					'name'                 => 'other-plugin.zip',
					'browser_download_url' => 'https://dl/other.zip',
				),
			),
			'zipball_url' => 'https://dl/zipball',
		);

		$this->assertSame(
			'https://dl/zipball',
			$this->invoke( $this->updater( '2.0.6' ), 'package_url', array( $release ) )
		);
	}

	public function test_package_url_is_empty_without_any_download(): void {
		$this->assertSame( '', $this->invoke( $this->updater( '2.0.6' ), 'package_url', array( array() ) ) );
	}

	public function test_inject_update_offers_a_newer_release(): void {
		$this->seed_release(
			array(
				'tag_name'    => 'v2.0.7',
				'assets'      => array(
					array(
						'name'                 => self::ASSET,
						'browser_download_url' => 'https://dl/tutorlms-analytics.zip',
					),
				),
				'zipball_url' => 'https://dl/zipball',
			)
		);

		$result = $this->updater( '2.0.6' )->inject_update( self::transient() );

		$key = plugin_basename( self::plugin_file() );
		$this->assertArrayHasKey( $key, $result->response );
		$this->assertSame( '2.0.7', $result->response[ $key ]->new_version );
		$this->assertSame( 'https://dl/tutorlms-analytics.zip', $result->response[ $key ]->package );
		$this->assertSame( self::SLUG, $result->response[ $key ]->slug );
		$this->assertArrayNotHasKey( $key, $result->no_update );
	}

	public function test_inject_update_reports_the_same_version_as_up_to_date(): void {
		$this->seed_release( array( 'tag_name' => 'v2.0.7', 'zipball_url' => 'https://dl/zipball' ) );

		$result = $this->updater( '2.0.7' )->inject_update( self::transient() );

		$key = plugin_basename( self::plugin_file() );
		$this->assertArrayNotHasKey( $key, $result->response );
		$this->assertArrayHasKey( $key, $result->no_update );
		$this->assertSame( '2.0.7', $result->no_update[ $key ]->new_version );
	}

	public function test_inject_update_never_offers_an_older_release(): void {
		$this->seed_release( array( 'tag_name' => 'v2.0.6', 'zipball_url' => 'https://dl/zipball' ) );

		$result = $this->updater( '2.0.10' )->inject_update( self::transient() );

		$key = plugin_basename( self::plugin_file() );
		$this->assertArrayNotHasKey( $key, $result->response );
		$this->assertArrayHasKey( $key, $result->no_update );
	}

	public function test_inject_update_leaves_a_non_object_transient_alone(): void {
		$this->assertFalse( $this->updater( '2.0.6' )->inject_update( false ) );
	}

	public function test_inject_update_offers_nothing_without_a_download_url(): void {
		$this->seed_release( array( 'tag_name' => 'v2.0.7' ) );

		$result = $this->updater( '2.0.6' )->inject_update( self::transient() );

		$this->assertSame( array(), $result->response );
	}

	/**
	 * A 404 (unknown repo, or no release published yet) must pass by quietly: no
	 * warning on the Plugins screen, and no repeat request until the negative
	 * cache expires.
	 */
	public function test_a_failing_api_request_offers_nothing_and_is_cached(): void {
		$GLOBALS['mock_http_response'] = array(
			'response' => array( 'code' => 404 ),
			'body'     => '{"message":"Not Found"}',
		);

		$updater = $this->updater( '2.0.6' );
		$result  = $updater->inject_update( self::transient() );

		$this->assertSame( array(), $result->response );
		$this->assertSame( array(), $result->no_update );
		$this->assertCount( 1, $GLOBALS['mock_http_requests'] );
		$this->assertSame(
			'https://api.github.com/repos/owner/repo/releases/latest',
			$GLOBALS['mock_http_requests'][0]['url']
		);
		$this->assertSame( array(), get_transient( 'tla_gh_release_' . md5( self::REPO ) ) );

		// Cached failure: the second check must not hit the API again.
		$updater->inject_update( self::transient() );
		$this->assertCount( 1, $GLOBALS['mock_http_requests'] );
	}

	public function test_a_transport_error_offers_nothing(): void {
		$GLOBALS['mock_http_response'] = new WP_Error( 'http_request_failed', 'cURL error 6' );

		$result = $this->updater( '2.0.6' )->inject_update( self::transient() );

		$this->assertSame( array(), $result->response );
	}

	public function test_a_malformed_body_offers_nothing(): void {
		$GLOBALS['mock_http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => 'not json',
		);

		$result = $this->updater( '2.0.6' )->inject_update( self::transient() );

		$this->assertSame( array(), $result->response );
	}

	public function test_a_successful_response_is_cached(): void {
		$GLOBALS['mock_http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"tag_name":"v2.0.7","zipball_url":"https://dl/zipball"}',
		);

		$updater = $this->updater( '2.0.6' );
		$result  = $updater->inject_update( self::transient() );

		$key = plugin_basename( self::plugin_file() );
		$this->assertSame( '2.0.7', $result->response[ $key ]->new_version );
		$this->assertSame(
			'v2.0.7',
			get_transient( 'tla_gh_release_' . md5( self::REPO ) )['tag_name']
		);

		$updater->inject_update( self::transient() );
		$this->assertCount( 1, $GLOBALS['mock_http_requests'] );
	}

	public function test_the_negative_cache_is_not_read_as_a_release(): void {
		$this->seed_release( array() );

		$this->assertNull( $this->invoke( $this->updater( '2.0.6' ), 'latest_release' ) );
		$this->assertSame( array(), $GLOBALS['mock_http_requests'] );
	}

	public function test_plugin_info_describes_the_release(): void {
		$this->seed_release(
			array(
				'tag_name'     => 'v2.0.7',
				'body'         => 'Release notes',
				'published_at' => '2026-08-01T00:00:00Z',
				'assets'       => array(
					array(
						'name'                 => self::ASSET,
						'browser_download_url' => 'https://dl/tutorlms-analytics.zip',
					),
				),
			)
		);

		$info = $this->updater( '2.0.6' )->plugin_info( false, 'plugin_information', (object) array( 'slug' => self::SLUG ) );

		$this->assertIsObject( $info );
		$this->assertSame( '2.0.7', $info->version );
		$this->assertSame( 'https://dl/tutorlms-analytics.zip', $info->download_link );
		$this->assertSame( 'https://github.com/owner/repo', $info->homepage );
		$this->assertStringContainsString( 'Release notes', $info->sections['changelog'] );
		$this->assertSame( '2026-08-01T00:00:00Z', $info->last_updated );
	}

	public function test_plugin_info_falls_back_to_the_installed_version(): void {
		$this->seed_release( array() );

		$info = $this->updater( '2.0.6' )->plugin_info( false, 'plugin_information', (object) array( 'slug' => self::SLUG ) );

		$this->assertSame( '2.0.6', $info->version );
		$this->assertSame( '', $info->download_link );
		$this->assertSame( '', $info->sections['changelog'] );
	}

	public function test_plugin_info_ignores_other_plugins(): void {
		$updater = $this->updater( '2.0.6' );

		$this->assertFalse( $updater->plugin_info( false, 'plugin_information', (object) array( 'slug' => 'akismet' ) ) );
		$this->assertFalse( $updater->plugin_info( false, 'query_plugins', (object) array( 'slug' => self::SLUG ) ) );
		$this->assertFalse( $updater->plugin_info( false, 'plugin_information', null ) );
	}

	public function test_fix_source_dir_ignores_other_plugins(): void {
		$source = '/tmp/upgrade/other-plugin/';

		$this->assertSame(
			$source,
			$this->updater( '2.0.6' )->fix_source_dir( $source, '/tmp/upgrade/', null, array( 'plugin' => 'akismet/akismet.php' ) )
		);
		$this->assertSame(
			$source,
			$this->updater( '2.0.6' )->fix_source_dir( $source, '/tmp/upgrade/', null, array() )
		);
	}

	public function test_fix_source_dir_keeps_an_already_correct_folder(): void {
		$source = '/tmp/upgrade/' . self::SLUG . '/';

		$this->assertSame(
			$source,
			$this->updater( '2.0.6' )->fix_source_dir(
				$source,
				'/tmp/upgrade/',
				null,
				array( 'plugin' => plugin_basename( self::plugin_file() ) )
			)
		);
	}

	public function test_register_hooks_into_the_update_machinery(): void {
		$this->updater( '2.0.6' )->register();

		$this->assertArrayHasKey( 'pre_set_site_transient_update_plugins', $GLOBALS['mock_filters'] );
		$this->assertArrayHasKey( 'plugins_api', $GLOBALS['mock_filters'] );
		$this->assertArrayHasKey( 'upgrader_source_selection', $GLOBALS['mock_filters'] );
	}

	/**
	 * @dataProvider unusable_repos
	 */
	public function test_register_does_nothing_without_a_usable_repo( string $repo ): void {
		$this->updater( '2.0.6', $repo )->register();

		$this->assertSame( array(), $GLOBALS['mock_filters'] );
	}

	public static function unusable_repos(): array {
		return array(
			'empty'        => array( '' ),
			'no owner'     => array( 'TutorLMS-Analytics' ),
			'only slashes' => array( '///' ),
		);
	}
}
