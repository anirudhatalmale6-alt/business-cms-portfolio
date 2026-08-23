<?php
/**
 * Blocks.
 *
 * All of these render on the server. Markup produced by a save() function is
 * frozen into every post that used it, so a later design tweak would mean
 * hundreds of "block contains unexpected content" warnings. Server rendering
 * keeps the design changeable for the life of the site.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Blocks {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'block_categories_all', array( $this, 'category' ), 10, 1 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
	}

	/**
	 * @param array<int,array<string,mixed>> $categories
	 * @return array<int,array<string,mixed>>
	 */
	public function category( array $categories ): array {
		array_unshift(
			$categories,
			array(
				'slug'  => 'bcms',
				'title' => __( 'Portfolio', 'business-cms' ),
				'icon'  => 'portfolio',
			)
		);
		return $categories;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function definitions(): array {
		return array(
			'bcms/project-grid'   => array(
				'title'      => __( 'Project grid', 'business-cms' ),
				'attributes' => array(
					'heading'      => array( 'type' => 'string', 'default' => '' ),
					'count'        => array( 'type' => 'number', 'default' => 6 ),
					'columns'      => array( 'type' => 'number', 'default' => 3 ),
					'industry'     => array( 'type' => 'string', 'default' => '' ),
					'showFilter'   => array( 'type' => 'boolean', 'default' => true ),
					'featuredOnly' => array( 'type' => 'boolean', 'default' => false ),
					'showSummary'  => array( 'type' => 'boolean', 'default' => true ),
					'ctaText'      => array( 'type' => 'string', 'default' => '' ),
				),
				'render'     => 'render_project_grid',
			),
			'bcms/project-stats'  => array(
				'title'      => __( 'Headline results', 'business-cms' ),
				'attributes' => array(
					'align' => array( 'type' => 'string', 'default' => '' ),
				),
				'render'     => 'render_project_stats',
			),
			'bcms/project-facts'  => array(
				'title'      => __( 'Project facts', 'business-cms' ),
				'attributes' => array(),
				'render'     => 'render_project_facts',
			),
			'bcms/contact-form'   => array(
				'title'      => __( 'Contact form', 'business-cms' ),
				'attributes' => array(
					'heading'        => array( 'type' => 'string', 'default' => '' ),
					'buttonLabel'    => array( 'type' => 'string', 'default' => '' ),
					'showCompany'    => array( 'type' => 'boolean', 'default' => true ),
					'showPhone'      => array( 'type' => 'boolean', 'default' => false ),
					'successMessage' => array( 'type' => 'string', 'default' => '' ),
				),
				'render'     => 'render_contact_form',
			),
		);
	}

	public function register(): void {
		foreach ( self::definitions() as $name => $def ) {
			register_block_type(
				$name,
				array(
					'api_version'     => 3,
					'title'           => $def['title'],
					'category'        => 'bcms',
					'attributes'      => $def['attributes'],
					'render_callback' => array( $this, $def['render'] ),
					'editor_script'   => 'bcms-blocks',
					'editor_style'    => 'bcms-editor',
					'supports'        => array(
						'html'   => false,
						'anchor' => true,
					),
				)
			);
		}
	}

	public function editor_assets(): void {
		wp_enqueue_script(
			'bcms-blocks',
			BCMS_URL . 'assets/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-core-data' ),
			BCMS_VERSION,
			true
		);

		$industries = array( array( 'label' => __( 'All industries', 'business-cms' ), 'value' => '' ) );
		foreach ( get_terms( array( 'taxonomy' => BCMS_Post_Types::INDUSTRY, 'hide_empty' => false ) ) as $term ) {
			if ( $term instanceof WP_Term ) {
				$industries[] = array( 'label' => $term->name, 'value' => $term->slug );
			}
		}

		wp_localize_script( 'bcms-blocks', 'BCMS_BLOCKS', array( 'industries' => $industries ) );
		wp_enqueue_style( 'bcms-editor', BCMS_URL . 'assets/editor.css', array(), BCMS_VERSION );
	}

	public function register_routes(): void {
		register_rest_route(
			'bcms/v1',
			'/grid',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( $this, 'rest_grid' ),
				'args'                => array(
					'industry' => array(
						'type'    => 'string',
						'default' => '',
						// Not sanitize_title directly: REST passes the request
						// as its second argument, which sanitize_title returns
						// verbatim as the fallback when the slug is empty — so
						// clearing the filter would hand a WP_REST_Request to
						// the query instead of an empty string.
						'sanitize_callback' => static fn( $value ): string => sanitize_title( (string) $value ),
					),
					'count'    => array(
						'type'    => 'integer',
						'default' => 6,
					),
					'columns'  => array(
						'type'    => 'integer',
						'default' => 3,
					),
					'summary'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
	}

	public function rest_grid( WP_REST_Request $request ): WP_REST_Response {
		$html = $this->grid_items(
			array(
				'industry'    => (string) $request->get_param( 'industry' ),
				'count'       => (int) $request->get_param( 'count' ),
				'showSummary' => (bool) $request->get_param( 'summary' ),
			)
		);

		return new WP_REST_Response(
			array(
				'html'  => $html,
				'empty' => '' === trim( $html ),
			),
			200
		);
	}

	/* ---------------------------------------------------------------------
	 * Renderers
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function render_project_grid( array $attributes = array() ): string {
		$attributes = wp_parse_args( $attributes, wp_list_pluck( self::definitions()['bcms/project-grid']['attributes'], 'default' ) );

		// Only pages that actually render a grid pay for the script.
		wp_enqueue_script( 'bcms-front' );

		// On an industry archive the grid should show that industry without the
		// editor having to hard-code it into a second copy of the template.
		if ( '' === (string) $attributes['industry'] ) {
			$attributes['industry'] = self::current_industry();
		}

		$columns  = max( 1, min( 4, (int) $attributes['columns'] ) );
		$items    = $this->grid_items( $attributes );
		$wrapper  = get_block_wrapper_attributes(
			array(
				'class'                 => 'bcms-grid-block bcms-grid-cols-' . $columns,
				'data-bcms-grid'        => '1',
				'data-bcms-count'       => (string) (int) $attributes['count'],
				'data-bcms-columns'     => (string) $columns,
				'data-bcms-summary'     => $attributes['showSummary'] ? '1' : '0',
			)
		);

		$out = '<div ' . $wrapper . '>';

		if ( ! empty( $attributes['heading'] ) ) {
			$out .= '<h2 class="bcms-grid-heading">' . esc_html( $attributes['heading'] ) . '</h2>';
		}

		if ( ! empty( $attributes['showFilter'] ) ) {
			$out .= $this->filter_bar( (string) $attributes['industry'] );
		}

		$out .= '<div class="bcms-grid" data-bcms-grid-items aria-live="polite">' . $items . '</div>';

		if ( ! empty( $attributes['ctaText'] ) ) {
			$out .= '<p class="bcms-grid-cta"><a class="bcms-btn bcms-btn-ghost" href="' . esc_url( get_post_type_archive_link( BCMS_Post_Types::PROJECT ) ) . '">' . esc_html( $attributes['ctaText'] ) . '</a></p>';
		}

		$out .= '</div>';

		return $out;
	}

	public static function current_industry(): string {
		if ( ! is_tax( BCMS_Post_Types::INDUSTRY ) ) {
			return '';
		}
		$term = get_queried_object();
		return $term instanceof WP_Term ? $term->slug : '';
	}

	private function filter_bar( string $active ): string {
		$terms = get_terms(
			array(
				'taxonomy'   => BCMS_Post_Types::INDUSTRY,
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) || count( $terms ) < 2 ) {
			return '';
		}

		$archive = (string) get_post_type_archive_link( BCMS_Post_Types::PROJECT );

		$out  = '<div class="bcms-filter" role="group" aria-label="' . esc_attr__( 'Filter projects by industry', 'business-cms' ) . '">';
		$out .= sprintf(
			'<a class="bcms-filter-chip%s" href="%s" data-bcms-filter="">%s</a>',
			'' === $active ? ' is-active' : '',
			esc_url( $archive ),
			esc_html__( 'All work', 'business-cms' )
		);

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$link = get_term_link( $term );
			$out .= sprintf(
				'<a class="bcms-filter-chip%s" href="%s" data-bcms-filter="%s">%s <span class="bcms-filter-count">%d</span></a>',
				$active === $term->slug ? ' is-active' : '',
				esc_url( is_wp_error( $link ) ? $archive : $link ),
				esc_attr( $term->slug ),
				esc_html( $term->name ),
				(int) $term->count
			);
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	private function grid_items( array $attributes ): string {
		$args = array(
			'post_type'           => BCMS_Post_Types::PROJECT,
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 24, (int) ( $attributes['count'] ?? 6 ) ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		);

		if ( ! empty( $attributes['industry'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => BCMS_Post_Types::INDUSTRY,
					'field'    => 'slug',
					'terms'    => sanitize_title( (string) $attributes['industry'] ),
				),
			);
		}

		if ( ! empty( $attributes['featuredOnly'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_bcms_featured',
					'value'   => '1',
					'compare' => '=',
				),
			);
		}

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<p class="bcms-grid-empty">' . esc_html__( 'No projects in this category yet.', 'business-cms' ) . '</p>';
		}

		$out = '';
		foreach ( $query->posts as $post ) {
			$out .= self::card( $post, ! empty( $attributes['showSummary'] ) );
		}

		return $out;
	}

	public static function card( WP_Post $post, bool $with_summary = true ): string {
		$industries = get_the_terms( $post, BCMS_Post_Types::INDUSTRY );
		$industry   = ( is_array( $industries ) && $industries ) ? $industries[0]->name : '';
		$summary    = BCMS_Meta::summary( $post->ID );
		$client     = (string) get_post_meta( $post->ID, '_bcms_client', true );

		$thumb = get_the_post_thumbnail(
			$post,
			'bcms-card',
			array(
				'class'   => 'bcms-card-img',
				'loading' => 'lazy',
				'alt'     => '',
			)
		);

		$out  = '<article class="bcms-card">';
		$out .= '<a class="bcms-card-link" href="' . esc_url( (string) get_permalink( $post ) ) . '">';
		$out .= '<span class="bcms-card-media">' . ( $thumb ?: '<span class="bcms-card-placeholder" aria-hidden="true"></span>' ) . '</span>';
		$out .= '<span class="bcms-card-body">';

		if ( $industry ) {
			$out .= '<span class="bcms-card-eyebrow">' . esc_html( $industry ) . '</span>';
		}

		$out .= '<span class="bcms-card-title">' . esc_html( get_the_title( $post ) ) . '</span>';

		if ( $client ) {
			$out .= '<span class="bcms-card-client">' . esc_html( $client ) . '</span>';
		}

		if ( $with_summary && $summary ) {
			$out .= '<span class="bcms-card-summary">' . esc_html( wp_trim_words( $summary, 22 ) ) . '</span>';
		}

		$out .= '<span class="bcms-card-more">' . esc_html__( 'Read the case study', 'business-cms' ) . '</span>';
		$out .= '</span></a></article>';

		return $out;
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function render_project_stats( array $attributes = array() ): string {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$stats = BCMS_Meta::stats( (int) $post_id );
		if ( ! $stats ) {
			return '';
		}

		$out = '<div ' . get_block_wrapper_attributes( array( 'class' => 'bcms-stats bcms-stats-' . count( $stats ) ) ) . '>';
		foreach ( $stats as $stat ) {
			$out .= '<div class="bcms-stat">';
			$out .= '<span class="bcms-stat-value">' . esc_html( $stat['value'] ) . '</span>';
			if ( '' !== $stat['label'] ) {
				$out .= '<span class="bcms-stat-label">' . esc_html( $stat['label'] ) . '</span>';
			}
			$out .= '</div>';
		}
		$out .= '</div>';

		return $out;
	}

	public function render_project_facts(): string {
		$post_id = (int) get_the_ID();
		if ( ! $post_id ) {
			return '';
		}

		$rows = array(
			__( 'Client', 'business-cms' )   => (string) get_post_meta( $post_id, '_bcms_client', true ),
			__( 'Industry', 'business-cms' ) => $this->term_list( $post_id, BCMS_Post_Types::INDUSTRY ),
			__( 'Services', 'business-cms' ) => $this->term_list( $post_id, BCMS_Post_Types::SERVICE ),
			__( 'Location', 'business-cms' ) => (string) get_post_meta( $post_id, '_bcms_location', true ),
			__( 'Year', 'business-cms' )     => (string) get_post_meta( $post_id, '_bcms_year', true ),
			__( 'Duration', 'business-cms' ) => (string) get_post_meta( $post_id, '_bcms_duration', true ),
		);

		$rows = array_filter( $rows, static fn( $v ) => '' !== trim( (string) $v ) );

		if ( ! $rows ) {
			return '';
		}

		$out = '<dl ' . get_block_wrapper_attributes( array( 'class' => 'bcms-facts' ) ) . '>';
		foreach ( $rows as $label => $value ) {
			$out .= '<div class="bcms-fact"><dt>' . esc_html( (string) $label ) . '</dt><dd>' . wp_kses_post( $value ) . '</dd></div>';
		}

		$site = (string) get_post_meta( $post_id, '_bcms_site_url', true );
		if ( $site ) {
			$out .= '<div class="bcms-fact"><dt>' . esc_html__( 'Live site', 'business-cms' ) . '</dt><dd><a href="' . esc_url( $site ) . '" rel="noopener nofollow" target="_blank">' . esc_html( wp_parse_url( $site, PHP_URL_HOST ) ?: $site ) . '</a></dd></div>';
		}

		$out .= '</dl>';

		return $out;
	}

	private function term_list( int $post_id, string $taxonomy ): string {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) || ! $terms ) {
			return '';
		}
		$parts = array();
		foreach ( $terms as $term ) {
			$link    = get_term_link( $term );
			$parts[] = is_wp_error( $link )
				? esc_html( $term->name )
				: '<a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>';
		}
		return implode( ', ', $parts );
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function render_contact_form( array $attributes = array() ): string {
		$forms = BCMS_Plugin::instance()->module( 'forms' );
		if ( ! $forms instanceof BCMS_Forms ) {
			return '';
		}
		return $forms->render( $attributes );
	}
}
