<?php
/**
 * Activation, database schema and upgrades.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

class ROGA_Install {

	const DB_VERSION_OPTION = 'gzf_db_version';
	const DB_VERSION        = '1.0.0';

	/**
	 * Runs on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		ROGA_Forms::register_post_type();
		flush_rewrite_rules();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		if ( ! get_option( 'gzf_seeded' ) ) {
			require_once ROGA_DIR . 'includes/presets/lechateau.php';
			$id = ROGA_Forms::create( roga_preset_lechateau() );
			if ( $id ) {
				update_option( 'gzf_seeded', $id );
			}
		}
	}

	/**
	 * Runs on plugin deactivation. Data is intentionally preserved.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Applies the schema when plugin files are updated in place.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	/**
	 * Returns the fully prefixed entries table name.
	 *
	 * @return string
	 */
	public static function entries_table() {
		global $wpdb;
		return $wpdb->prefix . 'gzf_entries';
	}

	/**
	 * Creates or updates the entries table.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::entries_table();
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			data LONGTEXT NULL,
			meta LONGTEXT NULL,
			ip VARCHAR(100) NULL,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY created_at (created_at),
			KEY status (status)
		) {$collate};";

		dbDelta( $sql );
	}
}
