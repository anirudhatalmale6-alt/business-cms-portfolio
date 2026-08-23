<?php
/**
 * CRM hand-off.
 *
 * The enquiry is already saved before this runs, so a webhook that is down,
 * slow or misconfigured can lose a notification but can never lose a lead.
 * Failed deliveries are retried on cron rather than dropped.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Crm {

	const RETRY_HOOK = 'bcms_crm_retry';
	const MAX_TRIES  = 4;

	public function __construct() {
		add_action( self::RETRY_HOOK, array( $this, 'retry' ), 10, 1 );
		add_action( 'wp_ajax_bcms_test_webhook', array( $this, 'ajax_test' ) );
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function dispatch( int $post_id, array $data ): void {
		$url = (string) BCMS_Settings::get( 'zapier_webhook', '' );

		if ( '' === $url ) {
			update_post_meta( $post_id, '_bcms_crm_status', 'disabled' );
			return;
		}

		$sent = $this->post( $url, $this->payload( $post_id, $data ) );

		if ( true === $sent ) {
			update_post_meta( $post_id, '_bcms_crm_status', 'sent' );
			update_post_meta( $post_id, '_bcms_crm_sent_at', (string) time() );
			return;
		}

		update_post_meta( $post_id, '_bcms_crm_status', 'failed' );
		update_post_meta( $post_id, '_bcms_crm_error', (string) $sent );
		$this->schedule_retry( $post_id, 1 );
	}

	private function schedule_retry( int $post_id, int $attempt ): void {
		if ( $attempt > self::MAX_TRIES ) {
			return;
		}
		// Backing off keeps a webhook outage from becoming a cron storm.
		$delay = MINUTE_IN_SECONDS * ( 5 ** ( $attempt - 1 ) );
		update_post_meta( $post_id, '_bcms_crm_attempts', (string) $attempt );
		wp_schedule_single_event( time() + $delay, self::RETRY_HOOK, array( $post_id ) );
	}

	public function retry( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || BCMS_Post_Types::ENQUIRY !== $post->post_type ) {
			return;
		}
		if ( 'sent' === get_post_meta( $post_id, '_bcms_crm_status', true ) ) {
			return;
		}

		$url = (string) BCMS_Settings::get( 'zapier_webhook', '' );
		if ( '' === $url ) {
			return;
		}

		$data = array(
			'name'    => $post->post_title,
			'email'   => (string) get_post_meta( $post_id, '_bcms_email', true ),
			'company' => (string) get_post_meta( $post_id, '_bcms_company', true ),
			'phone'   => (string) get_post_meta( $post_id, '_bcms_phone', true ),
			'message' => (string) get_post_meta( $post_id, '_bcms_message', true ),
		);

		$sent = $this->post( $url, $this->payload( $post_id, $data ) );

		if ( true === $sent ) {
			update_post_meta( $post_id, '_bcms_crm_status', 'sent' );
			update_post_meta( $post_id, '_bcms_crm_sent_at', (string) time() );
			delete_post_meta( $post_id, '_bcms_crm_error' );
			return;
		}

		update_post_meta( $post_id, '_bcms_crm_error', (string) $sent );
		$this->schedule_retry( $post_id, (int) get_post_meta( $post_id, '_bcms_crm_attempts', true ) + 1 );
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>
	 */
	private function payload( int $post_id, array $data ): array {
		$payload = array(
			'event'      => 'enquiry.created',
			'id'         => $post_id,
			'received'   => gmdate( 'c' ),
			'site'       => home_url(),
			'name'       => $data['name'] ?? '',
			'email'      => $data['email'] ?? '',
			'company'    => $data['company'] ?? '',
			'phone'      => $data['phone'] ?? '',
			'message'    => $data['message'] ?? '',
			'page'       => (string) get_post_meta( $post_id, '_bcms_page', true ),
			'referrer'   => (string) get_post_meta( $post_id, '_bcms_referrer', true ),
			'admin_link' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		);

		/**
		 * Filter the JSON body sent to the CRM, for adding owner routing,
		 * lead scores or campaign tags without touching this file.
		 *
		 * @param array<string,mixed> $payload
		 * @param int                 $post_id
		 */
		return (array) apply_filters( 'bcms_crm_payload', $payload, $post_id );
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return true|string True on success, an error string otherwise.
	 */
	private function post( string $url, array $payload ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 12,
				'redirection' => 3,
				'headers'     => array(
					'Content-Type' => 'application/json; charset=utf-8',
					'User-Agent'   => 'BusinessCMS/' . BCMS_VERSION . '; ' . home_url(),
				),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return sprintf( 'HTTP %d: %s', $code, wp_trim_words( (string) wp_remote_retrieve_body( $response ), 20 ) );
	}

	/**
	 * The settings page "send a test" button. Reports the real response so a
	 * broken URL is visible immediately rather than at the first real enquiry.
	 */
	public function ajax_test(): void {
		check_ajax_referer( 'bcms_test_webhook' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'business-cms' ) ), 403 );
		}

		$url = (string) BCMS_Settings::get( 'zapier_webhook', '' );
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'No webhook URL saved yet.', 'business-cms' ) ) );
		}

		$result = $this->post(
			$url,
			array(
				'event'    => 'enquiry.test',
				'received' => gmdate( 'c' ),
				'site'     => home_url(),
				'name'     => 'Test Enquiry',
				'email'    => 'test@example.com',
				'company'  => 'Business CMS',
				'message'  => 'This is a test payload sent from the Business CMS settings screen.',
			)
		);

		if ( true === $result ) {
			wp_send_json_success( array( 'message' => __( 'Webhook accepted the test payload.', 'business-cms' ) ) );
		}

		wp_send_json_error( array( 'message' => $result ) );
	}
}
