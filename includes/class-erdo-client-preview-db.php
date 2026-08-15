<?php
defined( 'ABSPATH' ) || exit;

class Erdo_Client_Preview_DB {

	const FEEDBACK_STATUS_OPEN = 'in_progress';
	const FEEDBACK_STATUS_DONE = 'completed';

	private static ?Erdo_Client_Preview_DB $instance = null;
	private string $feedback_table;
	private string $annotations_table;

	private function __construct() {
		global $wpdb;
		$this->feedback_table    = $wpdb->prefix . 'erdo_client_preview_feedback';
		$this->annotations_table = $wpdb->prefix . 'erdo_client_preview_annotations';
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate(): void {
		self::get_instance()->create_table();
	}

	public function maybe_upgrade(): void {
		if ( get_option( 'erdo_client_preview_db_version' ) !== ERDO_CLIENT_PREVIEW_DB_VERSION ) {
			$this->create_table();
		}
	}

	private function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = $this->feedback_table;

		$sql_feedback = "CREATE TABLE $table (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  author_name varchar(100) NOT NULL DEFAULT '',
  message text NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'in_progress',
  admin_reply text NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id)
) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_feedback );

		$annotations_table = $this->annotations_table;

		$sql_annotations = "CREATE TABLE $annotations_table (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  page_url varchar(500) NOT NULL DEFAULT '',
  selector text NOT NULL,
  x_percent decimal(6,2) NOT NULL DEFAULT '0.00',
  y_percent decimal(6,2) NOT NULL DEFAULT '0.00',
  page_x_percent decimal(6,2) NOT NULL DEFAULT '0.00',
  page_y_percent decimal(6,2) NOT NULL DEFAULT '0.00',
  message text NOT NULL,
  author_name varchar(100) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'in_progress',
  admin_reply text NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id)
) $charset;";

		dbDelta( $sql_annotations );

		update_option( 'erdo_client_preview_db_version', ERDO_CLIENT_PREVIEW_DB_VERSION );
	}

	// -------------------------------------------------------------------------
	// Visitor feedback (own table — not stored as WordPress comments)
	// -------------------------------------------------------------------------

	public function add_feedback( string $name, string $message ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			$this->feedback_table,
			array(
				'author_name' => $name,
				'message'     => $message,
				'status'      => self::FEEDBACK_STATUS_OPEN,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	public function update_feedback_status( int $id, string $status ): bool {
		if ( ! in_array( $status, array( self::FEEDBACK_STATUS_OPEN, self::FEEDBACK_STATUS_DONE ), true ) ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->feedback_table,
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	public function update_feedback_reply( int $id, string $reply ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->feedback_table,
			array( 'admin_reply' => $reply ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	public static function get_feedback_status_label( string $status ): string {
		if ( self::FEEDBACK_STATUS_DONE === $status ) {
			return __( 'Completed', 'erdo-client-preview' );
		}

		return __( 'In Progress', 'erdo-client-preview' );
	}

	public function get_feedback_paginated( int $page, int $per_page ): array {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;
		$table  = esc_sql( $this->feedback_table );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $rows ?: array();
	}

	public function get_feedback_count(): int {
		global $wpdb;

		$table = esc_sql( $this->feedback_table );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	public function delete_feedback( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->delete( $this->feedback_table, array( 'id' => $id ), array( '%d' ) );
	}

	public function delete_feedback_bulk( array $ids ): void {
		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;

		$table        = esc_sql( $this->feedback_table );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$placeholders})", $ids ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	public function update_feedback_status_bulk( array $ids, string $status ): void {
		if ( ! in_array( $status, array( self::FEEDBACK_STATUS_OPEN, self::FEEDBACK_STATUS_DONE ), true ) ) {
			return;
		}

		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;

		$table        = esc_sql( $this->feedback_table );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( $status ), $ids );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET status = %s WHERE id IN ({$placeholders})", $params ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Fetch the current status of a set of feedback rows by id.
	 *
	 * Used to sync a visitor's locally stored feedback history with the
	 * latest status set by the site admin.
	 *
	 * @param int[] $ids Feedback IDs.
	 * @return array
	 */
	public function get_feedback_by_ids( array $ids ): array {
		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;

		$table        = esc_sql( $this->feedback_table );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, status, admin_reply FROM `{$table}` WHERE id IN ({$placeholders})", $ids ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return $rows ?: array();
	}

	// -------------------------------------------------------------------------
	// Visual annotations (own table)
	// -------------------------------------------------------------------------

	public function add_annotation( string $page_url, string $selector, float $x_percent, float $y_percent, float $page_x_percent, float $page_y_percent, string $message, string $name ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			$this->annotations_table,
			array(
				'page_url'       => $page_url,
				'selector'       => $selector,
				'x_percent'      => $x_percent,
				'y_percent'      => $y_percent,
				'page_x_percent' => $page_x_percent,
				'page_y_percent' => $page_y_percent,
				'message'        => $message,
				'author_name'    => $name,
				'status'         => self::FEEDBACK_STATUS_OPEN,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	public function get_annotations_for_page( string $page_url ): array {
		global $wpdb;

		$table = esc_sql( $this->annotations_table );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE page_url = %s ORDER BY id ASC", $page_url ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $rows ?: array();
	}

	public function get_annotations_paginated( int $page, int $per_page ): array {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;
		$table  = esc_sql( $this->annotations_table );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $rows ?: array();
	}

	public function get_annotation_count(): int {
		global $wpdb;

		$table = esc_sql( $this->annotations_table );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	public function update_annotation_status( int $id, string $status ): bool {
		if ( ! in_array( $status, array( self::FEEDBACK_STATUS_OPEN, self::FEEDBACK_STATUS_DONE ), true ) ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->annotations_table,
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	public function update_annotation_reply( int $id, string $reply ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->annotations_table,
			array( 'admin_reply' => $reply ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	public function delete_annotation( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->delete( $this->annotations_table, array( 'id' => $id ), array( '%d' ) );
	}

	public function delete_annotation_bulk( array $ids ): void {
		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;

		$table        = esc_sql( $this->annotations_table );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$placeholders})", $ids ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	public function update_annotation_status_bulk( array $ids, string $status ): void {
		if ( ! in_array( $status, array( self::FEEDBACK_STATUS_OPEN, self::FEEDBACK_STATUS_DONE ), true ) ) {
			return;
		}

		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;

		$table        = esc_sql( $this->annotations_table );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$params       = array_merge( array( $status ), $ids );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET status = %s WHERE id IN ({$placeholders})", $params ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Fetch the current status of a set of annotation rows by id.
	 *
	 * Used to sync pin markers on the page with the latest status and
	 * admin reply set by the site admin.
	 *
	 * @param int[] $ids Annotation IDs.
	 * @return array
	 */
	public function get_annotations_by_ids( array $ids ): array {
		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			return array();
		}

		global $wpdb;

		$table        = esc_sql( $this->annotations_table );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, status, admin_reply FROM `{$table}` WHERE id IN ({$placeholders})", $ids ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return $rows ?: array();
	}

}
