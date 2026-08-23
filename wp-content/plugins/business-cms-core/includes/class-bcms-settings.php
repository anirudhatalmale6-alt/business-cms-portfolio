<?php
/**
 * One option row holds every setting, so the whole configuration is a single
 * autoloaded read rather than a dozen scattered options.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Settings {

	const OPTION = 'bcms_settings';

	/** @var array<string,mixed>|null */
	private static ?array $cache = null;

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'bust_cache' ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'company_name'      => get_bloginfo( 'name' ),
			'company_tagline'   => get_bloginfo( 'description' ),
			'company_logo_id'   => 0,
			'contact_email'     => get_option( 'admin_email' ),
			'contact_phone'     => '',
			'contact_address'   => '',
			'project_slug'      => 'work',
			'industry_slug'     => 'industry',
			'archive_per_page'  => 9,
			'ga4_id'            => '',
			'matomo_url'        => '',
			'matomo_site_id'    => '',
			'turnstile_site'    => '',
			'turnstile_secret'  => '',
			'zapier_webhook'    => '',
			'notify_email'      => get_option( 'admin_email' ),
			'seo_default_image' => 0,
			'seo_twitter'       => '',
			'linkedin_url'      => '',
			'honeypot_field'    => 'bcms_website_url',
			'min_fill_seconds'  => 3,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( string $key, $fallback = '' ) {
		$all = self::all();
		if ( ! array_key_exists( $key, $all ) ) {
			return $fallback;
		}
		return ( '' === $all[ $key ] || null === $all[ $key ] ) && '' !== $fallback ? $fallback : $all[ $key ];
	}

	public static function bust_cache(): void {
		self::$cache = null;
	}

	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . BCMS_Post_Types::PROJECT,
			__( 'Business CMS Settings', 'business-cms' ),
			__( 'Settings', 'business-cms' ),
			'manage_options',
			'bcms-settings',
			array( $this, 'render' )
		);
	}

	public function register(): void {
		register_setting(
			'bcms_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = self::all();

		$text = array( 'company_name', 'company_tagline', 'contact_phone', 'ga4_id', 'matomo_site_id', 'turnstile_site', 'seo_twitter', 'honeypot_field', 'project_slug', 'industry_slug' );
		foreach ( $text as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		foreach ( array( 'matomo_url', 'zapier_webhook', 'linkedin_url' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = esc_url_raw( trim( wp_unslash( $input[ $key ] ) ) );
			}
		}

		foreach ( array( 'contact_email', 'notify_email' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_email( wp_unslash( $input[ $key ] ) );
			}
		}

		if ( isset( $input['contact_address'] ) ) {
			$out['contact_address'] = sanitize_textarea_field( wp_unslash( $input['contact_address'] ) );
		}

		foreach ( array( 'company_logo_id', 'seo_default_image', 'archive_per_page', 'min_fill_seconds' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = absint( $input[ $key ] );
			}
		}

		// A blank secret means "leave the stored one alone", so the saved key is
		// never echoed back into the page just to survive a save.
		if ( isset( $input['turnstile_secret'] ) && '' !== trim( (string) $input['turnstile_secret'] ) ) {
			$out['turnstile_secret'] = sanitize_text_field( wp_unslash( $input['turnstile_secret'] ) );
		}

		$out['project_slug']     = sanitize_title( $out['project_slug'] ?: 'work' );
		$out['industry_slug']    = sanitize_title( $out['industry_slug'] ?: 'industry' );
		$out['archive_per_page'] = max( 3, min( 48, (int) $out['archive_per_page'] ) );

		self::$cache = null;
		// Slugs feed rewrite rules; without this the new URLs 404 until someone
		// visits the permalinks screen.
		set_transient( 'bcms_flush_rewrites', 1, 60 );

		return $out;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Business CMS Settings', 'business-cms' ); ?></h1>
			<p class="description" style="max-width:46em">
				<?php esc_html_e( 'Everything the site needs that is not page content. Nothing here is required for the site to work — leave a field empty and that feature stays off.', 'business-cms' ); ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'bcms_settings_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Company', 'business-cms' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row( 'company_name', __( 'Company name', 'business-cms' ), $s );
					$this->text_row( 'company_tagline', __( 'Tagline', 'business-cms' ), $s );
					$this->text_row( 'contact_email', __( 'Public contact email', 'business-cms' ), $s, 'email' );
					$this->text_row( 'contact_phone', __( 'Phone', 'business-cms' ), $s );
					$this->text_row( 'linkedin_url', __( 'LinkedIn URL', 'business-cms' ), $s, 'url' );
					?>
					<tr>
						<th scope="row"><label for="bcms_contact_address"><?php esc_html_e( 'Address', 'business-cms' ); ?></label></th>
						<td><textarea id="bcms_contact_address" name="<?php echo esc_attr( self::OPTION ); ?>[contact_address]" rows="3" class="large-text"><?php echo esc_textarea( $s['contact_address'] ); ?></textarea></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Portfolio', 'business-cms' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row( 'project_slug', __( 'Portfolio URL slug', 'business-cms' ), $s, 'text', home_url( '/' ) . '<strong>' . esc_html( $s['project_slug'] ) . '</strong>/' );
					$this->text_row( 'industry_slug', __( 'Industry URL slug', 'business-cms' ), $s );
					$this->text_row( 'archive_per_page', __( 'Projects per page', 'business-cms' ), $s, 'number' );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Analytics', 'business-cms' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row( 'ga4_id', __( 'Google Analytics 4 ID', 'business-cms' ), $s, 'text', 'G-XXXXXXXXXX' );
					$this->text_row( 'matomo_url', __( 'Matomo URL', 'business-cms' ), $s, 'url', 'https://analytics.example.com/' );
					$this->text_row( 'matomo_site_id', __( 'Matomo site ID', 'business-cms' ), $s );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Contact form', 'business-cms' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					$this->text_row( 'notify_email', __( 'Send enquiries to', 'business-cms' ), $s, 'email' );
					$this->text_row( 'turnstile_site', __( 'Cloudflare Turnstile site key', 'business-cms' ), $s, 'text', __( 'Optional. The honeypot and timing checks run with or without this.', 'business-cms' ) );
					?>
					<tr>
						<th scope="row"><label for="bcms_turnstile_secret"><?php esc_html_e( 'Turnstile secret key', 'business-cms' ); ?></label></th>
						<td>
							<input type="password" id="bcms_turnstile_secret" name="<?php echo esc_attr( self::OPTION ); ?>[turnstile_secret]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $s['turnstile_secret'] ? esc_attr__( '•••••••• (saved — leave blank to keep)', 'business-cms' ) : ''; ?>">
							<p class="description"><?php esc_html_e( 'Never displayed once saved. Leave blank to keep the existing key.', 'business-cms' ); ?></p>
						</td>
					</tr>
					<?php $this->text_row( 'min_fill_seconds', __( 'Minimum seconds before submit', 'business-cms' ), $s, 'number', __( 'Bots post instantly. Anything faster than this is rejected.', 'business-cms' ) ); ?>
				</table>

				<h2 class="title"><?php esc_html_e( 'CRM hand-off', 'business-cms' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php $this->text_row( 'zapier_webhook', __( 'Zapier / Make webhook URL', 'business-cms' ), $s, 'url', __( 'Every enquiry is POSTed here as JSON. Leave blank to disable.', 'business-cms' ) ); ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Test', 'business-cms' ); ?></th>
						<td>
							<button type="button" class="button" id="bcms-test-webhook"><?php esc_html_e( 'Send a test enquiry', 'business-cms' ); ?></button>
							<span id="bcms-test-result" style="margin-left:.6em"></span>
							<p class="description"><?php esc_html_e( 'Save first, then test. The result below is the live response from your webhook.', 'business-cms' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $s
	 */
	private function text_row( string $key, string $label, array $s, string $type = 'text', string $help = '' ): void {
		?>
		<tr>
			<th scope="row"><label for="bcms_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>" id="bcms_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $s[ $key ] ); ?>" class="regular-text">
				<?php if ( $help ) : ?>
					<p class="description"><?php echo wp_kses_post( $help ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
