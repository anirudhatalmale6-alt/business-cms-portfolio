<?php
/**
 * Project facts (client, year, results…) as registered post meta.
 *
 * Registered with show_in_rest so the block editor can read and write them,
 * which is what lets the sidebar panel be a few lines of JS instead of a
 * classic meta box bolted onto a block-editor screen.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

class BCMS_Meta {

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function schema(): array {
		return array(
			'_bcms_client'      => array(
				'label' => __( 'Client', 'business-cms' ),
				'type'  => 'string',
				'help'  => __( 'Shown on the case-study header.', 'business-cms' ),
			),
			'_bcms_year'        => array(
				'label' => __( 'Year', 'business-cms' ),
				'type'  => 'string',
			),
			'_bcms_location'    => array(
				'label' => __( 'Location', 'business-cms' ),
				'type'  => 'string',
			),
			'_bcms_duration'    => array(
				'label' => __( 'Engagement length', 'business-cms' ),
				'type'  => 'string',
			),
			'_bcms_summary'     => array(
				'label' => __( 'One-line summary', 'business-cms' ),
				'type'  => 'string',
				'multiline' => true,
				'help'  => __( 'Used on portfolio cards and as the SEO description fallback.', 'business-cms' ),
			),
			'_bcms_video_url'   => array(
				'label' => __( 'Hero video URL', 'business-cms' ),
				'type'  => 'string',
				'help'  => __( 'MP4 or YouTube/Vimeo link. Replaces the hero image when set.', 'business-cms' ),
			),
			'_bcms_site_url'    => array(
				'label' => __( 'Live site URL', 'business-cms' ),
				'type'  => 'string',
			),
			'_bcms_featured'    => array(
				'label' => __( 'Feature on the homepage', 'business-cms' ),
				'type'  => 'boolean',
			),
			'_bcms_stats'       => array(
				'label'  => __( 'Headline results', 'business-cms' ),
				'type'   => 'string',
				'hidden' => true,
			),
		);
	}

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
	}

	public function register(): void {
		foreach ( self::schema() as $key => $field ) {
			register_post_meta(
				BCMS_Post_Types::PROJECT,
				$key,
				array(
					'type'              => $field['type'],
					'single'            => true,
					'default'           => 'boolean' === $field['type'] ? false : '',
					'show_in_rest'      => true,
					'sanitize_callback' => 'boolean' === $field['type']
						? 'rest_sanitize_boolean'
						: ( '_bcms_site_url' === $key || '_bcms_video_url' === $key ? 'esc_url_raw' : 'sanitize_textarea_field' ),
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	public function editor_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || BCMS_Post_Types::PROJECT !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'bcms-editor',
			BCMS_URL . 'assets/editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-core-data' ),
			BCMS_VERSION,
			true
		);

		$fields = array();
		foreach ( self::schema() as $key => $field ) {
			if ( ! empty( $field['hidden'] ) ) {
				continue;
			}
			$fields[] = array(
				'key'       => $key,
				'label'     => $field['label'],
				'type'      => $field['type'],
				'help'      => $field['help'] ?? '',
				'multiline' => ! empty( $field['multiline'] ),
			);
		}

		wp_localize_script(
			'bcms-editor',
			'BCMS_EDITOR',
			array(
				'postType' => BCMS_Post_Types::PROJECT,
				'fields'   => $fields,
				'panel'    => __( 'Project details', 'business-cms' ),
			)
		);

		wp_enqueue_style( 'bcms-editor', BCMS_URL . 'assets/editor.css', array(), BCMS_VERSION );
	}

	/**
	 * Headline results are stored as one JSON string rather than three
	 * numbered meta keys, so adding a fourth stat later is a data change
	 * and not a migration.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	public static function stats( int $post_id ): array {
		$raw = (string) get_post_meta( $post_id, '_bcms_stats', true );
		if ( '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$value = trim( (string) ( $row['value'] ?? '' ) );
			$label = trim( (string) ( $row['label'] ?? '' ) );
			if ( '' === $value && '' === $label ) {
				continue;
			}
			$out[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $out;
	}

	public static function summary( int $post_id ): string {
		$summary = trim( (string) get_post_meta( $post_id, '_bcms_summary', true ) );
		if ( '' !== $summary ) {
			return $summary;
		}
		$excerpt = get_the_excerpt( $post_id );
		return $excerpt ? wp_strip_all_tags( $excerpt ) : '';
	}
}
