<?php
/**
 * Role-based permissions.
 *
 * The point of the custom capabilities is that a Portfolio Manager can run the
 * whole portfolio without being able to install a plugin or change a theme —
 * which is what "role-based permissions" has to mean on a business site.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Roles {

	const VERSION_OPTION = 'bcms_roles_version';
	const VERSION        = 3;

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'protect_enquiries' ), 10, 4 );
	}

	/**
	 * Capabilities are additive across upgrades, so bumping VERSION re-applies
	 * them without an uninstall.
	 */
	public static function maybe_install(): void {
		if ( (int) get_option( self::VERSION_OPTION ) === self::VERSION ) {
			return;
		}
		self::install();
		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * @return string[]
	 */
	public static function project_caps(): array {
		return array(
			'edit_bcms_project',
			'read_bcms_project',
			'delete_bcms_project',
			'edit_bcms_projects',
			'edit_others_bcms_projects',
			'publish_bcms_projects',
			'read_private_bcms_projects',
			'delete_bcms_projects',
			'delete_private_bcms_projects',
			'delete_published_bcms_projects',
			'delete_others_bcms_projects',
			'edit_private_bcms_projects',
			'edit_published_bcms_projects',
		);
	}

	/**
	 * @return string[]
	 */
	public static function enquiry_caps(): array {
		return array(
			'edit_bcms_enquiry',
			'read_bcms_enquiry',
			'delete_bcms_enquiry',
			'edit_bcms_enquiries',
			'edit_others_bcms_enquiries',
			'read_private_bcms_enquiries',
			'delete_bcms_enquiries',
			'delete_others_bcms_enquiries',
			'delete_published_bcms_enquiries',
			'edit_published_bcms_enquiries',
		);
	}

	public static function install(): void {
		$all = array_merge( self::project_caps(), self::enquiry_caps(), array( 'manage_bcms_terms' ) );

		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $all as $cap ) {
				$role->add_cap( $cap );
			}
		}

		// Owns the portfolio and the enquiry inbox. Cannot touch plugins,
		// themes, users or settings.
		remove_role( 'bcms_portfolio_manager' );
		add_role(
			'bcms_portfolio_manager',
			__( 'Portfolio Manager', 'business-cms' ),
			array_merge(
				array(
					'read'                   => true,
					'upload_files'           => true,
					'edit_posts'             => true,
					'edit_published_posts'   => true,
					'publish_posts'          => true,
					'delete_posts'           => true,
					'edit_pages'             => true,
					'edit_published_pages'   => true,
					'unfiltered_html'        => false,
				),
				array_fill_keys( self::project_caps(), true ),
				array_fill_keys( self::enquiry_caps(), true ),
				array( 'manage_bcms_terms' => true )
			)
		);

		// Writes case studies, cannot publish them and never sees the inbox.
		remove_role( 'bcms_case_study_writer' );
		add_role(
			'bcms_case_study_writer',
			__( 'Case Study Writer', 'business-cms' ),
			array(
				'read'                          => true,
				'upload_files'                  => true,
				'edit_bcms_project'             => true,
				'read_bcms_project'             => true,
				'delete_bcms_project'           => true,
				'edit_bcms_projects'            => true,
				'edit_published_bcms_projects'  => true,
				'delete_bcms_projects'          => true,
			)
		);
	}

	/**
	 * Enquiries hold contact details of real people, so the inbox is gated on a
	 * capability that the writer role does not have — not merely hidden from
	 * the menu, which a guessed URL would walk straight past.
	 *
	 * @param string[] $caps
	 * @param string   $cap
	 * @param int      $user_id
	 * @param mixed[]  $args
	 * @return string[]
	 */
	public static function protect_enquiries( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'create_bcms_enquiries' === $cap ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	public static function uninstall(): void {
		remove_role( 'bcms_portfolio_manager' );
		remove_role( 'bcms_case_study_writer' );
		delete_option( self::VERSION_OPTION );
	}
}
