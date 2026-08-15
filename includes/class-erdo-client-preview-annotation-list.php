<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Erdo_Client_Preview_Annotation_List extends WP_List_Table {

	private Erdo_Client_Preview_DB $db;

	public function __construct( Erdo_Client_Preview_DB $db ) {
		parent::__construct( array(
			'singular' => 'erdo_client_preview_annotation',
			'plural'   => 'erdo_client_preview_annotations',
			'ajax'     => false,
		) );
		$this->db = $db;
	}

	public function get_columns(): array {
		return array(
			'cb'      => '<input type="checkbox" />',
			'page'    => __( 'Page', 'erdo-client-preview' ),
			'message' => __( 'Note', 'erdo-client-preview' ),
			'status'  => __( 'Status', 'erdo-client-preview' ),
			'reply'   => __( 'Reply', 'erdo-client-preview' ),
			'date'    => __( 'Date', 'erdo-client-preview' ),
		);
	}

	public function get_bulk_actions(): array {
		return array(
			'mark_completed'   => __( 'Mark as Completed', 'erdo-client-preview' ),
			'mark_in_progress' => __( 'Mark as In Progress', 'erdo-client-preview' ),
			'delete'           => __( 'Delete', 'erdo-client-preview' ),
		);
	}

	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="annotation_ids[]" value="%d" />', (int) $item->id );
	}

	protected function column_page( $item ): string {
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'        => 'erdo_client_preview_annotation_delete',
					'annotation_id' => $item->id,
					'tab'           => 'annotations',
				),
				admin_url( 'admin.php?page=erdo-client-preview' )
			),
			'erdo_client_preview_annotation_delete_' . $item->id
		);

		$row_actions = array(
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
				esc_url( $delete_url ),
				esc_js( __( 'Are you sure you want to delete this annotation?', 'erdo-client-preview' ) ),
				__( 'Delete', 'erdo-client-preview' )
			),
		);

		return sprintf(
			'<a href="%1$s" target="_blank">%2$s</a><br />%3$s',
			esc_url( home_url( $item->page_url ) ),
			esc_html( $item->page_url ),
			esc_html( $item->author_name )
		) . $this->row_actions( $row_actions );
	}

	protected function column_message( $item ): string {
		return esc_html( $item->message );
	}

	protected function column_status( $item ): string {
		$status = $item->status;
		$label  = Erdo_Client_Preview_DB::get_feedback_status_label( $status );

		$next_status = Erdo_Client_Preview_DB::FEEDBACK_STATUS_DONE === $status
			? Erdo_Client_Preview_DB::FEEDBACK_STATUS_OPEN
			: Erdo_Client_Preview_DB::FEEDBACK_STATUS_DONE;

		$next_label = Erdo_Client_Preview_DB::FEEDBACK_STATUS_DONE === $next_status
			? __( 'Mark as Completed', 'erdo-client-preview' )
			: __( 'Mark as In Progress', 'erdo-client-preview' );

		$toggle_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'        => 'erdo_client_preview_annotation_status',
					'annotation_id' => $item->id,
					'status'        => $next_status,
					'tab'           => 'annotations',
				),
				admin_url( 'admin.php?page=erdo-client-preview' )
			),
			'erdo_client_preview_annotation_status_' . $item->id
		);

		return sprintf(
			'<span class="erdo-client-preview-feedback-status erdo-client-preview-feedback-status--%1$s">%2$s</span><br /><a href="%3$s">%4$s</a>',
			esc_attr( $status ),
			esc_html( $label ),
			esc_url( $toggle_url ),
			esc_html( $next_label )
		);
	}

	protected function column_reply( $item ): string {
		return sprintf(
			'<div class="erdo-client-preview-feedback-reply" data-item-id="%1$d" data-action="erdo_client_preview_annotation_reply">
				<textarea class="erdo-client-preview-feedback-reply-input" rows="2" placeholder="%2$s">%3$s</textarea>
				<button type="button" class="button button-small erdo-client-preview-feedback-reply-save">%4$s</button>
				<span class="erdo-client-preview-feedback-reply-status" aria-live="polite"></span>
			</div>',
			(int) $item->id,
			esc_attr__( 'Write a reply…', 'erdo-client-preview' ),
			esc_textarea( $item->admin_reply ),
			esc_html__( 'Save Reply', 'erdo-client-preview' )
		);
	}

	protected function column_date( $item ): string {
		$ts = strtotime( $item->created_at );
		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) );
	}

	protected function column_default( $item, $column_name ): string {
		return '';
	}

	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$this->set_pagination_args( array(
			'total_items' => $this->db->get_annotation_count(),
			'per_page'    => $per_page,
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			array(),
		);

		$this->items = $this->db->get_annotations_paginated( $current_page, $per_page );
	}
}
