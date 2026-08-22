<?php
/**
 * Reading and writing submitted entries.
 *
 * @package Roga
 */

defined( 'ABSPATH' ) || exit;

class ROGA_Entries {

	/**
	 * Stores one submission.
	 *
	 * @param int   $form_id Form post ID.
	 * @param array $data    Map of field id => answer.
	 * @param array $meta    Extra context (labels, page url...).
	 * @param bool  $keep_ip Whether the visitor IP should be recorded.
	 * @return int|false Insert ID.
	 */
	public static function insert( $form_id, $data, $meta = array(), $keep_ip = false ) {
		global $wpdb;

		$table = ROGA_Install::entries_table();

		$ok = $wpdb->insert(
			$table,
			array(
				'form_id'    => (int) $form_id,
				'created_at' => current_time( 'mysql' ),
				'status'     => 'new',
				'data'       => wp_json_encode( $data ),
				'meta'       => wp_json_encode( $meta ),
				'ip'         => $keep_ip ? self::client_ip() : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Lists entries.
	 *
	 * @param array $args form_id, status, per_page, page, search.
	 * @return array{items:array,total:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'form_id'  => 0,
				'status'   => '',
				'per_page' => 25,
				'page'     => 1,
				'search'   => '',
			)
		);

		$table  = ROGA_Install::entries_table();
		$where  = array( '1=1' );
		$params = array();

		if ( $args['form_id'] ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( '' !== $args['search'] ) {
			$where[]  = 'data LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$clause = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$clause}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql ); // phpcs:ignore WordPress.DB

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * $per_page );

		$list_sql    = "SELECT * FROM {$table} WHERE {$clause} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ), ARRAY_A ); // phpcs:ignore WordPress.DB

		foreach ( $rows as &$row ) {
			$row['data'] = json_decode( $row['data'], true );
			$row['meta'] = json_decode( $row['meta'], true );
		}

		return array(
			'items' => $rows ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Reads one entry.
	 *
	 * @param int $id Entry ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = ROGA_Install::entries_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB

		if ( ! $row ) {
			return null;
		}

		$row['data'] = json_decode( $row['data'], true );
		$row['meta'] = json_decode( $row['meta'], true );

		return $row;
	}

	/**
	 * Deletes one entry.
	 *
	 * @param int $id Entry ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( ROGA_Install::entries_table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Marks an entry read or unread.
	 *
	 * @param int    $id     Entry ID.
	 * @param string $status new|read.
	 * @return bool
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;

		$status = in_array( $status, array( 'new', 'read' ), true ) ? $status : 'new';

		return (bool) $wpdb->update(
			ROGA_Install::entries_table(),
			array( 'status' => $status ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Counts unread entries, optionally for one form.
	 *
	 * @param int $form_id Form post ID, 0 for all.
	 * @return int
	 */
	public static function count_new( $form_id = 0 ) {
		global $wpdb;

		$table = ROGA_Install::entries_table();

		if ( $form_id ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'new' AND form_id = %d", (int) $form_id ) ); // phpcs:ignore WordPress.DB
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'new'" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Best effort visitor IP, truncated to stay proportionate.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( $ip, 0, 100 );
	}
}
