<?php
/**
 * SEO foundations.
 *
 * Deliberately small: titles, descriptions, canonicals, Open Graph, Twitter
 * cards and JSON-LD. It does not try to be Yoast — if the client later wants
 * Yoast or Rank Math, this class stands down automatically rather than
 * emitting a second, conflicting set of tags.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Seo {

	public function __construct() {
		add_action( 'wp_head', array( $this, 'head' ), 1 );
		add_filter( 'document_title_separator', array( $this, 'separator' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'sitemap_taxonomies' ) );
		add_filter( 'robots_txt', array( $this, 'robots' ), 10, 2 );
	}

	/**
	 * A dedicated SEO plugin owns these tags if one is installed; two sets of
	 * og: tags is worse than none.
	 */
	private function stand_down(): bool {
		return defined( 'WPSEO_VERSION' )
			|| defined( 'RANK_MATH_VERSION' )
			|| class_exists( 'All_in_One_SEO_Pack' )
			|| (bool) apply_filters( 'bcms_disable_seo', false );
	}

	public function separator( string $sep ): string {
		return '·';
	}

	public function head(): void {
		if ( $this->stand_down() || is_admin() ) {
			return;
		}

		$title = wp_get_document_title();
		$desc  = $this->description();
		$url   = $this->canonical();
		$image = $this->image();

		echo "\n<!-- Business CMS SEO -->\n";

		if ( '' !== $desc ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
		}

		if ( '' !== $url ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
		}

		printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( is_singular( BCMS_Post_Types::PROJECT ) ? 'article' : ( is_singular() ? 'article' : 'website' ) ) );
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( (string) BCMS_Settings::get( 'company_name', get_bloginfo( 'name' ) ) ) );
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );

		if ( '' !== $desc ) {
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		if ( '' !== $url ) {
			printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
		}
		if ( '' !== $image ) {
			printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		} else {
			echo '<meta name="twitter:card" content="summary">' . "\n";
		}

		$twitter = (string) BCMS_Settings::get( 'seo_twitter', '' );
		if ( '' !== $twitter ) {
			printf( '<meta name="twitter:site" content="%s">' . "\n", esc_attr( '@' . ltrim( $twitter, '@' ) ) );
		}

		$this->json_ld();
	}

	private function description(): string {
		if ( is_singular( BCMS_Post_Types::PROJECT ) ) {
			$summary = BCMS_Meta::summary( (int) get_the_ID() );
			if ( '' !== $summary ) {
				return wp_trim_words( $summary, 32, '' );
			}
		}

		if ( is_singular() ) {
			$post = get_post();
			if ( $post ) {
				$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( (string) $post->post_content );
				return wp_trim_words( (string) $excerpt, 32, '' );
			}
		}

		if ( is_tax( array( BCMS_Post_Types::INDUSTRY, BCMS_Post_Types::SERVICE ) ) ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				return $term->description
					? wp_trim_words( $term->description, 32, '' )
					: sprintf(
						/* translators: %s: industry name */
						__( 'Selected %s projects and case studies.', 'business-cms' ),
						$term->name
					);
			}
		}

		if ( is_post_type_archive( BCMS_Post_Types::PROJECT ) ) {
			return (string) BCMS_Settings::get( 'company_tagline', get_bloginfo( 'description' ) );
		}

		if ( is_front_page() ) {
			return (string) BCMS_Settings::get( 'company_tagline', get_bloginfo( 'description' ) );
		}

		return '';
	}

	private function canonical(): string {
		if ( is_singular() ) {
			return (string) get_permalink();
		}
		if ( is_post_type_archive() ) {
			return (string) get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$link = get_term_link( $term );
				return is_wp_error( $link ) ? '' : $link;
			}
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		return '';
	}

	private function image(): string {
		if ( is_singular() && has_post_thumbnail() ) {
			$src = wp_get_attachment_image_src( (int) get_post_thumbnail_id(), 'bcms-social' );
			if ( $src ) {
				return (string) $src[0];
			}
		}

		$fallback = (int) BCMS_Settings::get( 'seo_default_image', 0 );
		if ( $fallback ) {
			$src = wp_get_attachment_image_src( $fallback, 'bcms-social' );
			if ( $src ) {
				return (string) $src[0];
			}
		}

		return '';
	}

	private function json_ld(): void {
		$company = (string) BCMS_Settings::get( 'company_name', get_bloginfo( 'name' ) );

		$graph = array();

		$org = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => $company,
			'url'   => home_url( '/' ),
		);

		$logo_id = (int) BCMS_Settings::get( 'company_logo_id', 0 );
		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $src ) {
				$org['logo'] = $src[0];
			}
		}

		$same_as = array_filter( array( (string) BCMS_Settings::get( 'linkedin_url', '' ) ) );
		if ( $same_as ) {
			$org['sameAs'] = array_values( $same_as );
		}

		$phone = (string) BCMS_Settings::get( 'contact_phone', '' );
		$email = (string) BCMS_Settings::get( 'contact_email', '' );
		if ( $phone || $email ) {
			$org['contactPoint'] = array_filter(
				array(
					'@type'       => 'ContactPoint',
					'contactType' => 'sales',
					'telephone'   => $phone,
					'email'       => $email,
				)
			);
		}

		$graph[] = $org;

		if ( is_singular( BCMS_Post_Types::PROJECT ) ) {
			$post_id = (int) get_the_ID();
			$work    = array(
				'@type'       => 'CreativeWork',
				'@id'         => get_permalink( $post_id ) . '#project',
				'name'        => get_the_title( $post_id ),
				'headline'    => get_the_title( $post_id ),
				'url'         => get_permalink( $post_id ),
				'dateCreated' => get_the_date( 'c', $post_id ),
				'creator'     => array( '@id' => home_url( '/#organization' ) ),
			);

			$summary = BCMS_Meta::summary( $post_id );
			if ( '' !== $summary ) {
				$work['description'] = $summary;
			}

			$client = (string) get_post_meta( $post_id, '_bcms_client', true );
			if ( '' !== $client ) {
				$work['sourceOrganization'] = array(
					'@type' => 'Organization',
					'name'  => $client,
				);
			}

			if ( has_post_thumbnail( $post_id ) ) {
				$src = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post_id ), 'bcms-social' );
				if ( $src ) {
					$work['image'] = $src[0];
				}
			}

			$industries = get_the_terms( $post_id, BCMS_Post_Types::INDUSTRY );
			if ( is_array( $industries ) && $industries ) {
				$work['about'] = wp_list_pluck( $industries, 'name' );
			}

			$graph[] = $work;
		}

		$graph[] = $this->breadcrumbs();

		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array_values( array_filter( $graph ) ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode output.
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function breadcrumbs(): ?array {
		if ( is_front_page() ) {
			return null;
		}

		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'business-cms' ),
				'item'     => home_url( '/' ),
			),
		);

		if ( is_singular( BCMS_Post_Types::PROJECT ) || is_post_type_archive( BCMS_Post_Types::PROJECT ) || is_tax( BCMS_Post_Types::INDUSTRY ) ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => __( 'Work', 'business-cms' ),
				'item'     => get_post_type_archive_link( BCMS_Post_Types::PROJECT ),
			);
		}

		if ( is_singular() ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'name'     => get_the_title(),
				'item'     => get_permalink(),
			);
		} elseif ( is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$link    = get_term_link( $term );
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => count( $items ) + 1,
					'name'     => $term->name,
					'item'     => is_wp_error( $link ) ? home_url( '/' ) : $link,
				);
			}
		}

		if ( count( $items ) < 2 ) {
			return null;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/**
	 * @param string[] $post_types
	 * @return string[]
	 */
	public function sitemap_post_types( array $post_types ): array {
		$project = get_post_type_object( BCMS_Post_Types::PROJECT );
		if ( $project ) {
			$post_types[ BCMS_Post_Types::PROJECT ] = $project;
		}
		unset( $post_types[ BCMS_Post_Types::ENQUIRY ] );
		return $post_types;
	}

	/**
	 * @param array<string,WP_Taxonomy> $taxonomies
	 * @return array<string,WP_Taxonomy>
	 */
	public function sitemap_taxonomies( array $taxonomies ): array {
		foreach ( array( BCMS_Post_Types::INDUSTRY, BCMS_Post_Types::SERVICE ) as $tax ) {
			$object = get_taxonomy( $tax );
			if ( $object ) {
				$taxonomies[ $tax ] = $object;
			}
		}
		return $taxonomies;
	}

	public function robots( string $output, $public ): string {
		if ( ! $public ) {
			return $output;
		}
		$output .= "\nSitemap: " . esc_url( home_url( '/wp-sitemap.xml' ) ) . "\n";
		$output .= "Disallow: /wp-admin/\n";
		$output .= "Allow: /wp-admin/admin-ajax.php\n";
		return $output;
	}
}
