<?php
/**
 * Contact form: rendering, spam filtering, storage and notification.
 *
 * Three independent spam gates, cheapest first: a honeypot field, a minimum
 * fill time, and optionally Cloudflare Turnstile. The first two cost the
 * visitor nothing and stop the overwhelming majority of drive-by bots; the
 * third is there for when a site starts getting targeted specifically.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Forms {

	const NONCE = 'bcms_contact';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_action( 'add_meta_boxes', array( $this, 'enquiry_meta_box' ) );
	}

	public function maybe_enqueue(): void {
		// The stylesheet is small and needed by any page that renders a card, so
		// it ships site-wide; the script is registered here but only enqueued by
		// the blocks that actually need it.
		wp_enqueue_style( 'bcms-front', BCMS_URL . 'assets/front.css', array(), BCMS_VERSION );
		wp_register_script( 'bcms-front', BCMS_URL . 'assets/front.js', array(), BCMS_VERSION, true );
		wp_localize_script(
			'bcms-front',
			'BCMS_FRONT',
			array(
				'root'  => esc_url_raw( rest_url( 'bcms/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'  => array(
					'sending'   => __( 'Sending…', 'business-cms' ),
					'error'     => __( 'Something went wrong. Please try again, or email us directly.', 'business-cms' ),
					'required'  => __( 'Please fill in this field.', 'business-cms' ),
					'email'     => __( 'Please enter a valid email address.', 'business-cms' ),
					'loading'   => __( 'Loading projects…', 'business-cms' ),
				),
			)
		);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function render( array $attributes = array() ): string {
		wp_enqueue_script( 'bcms-front' );

		$site_key = (string) BCMS_Settings::get( 'turnstile_site', '' );
		if ( $site_key ) {
			wp_enqueue_script( 'bcms-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
		}

		$heading = (string) ( $attributes['heading'] ?? '' );
		$button  = (string) ( $attributes['buttonLabel'] ?? '' );
		$success = (string) ( $attributes['successMessage'] ?? '' );
		$honey   = (string) BCMS_Settings::get( 'honeypot_field', 'bcms_website_url' );

		ob_start();
		?>
		<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'bcms-form-block' ) ) ); ?>>
			<?php if ( '' !== $heading ) : ?>
				<h2 class="bcms-form-heading"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>

			<form class="bcms-form" method="post" novalidate
				data-bcms-form="1"
				data-success="<?php echo esc_attr( $success ?: __( 'Thank you — your message is with us. We reply to every enquiry within one working day.', 'business-cms' ) ); ?>">

				<div class="bcms-field">
					<label for="bcms-name"><?php esc_html_e( 'Your name', 'business-cms' ); ?> <span class="bcms-req" aria-hidden="true">*</span></label>
					<input type="text" id="bcms-name" name="name" required autocomplete="name">
					<span class="bcms-error" data-error-for="name"></span>
				</div>

				<div class="bcms-field">
					<label for="bcms-email"><?php esc_html_e( 'Email', 'business-cms' ); ?> <span class="bcms-req" aria-hidden="true">*</span></label>
					<input type="email" id="bcms-email" name="email" required autocomplete="email">
					<span class="bcms-error" data-error-for="email"></span>
				</div>

				<?php if ( ! empty( $attributes['showCompany'] ) ) : ?>
					<div class="bcms-field">
						<label for="bcms-company"><?php esc_html_e( 'Company', 'business-cms' ); ?></label>
						<input type="text" id="bcms-company" name="company" autocomplete="organization">
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $attributes['showPhone'] ) ) : ?>
					<div class="bcms-field">
						<label for="bcms-phone"><?php esc_html_e( 'Phone', 'business-cms' ); ?></label>
						<input type="tel" id="bcms-phone" name="phone" autocomplete="tel">
					</div>
				<?php endif; ?>

				<div class="bcms-field">
					<label for="bcms-message"><?php esc_html_e( 'How can we help?', 'business-cms' ); ?> <span class="bcms-req" aria-hidden="true">*</span></label>
					<textarea id="bcms-message" name="message" rows="6" required></textarea>
					<span class="bcms-error" data-error-for="message"></span>
				</div>

				<?php // Honeypot. Hidden from people, irresistible to bots. ?>
				<div class="bcms-hp" aria-hidden="true">
					<label for="bcms-<?php echo esc_attr( $honey ); ?>"><?php esc_html_e( 'Leave this field empty', 'business-cms' ); ?></label>
					<input type="text" id="bcms-<?php echo esc_attr( $honey ); ?>" name="<?php echo esc_attr( $honey ); ?>" tabindex="-1" autocomplete="off">
				</div>

				<input type="hidden" name="rendered_at" value="<?php echo esc_attr( (string) time() ); ?>">
				<input type="hidden" name="source" value="<?php echo esc_attr( (string) get_the_ID() ); ?>">

				<?php if ( $site_key ) : ?>
					<div class="cf-turnstile bcms-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-theme="light"></div>
				<?php endif; ?>

				<button type="submit" class="bcms-btn bcms-btn-primary">
					<?php echo esc_html( $button ?: __( 'Send enquiry', 'business-cms' ) ); ?>
				</button>

				<p class="bcms-form-note">
					<?php esc_html_e( 'We use your details only to reply to this enquiry.', 'business-cms' ); ?>
				</p>

				<div class="bcms-form-status" role="status" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function register_routes(): void {
		register_rest_route(
			'bcms/v1',
			'/enquiry',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'submit' ),
			)
		);
	}

	public function submit( WP_REST_Request $request ) {
		$data = array(
			'name'    => sanitize_text_field( (string) $request->get_param( 'name' ) ),
			'email'   => sanitize_email( (string) $request->get_param( 'email' ) ),
			'company' => sanitize_text_field( (string) $request->get_param( 'company' ) ),
			'phone'   => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
			'message' => sanitize_textarea_field( (string) $request->get_param( 'message' ) ),
			'source'  => absint( $request->get_param( 'source' ) ),
		);

		$spam = $this->spam_check( $request );
		if ( is_wp_error( $spam ) ) {
			// Deliberately vague to the caller: telling a bot which gate it
			// tripped is telling it how to get past next time. The real reason
			// goes to the log instead.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[BCMS] enquiry rejected: ' . $spam->get_error_code() );
			}
			return new WP_Error( 'bcms_rejected', __( 'We could not accept that submission. Please try again.', 'business-cms' ), array( 'status' => 400 ) );
		}

		$errors = array();
		if ( '' === $data['name'] ) {
			$errors['name'] = __( 'Please tell us your name.', 'business-cms' );
		}
		if ( '' === $data['email'] || ! is_email( $data['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'business-cms' );
		}
		if ( strlen( trim( $data['message'] ) ) < 10 ) {
			$errors['message'] = __( 'Please give us a little more detail.', 'business-cms' );
		}

		if ( $errors ) {
			return new WP_Error(
				'bcms_invalid',
				__( 'Please check the highlighted fields.', 'business-cms' ),
				array(
					'status' => 422,
					'fields' => $errors,
				)
			);
		}

		$post_id = $this->store( $data );
		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'bcms_store_failed', __( 'We could not save your message. Please email us directly.', 'business-cms' ), array( 'status' => 500 ) );
		}

		$this->notify( $post_id, $data );

		$crm = BCMS_Plugin::instance()->module( 'crm' );
		if ( $crm instanceof BCMS_Crm ) {
			$crm->dispatch( $post_id, $data );
		}

		/**
		 * Fires once an enquiry is stored and dispatched.
		 *
		 * @param int                 $post_id Stored enquiry.
		 * @param array<string,mixed> $data    Sanitised submission.
		 */
		do_action( 'bcms_enquiry_received', $post_id, $data );

		return new WP_REST_Response( array( 'ok' => true ), 201 );
	}

	/**
	 * @return true|WP_Error
	 */
	private function spam_check( WP_REST_Request $request ) {
		$honey = (string) BCMS_Settings::get( 'honeypot_field', 'bcms_website_url' );
		if ( '' !== trim( (string) $request->get_param( $honey ) ) ) {
			return new WP_Error( 'honeypot' );
		}

		$min        = (int) BCMS_Settings::get( 'min_fill_seconds', 3 );
		$rendered   = absint( $request->get_param( 'rendered_at' ) );
		$elapsed    = time() - $rendered;
		if ( $rendered > 0 && $elapsed < $min ) {
			return new WP_Error( 'too_fast' );
		}
		// A form left open for over a day is far more likely to be a replayed
		// capture than a genuinely slow visitor.
		if ( $rendered > 0 && $elapsed > DAY_IN_SECONDS ) {
			return new WP_Error( 'stale' );
		}

		$secret = (string) BCMS_Settings::get( 'turnstile_secret', '' );
		if ( '' !== $secret ) {
			$token = (string) $request->get_param( 'cf-turnstile-response' );
			if ( '' === $token ) {
				return new WP_Error( 'turnstile_missing' );
			}
			if ( ! $this->verify_turnstile( $secret, $token ) ) {
				return new WP_Error( 'turnstile_failed' );
			}
		}

		return true;
	}

	private function verify_turnstile( string $secret, string $token ): bool {
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => $this->ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Cloudflare being unreachable must not silently take the contact
			// form offline; the honeypot and timing gates still applied.
			error_log( '[BCMS] Turnstile unreachable: ' . $response->get_error_message() );
			return true;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) && ! empty( $body['success'] );
	}

	/**
	 * @param array<string,mixed> $data
	 * @return int|WP_Error
	 */
	private function store( array $data ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => BCMS_Post_Types::ENQUIRY,
				'post_status' => 'publish',
				'post_title'  => $data['name'] . ( $data['company'] ? ' — ' . $data['company'] : '' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = array(
			'_bcms_email'    => $data['email'],
			'_bcms_company'  => $data['company'],
			'_bcms_phone'    => $data['phone'],
			'_bcms_message'  => $data['message'],
			'_bcms_ip'       => $this->ip(),
			'_bcms_page'     => $data['source'] ? (string) get_permalink( $data['source'] ) : '',
			'_bcms_referrer' => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return (int) $post_id;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function notify( int $post_id, array $data ): void {
		$to = (string) BCMS_Settings::get( 'notify_email', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}

		$lines = array(
			sprintf( __( 'Name: %s', 'business-cms' ), $data['name'] ),
			sprintf( __( 'Email: %s', 'business-cms' ), $data['email'] ),
		);

		if ( $data['company'] ) {
			$lines[] = sprintf( __( 'Company: %s', 'business-cms' ), $data['company'] );
		}
		if ( $data['phone'] ) {
			$lines[] = sprintf( __( 'Phone: %s', 'business-cms' ), $data['phone'] );
		}

		$lines[] = '';
		$lines[] = $data['message'];
		$lines[] = '';
		$lines[] = sprintf( __( 'Sent from: %s', 'business-cms' ), (string) get_post_meta( $post_id, '_bcms_page', true ) );
		$lines[] = sprintf( __( 'Read it in the dashboard: %s', 'business-cms' ), admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );

		wp_mail(
			$to,
			sprintf(
				/* translators: %s: sender name */
				__( 'New enquiry from %s', 'business-cms' ),
				$data['name']
			),
			implode( "\n", $lines ),
			array(
				'Content-Type: text/plain; charset=UTF-8',
				'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>',
			)
		);
	}

	private function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		// Behind Cloudflare or any reverse proxy REMOTE_ADDR is the proxy, so the
		// real client address has to come from the header the proxy sets.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	public function enquiry_meta_box(): void {
		add_meta_box(
			'bcms-enquiry',
			__( 'Enquiry', 'business-cms' ),
			array( $this, 'render_enquiry_box' ),
			BCMS_Post_Types::ENQUIRY,
			'normal',
			'high'
		);
	}

	public function render_enquiry_box( WP_Post $post ): void {
		$rows = array(
			__( 'Email', 'business-cms' )    => (string) get_post_meta( $post->ID, '_bcms_email', true ),
			__( 'Company', 'business-cms' )  => (string) get_post_meta( $post->ID, '_bcms_company', true ),
			__( 'Phone', 'business-cms' )    => (string) get_post_meta( $post->ID, '_bcms_phone', true ),
			__( 'Page', 'business-cms' )     => (string) get_post_meta( $post->ID, '_bcms_page', true ),
			__( 'Referrer', 'business-cms' ) => (string) get_post_meta( $post->ID, '_bcms_referrer', true ),
			__( 'IP', 'business-cms' )       => (string) get_post_meta( $post->ID, '_bcms_ip', true ),
			__( 'CRM', 'business-cms' )      => (string) get_post_meta( $post->ID, '_bcms_crm_status', true ),
		);
		?>
		<table class="widefat striped" style="margin-bottom:1em">
			<tbody>
			<?php foreach ( array_filter( $rows ) as $label => $value ) : ?>
				<tr>
					<th style="width:140px"><?php echo esc_html( (string) $label ); ?></th>
					<td><?php echo esc_html( $value ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h3><?php esc_html_e( 'Message', 'business-cms' ); ?></h3>
		<div style="padding:12px;background:#f6f7f7;border-radius:3px;white-space:pre-wrap">
			<?php echo esc_html( (string) get_post_meta( $post->ID, '_bcms_message', true ) ); ?>
		</div>
		<?php
	}
}
