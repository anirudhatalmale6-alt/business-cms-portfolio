<?php
/**
 * Admin experience and one-time setup chores.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Admin {

	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'image_sizes' ) );
		add_action( 'admin_init', array( $this, 'maybe_flush' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BCMS_FILE ), array( $this, 'action_links' ) );
		add_filter( 'image_size_names_choose', array( $this, 'size_names' ) );
		add_action( 'admin_notices', array( $this, 'setup_notice' ) );
	}

	/**
	 * Cards are cropped to a fixed ratio so a mixed bag of client-supplied
	 * photography still lines up in the grid.
	 */
	public function image_sizes(): void {
		add_image_size( 'bcms-card', 800, 600, true );
		add_image_size( 'bcms-hero', 1920, 900, true );
		add_image_size( 'bcms-social', 1200, 630, true );
	}

	/**
	 * @param array<string,string> $sizes
	 * @return array<string,string>
	 */
	public function size_names( array $sizes ): array {
		$sizes['bcms-card'] = __( 'Portfolio card', 'business-cms' );
		$sizes['bcms-hero'] = __( 'Case study hero', 'business-cms' );
		return $sizes;
	}

	public function maybe_flush(): void {
		if ( get_transient( 'bcms_flush_rewrites' ) ) {
			delete_transient( 'bcms_flush_rewrites' );
			flush_rewrite_rules( false );
		}
	}

	public function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'bcms-settings' ) ) {
			return;
		}

		wp_enqueue_script( 'bcms-admin', BCMS_URL . 'assets/admin.js', array(), BCMS_VERSION, true );
		wp_localize_script(
			'bcms-admin',
			'BCMS_ADMIN',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'bcms_test_webhook' ),
				'i18n'  => array(
					'testing' => __( 'Testing…', 'business-cms' ),
					'failed'  => __( 'Request failed.', 'business-cms' ),
				),
			)
		);
	}

	public function dashboard_widget(): void {
		if ( ! current_user_can( 'edit_bcms_projects' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'bcms_overview', __( 'Portfolio at a glance', 'business-cms' ), array( $this, 'render_widget' ) );
	}

	public function render_widget(): void {
		$projects = wp_count_posts( BCMS_Post_Types::PROJECT );
		$counts   = wp_count_posts( BCMS_Post_Types::ENQUIRY );

		$recent = get_posts(
			array(
				'post_type'      => BCMS_Post_Types::ENQUIRY,
				'posts_per_page' => 5,
				'post_status'    => 'publish',
			)
		);
		?>
		<ul style="margin:0 0 1em">
			<li><strong><?php echo esc_html( (string) ( $projects->publish ?? 0 ) ); ?></strong> <?php esc_html_e( 'published projects', 'business-cms' ); ?>
				<?php if ( ! empty( $projects->draft ) ) : ?>
					<em>(<?php echo esc_html( sprintf( _n( '%d draft', '%d drafts', (int) $projects->draft, 'business-cms' ), (int) $projects->draft ) ); ?>)</em>
				<?php endif; ?>
			</li>
			<li><strong><?php echo esc_html( (string) ( $counts->publish ?? 0 ) ); ?></strong> <?php esc_html_e( 'enquiries received', 'business-cms' ); ?></li>
		</ul>

		<?php if ( $recent ) : ?>
			<h3 style="margin:0 0 .4em;font-size:13px"><?php esc_html_e( 'Latest enquiries', 'business-cms' ); ?></h3>
			<ul style="margin:0">
				<?php foreach ( $recent as $enquiry ) : ?>
					<li style="margin-bottom:.3em">
						<a href="<?php echo esc_url( (string) get_edit_post_link( $enquiry->ID ) ); ?>"><?php echo esc_html( $enquiry->post_title ); ?></a>
						<span style="color:#646970">— <?php echo esc_html( human_time_diff( (int) get_post_timestamp( $enquiry ) ) ); ?> <?php esc_html_e( 'ago', 'business-cms' ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<p style="margin-top:1em">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . BCMS_Post_Types::PROJECT ) ); ?>"><?php esc_html_e( 'Add a project', 'business-cms' ); ?></a>
		</p>
		<?php
	}

	/**
	 * @param string[] $links
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . BCMS_Post_Types::PROJECT . '&page=bcms-settings' ) ) . '">' . esc_html__( 'Settings', 'business-cms' ) . '</a>'
		);
		return $links;
	}

	/**
	 * A single, dismissible nudge on the portfolio screens only — not a
	 * site-wide banner that the team learns to ignore.
	 */
	public function setup_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( (string) $screen->id, BCMS_Post_Types::PROJECT ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( wp_count_posts( BCMS_Post_Types::PROJECT )->publish > 0 ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: settings link */
					esc_html__( 'No projects published yet. Add your first case study, then set your company details in %s.', 'business-cms' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . BCMS_Post_Types::PROJECT . '&page=bcms-settings' ) ) . '">' . esc_html__( 'Settings', 'business-cms' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
