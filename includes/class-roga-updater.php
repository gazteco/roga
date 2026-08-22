<?php
/**
 * One click updates served from GitHub Releases.
 *
 * The plugin advertises itself to WordPress like any hosted extension: the
 * update badge, the "View details" modal and the Update button all work.
 *
 * Configuration, in order of precedence:
 *   1. The ROGA_GITHUB_REPO / ROGA_GITHUB_TOKEN constants (wp-config.php).
 *   2. The roga_github_repo / roga_github_token options (Réglages screen).
 *   3. The defaults below.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

class ROGA_Updater {

	const DEFAULT_REPO   = 'gazteco/roga';
	const CACHE_KEY      = 'roga_release_cache';
	const CACHE_LIFETIME = 6 * HOUR_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_source_folder' ), 10, 4 );
		add_filter( 'http_request_args', array( __CLASS__, 'authorise_download' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush_cache' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_action( 'admin_post_roga_check_update', array( __CLASS__, 'manual_check' ) );
	}

	/**
	 * The configured repository, as "owner/name".
	 *
	 * @return string
	 */
	public static function repo() {
		if ( defined( 'ROGA_GITHUB_REPO' ) && ROGA_GITHUB_REPO ) {
			return ROGA_GITHUB_REPO;
		}

		$option = get_option( 'gzf_github_repo' );

		return $option ? $option : self::DEFAULT_REPO;
	}

	/**
	 * The access token, needed only for a private repository.
	 *
	 * @return string
	 */
	public static function token() {
		if ( defined( 'ROGA_GITHUB_TOKEN' ) && ROGA_GITHUB_TOKEN ) {
			return ROGA_GITHUB_TOKEN;
		}

		return (string) get_option( 'gzf_github_token', '' );
	}

	/**
	 * Plugin basename, e.g. roga/roga.php.
	 *
	 * @return string
	 */
	public static function basename() {
		return plugin_basename( ROGA_FILE );
	}

	/**
	 * Fetches the latest release, cached.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array|null Normalised release data.
	 */
	public static function latest_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return empty( $cached['version'] ) ? null : $cached;
			}
		}

		$repo = self::repo();
		if ( ! $repo || false === strpos( $repo, '/' ) ) {
			return null;
		}

		$args = array(
			'timeout' => 12,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'GazteForms/' . ROGA_VERSION . '; ' . home_url( '/' ),
			),
		);

		$token = self::token();
		if ( $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( 'https://api.github.com/repos/' . $repo . '/releases/latest', $args );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache the miss briefly so a broken configuration cannot hammer the API.
			set_site_transient( self::CACHE_KEY, array( 'version' => '' ), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_site_transient( self::CACHE_KEY, array( 'version' => '' ), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$release = array(
			'version'   => ltrim( (string) $body['tag_name'], 'vV' ),
			'name'      => isset( $body['name'] ) ? (string) $body['name'] : '',
			'notes'     => isset( $body['body'] ) ? (string) $body['body'] : '',
			'published' => isset( $body['published_at'] ) ? (string) $body['published_at'] : '',
			'package'   => '',
			'private'   => false,
		);

		// Prefer a zip attached to the release: it carries the correct folder name.
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && preg_match( '/\.zip$/i', $asset['name'] ) ) {
					if ( $token ) {
						// The API URL is the only one that accepts a token.
						$release['package'] = isset( $asset['url'] ) ? $asset['url'] : '';
						$release['private'] = true;
					} else {
						$release['package'] = isset( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';
					}
					break;
				}
			}
		}

		// Otherwise fall back to the source archive; the folder gets renamed on install.
		if ( ! $release['package'] && ! empty( $body['zipball_url'] ) ) {
			$release['package'] = (string) $body['zipball_url'];
			$release['private'] = (bool) $token;
		}

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_LIFETIME );

		return $release;
	}

	/**
	 * Advertises the update to WordPress.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = self::latest_release();

		if ( ! $release || ! $release['package'] ) {
			return $transient;
		}

		$basename = self::basename();

		$item = (object) array(
			'id'            => 'github.com/' . self::repo(),
			'slug'          => dirname( $basename ),
			'plugin'        => $basename,
			'new_version'   => $release['version'],
			'url'           => 'https://github.com/' . self::repo(),
			'package'       => $release['package'],
			'icons'         => array(),
			'banners'       => array(),
			'tested'        => '6.8',
			'requires_php'  => '7.4',
			'compatibility' => new stdClass(),
		);

		if ( version_compare( $release['version'], ROGA_VERSION, '>' ) ) {
			$transient->response[ $basename ] = $item;
			unset( $transient->no_update[ $basename ] );
		} else {
			$item->new_version = ROGA_VERSION;
			$transient->no_update[ $basename ] = $item;
			unset( $transient->response[ $basename ] );
		}

		return $transient;
	}

	/**
	 * Feeds the "View details" modal.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action Requested action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || dirname( self::basename() ) !== $args->slug ) {
			return $result;
		}

		$release = self::latest_release();

		if ( ! $release ) {
			return $result;
		}

		$notes = $release['notes'] ? $release['notes'] : __( 'Aucune note de version.', 'roga' );

		return (object) array(
			'name'          => 'Roga Forms',
			'slug'          => $args->slug,
			'version'       => $release['version'],
			'author'        => '<a href="https://gazteco.fr/">Gazte Co.</a>',
			'homepage'      => 'https://github.com/' . self::repo(),
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'tested'        => '6.8',
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => wpautop( esc_html__( 'Formulaires conversationnels sur mesure, développés pour Gazte Co. Sans publicité ni limitation.', 'roga' ) ),
				'changelog'   => wpautop( esc_html( $notes ) ),
			),
		);
	}

	/**
	 * Renames the extracted folder when the package is a GitHub source archive,
	 * which unpacks as owner-repo-sha rather than the plugin slug.
	 *
	 * @param string      $source        Extracted folder.
	 * @param string      $remote_source Parent folder.
	 * @param WP_Upgrader $upgrader      Upgrader instance.
	 * @param array       $extra         Hook extra.
	 * @return string|WP_Error
	 */
	public static function fix_source_folder( $source, $remote_source, $upgrader, $extra = array() ) {
		global $wp_filesystem;

		$slug = dirname( self::basename() );

		$is_ours = ( isset( $extra['plugin'] ) && self::basename() === $extra['plugin'] )
			|| ( false !== strpos( (string) $source, 'roga' ) );

		if ( ! $is_ours || ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . $slug;

		if ( trailingslashit( $source ) === trailingslashit( $desired ) ) {
			return $source;
		}

		if ( $wp_filesystem->exists( $desired ) ) {
			$wp_filesystem->delete( $desired, true );
		}

		if ( $wp_filesystem->move( $source, $desired ) ) {
			return trailingslashit( $desired );
		}

		return new WP_Error( 'roga_rename_failed', __( 'Impossible de renommer le dossier de l\'extension pendant la mise à jour.', 'roga' ) );
	}

	/**
	 * Adds the token when downloading a private release asset.
	 *
	 * @param array  $args Request arguments.
	 * @param string $url  Requested URL.
	 * @return array
	 */
	public static function authorise_download( $args, $url ) {
		$token = self::token();

		if ( ! $token || false === strpos( (string) $url, 'api.github.com/repos/' . self::repo() ) ) {
			return $args;
		}

		$args['headers'] = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$args['headers']['Authorization'] = 'Bearer ' . $token;
		$args['headers']['Accept']        = 'application/octet-stream';
		$args['headers']['User-Agent']    = 'GazteForms/' . ROGA_VERSION;

		return $args;
	}

	/**
	 * Clears the cached release after any update run.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $extra    Hook extra.
	 */
	public static function flush_cache( $upgrader, $extra ) {
		if ( isset( $extra['type'] ) && 'plugin' === $extra['type'] ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Adds a "check now" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @param string   $file  Plugin file.
	 * @return string[]
	 */
	public static function row_meta( $links, $file ) {
		if ( self::basename() !== $file || ! current_user_can( 'update_plugins' ) ) {
			return $links;
		}

		$url = wp_nonce_url( admin_url( 'admin-post.php?action=roga_check_update' ), 'roga_check_update' );

		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Vérifier les mises à jour', 'roga' ) . '</a>';

		return $links;
	}

	/**
	 * Forces a fresh look at the release feed.
	 */
	public static function manual_check() {
		check_admin_referer( 'roga_check_update' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'roga' ) );
		}

		delete_site_transient( self::CACHE_KEY );
		self::latest_release( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}
}
