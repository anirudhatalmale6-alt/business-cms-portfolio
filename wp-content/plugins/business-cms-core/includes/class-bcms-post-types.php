<?php
/**
 * Content structure: projects, the taxonomies that slice them, and enquiries.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Post_Types {

	const PROJECT = 'bcms_project';
	const ENQUIRY = 'bcms_enquiry';
	const INDUSTRY = 'bcms_industry';
	const SERVICE  = 'bcms_service';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'manage_' . self::PROJECT . '_posts_columns', array( $this, 'project_columns' ) );
		add_action( 'manage_' . self::PROJECT . '_posts_custom_column', array( $this, 'project_column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::PROJECT . '_sortable_columns', array( $this, 'project_sortable' ) );
		add_filter( 'manage_' . self::ENQUIRY . '_posts_columns', array( $this, 'enquiry_columns' ) );
		add_action( 'manage_' . self::ENQUIRY . '_posts_custom_column', array( $this, 'enquiry_column' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'archive_query' ) );
	}

	public function register(): void {
		$this->register_projects();
		$this->register_taxonomies();
		$this->register_enquiries();
	}

	private function register_projects(): void {
		$slug = BCMS_Settings::get( 'project_slug', 'work' );

		register_post_type(
			self::PROJECT,
			array(
				'labels'              => array(
					'name'                  => __( 'Projects', 'business-cms' ),
					'singular_name'         => __( 'Project', 'business-cms' ),
					'add_new'               => __( 'Add Project', 'business-cms' ),
					'add_new_item'          => __( 'Add New Project', 'business-cms' ),
					'edit_item'             => __( 'Edit Project', 'business-cms' ),
					'new_item'              => __( 'New Project', 'business-cms' ),
					'view_item'             => __( 'View Project', 'business-cms' ),
					'search_items'          => __( 'Search Projects', 'business-cms' ),
					'not_found'             => __( 'No projects yet. Add your first one.', 'business-cms' ),
					'not_found_in_trash'    => __( 'No projects in the trash.', 'business-cms' ),
					'all_items'             => __( 'All Projects', 'business-cms' ),
					'featured_image'        => __( 'Hero image', 'business-cms' ),
					'set_featured_image'    => __( 'Set hero image', 'business-cms' ),
					'remove_featured_image' => __( 'Remove hero image', 'business-cms' ),
					'menu_name'             => __( 'Portfolio', 'business-cms' ),
				),
				'description'         => __( 'Case studies shown in the portfolio.', 'business-cms' ),
				'public'              => true,
				'has_archive'         => $slug,
				'rewrite'             => array(
					'slug'       => $slug,
					'with_front' => false,
				),
				'menu_icon'           => 'dashicons-portfolio',
				'menu_position'       => 20,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes' ),
				'show_in_rest'        => true,
				'rest_base'           => 'projects',
				'capability_type'     => array( 'bcms_project', 'bcms_projects' ),
				'map_meta_cap'        => true,
				'exclude_from_search' => false,
				'template'            => array(
					array( 'bcms/project-stats' ),
					array(
						'core/paragraph',
						array( 'placeholder' => __( 'Open with the situation the client was in…', 'business-cms' ) ),
					),
				),
			)
		);
	}

	private function register_taxonomies(): void {
		register_taxonomy(
			self::INDUSTRY,
			array( self::PROJECT ),
			array(
				'labels'            => array(
					'name'          => __( 'Industries', 'business-cms' ),
					'singular_name' => __( 'Industry', 'business-cms' ),
					'add_new_item'  => __( 'Add Industry', 'business-cms' ),
					'menu_name'     => __( 'Industries', 'business-cms' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'industries',
				'rewrite'           => array(
					'slug'       => BCMS_Settings::get( 'industry_slug', 'industry' ),
					'with_front' => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_bcms_terms',
					'edit_terms'   => 'manage_bcms_terms',
					'delete_terms' => 'manage_bcms_terms',
					'assign_terms' => 'edit_bcms_projects',
				),
			)
		);

		register_taxonomy(
			self::SERVICE,
			array( self::PROJECT ),
			array(
				'labels'            => array(
					'name'          => __( 'Services', 'business-cms' ),
					'singular_name' => __( 'Service', 'business-cms' ),
					'menu_name'     => __( 'Services', 'business-cms' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'services',
				'rewrite'           => array(
					'slug'       => 'service',
					'with_front' => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_bcms_terms',
					'edit_terms'   => 'manage_bcms_terms',
					'delete_terms' => 'manage_bcms_terms',
					'assign_terms' => 'edit_bcms_projects',
				),
			)
		);
	}

	/**
	 * Enquiries are stored as posts so nothing is lost if the mail server
	 * or the CRM webhook is down. Not public, not searchable, admin only.
	 */
	private function register_enquiries(): void {
		register_post_type(
			self::ENQUIRY,
			array(
				'labels'          => array(
					'name'          => __( 'Enquiries', 'business-cms' ),
					'singular_name' => __( 'Enquiry', 'business-cms' ),
					'menu_name'     => __( 'Enquiries', 'business-cms' ),
					'not_found'     => __( 'No enquiries received yet.', 'business-cms' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'menu_position'   => 21,
				'supports'        => array( 'title' ),
				'capability_type' => array( 'bcms_enquiry', 'bcms_enquiries' ),
				'map_meta_cap'    => true,
				'capabilities'    => array(
					'create_posts' => 'do_not_allow',
				),
			)
		);
	}

	public function project_columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$out['bcms_thumb'] = __( 'Hero', 'business-cms' );
			}
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['bcms_client'] = __( 'Client', 'business-cms' );
				$out['bcms_year']   = __( 'Year', 'business-cms' );
			}
		}
		return $out;
	}

	public function project_column( string $column, int $post_id ): void {
		if ( 'bcms_thumb' === $column ) {
			echo get_the_post_thumbnail( $post_id, array( 60, 40 ) ) ?: '<span aria-hidden="true" style="display:inline-block;width:60px;height:40px;background:#e4e8ec"></span>';
		} elseif ( 'bcms_client' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_bcms_client', true ) ?: '—' );
		} elseif ( 'bcms_year' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_bcms_year', true ) ?: '—' );
		}
	}

	public function project_sortable( array $columns ): array {
		$columns['bcms_year'] = 'bcms_year';
		return $columns;
	}

	public function enquiry_columns( array $columns ): array {
		return array(
			'cb'          => $columns['cb'] ?? '',
			'title'       => __( 'From', 'business-cms' ),
			'bcms_email'  => __( 'Email', 'business-cms' ),
			'bcms_msg'    => __( 'Message', 'business-cms' ),
			'bcms_crm'    => __( 'CRM', 'business-cms' ),
			'date'        => __( 'Received', 'business-cms' ),
		);
	}

	public function enquiry_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'bcms_email':
				$email = (string) get_post_meta( $post_id, '_bcms_email', true );
				echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—';
				break;
			case 'bcms_msg':
				echo esc_html( wp_trim_words( (string) get_post_meta( $post_id, '_bcms_message', true ), 18 ) );
				break;
			case 'bcms_crm':
				$state = (string) get_post_meta( $post_id, '_bcms_crm_status', true );
				$map   = array(
					'sent'     => '<span style="color:#1a7f37">' . esc_html__( 'Sent', 'business-cms' ) . '</span>',
					'failed'   => '<span style="color:#b32d2e">' . esc_html__( 'Failed', 'business-cms' ) . '</span>',
					'disabled' => '<span style="color:#646970">' . esc_html__( 'Off', 'business-cms' ) . '</span>',
				);
				echo wp_kses_post( $map[ $state ] ?? '—' );
				break;
		}
	}

	/**
	 * Portfolio archives are a grid, so they need their own per-page count
	 * rather than inheriting the blog's.
	 */
	public function archive_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->is_post_type_archive( self::PROJECT ) || $query->is_tax( array( self::INDUSTRY, self::SERVICE ) ) ) {
			$query->set( 'posts_per_page', (int) BCMS_Settings::get( 'archive_per_page', 9 ) );
			$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
		}
	}

	public static function project_slug(): string {
		return (string) BCMS_Settings::get( 'project_slug', 'work' );
	}
}
