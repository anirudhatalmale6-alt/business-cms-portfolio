<?php
/**
 * Meridian theme setup.
 *
 * @package Meridian
 */

defined( 'ABSPATH' ) || exit;

const MERIDIAN_VERSION = '1.0.0';

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'custom-logo', array(
			'height'      => 80,
			'width'       => 260,
			'flex-height' => true,
			'flex-width'  => true,
		) );
		add_editor_style( 'style.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style( 'meridian', get_stylesheet_uri(), array(), MERIDIAN_VERSION );
	}
);

/**
 * Block themes have no header.php to put a skip link in, so it is injected at
 * the top of the body instead. Without it a keyboard user tabs through the
 * whole navigation on every single page.
 */
add_action(
	'wp_body_open',
	static function (): void {
		printf(
			'<a class="skip-link screen-reader-text" href="#main">%s</a>',
			esc_html__( 'Skip to content', 'meridian' )
		);
	}
);

/**
 * Register the block patterns as a category so they are findable in the
 * inserter rather than buried among core's.
 */
add_action(
	'init',
	static function (): void {
		register_block_pattern_category(
			'meridian',
			array( 'label' => __( 'Meridian', 'meridian' ) )
		);
	}
);

/**
 * The style variations shipped in /styles are how the client re-skins the site
 * without a developer; this keeps the Site Editor's "Styles" panel honest by
 * labelling the active one.
 *
 * @param string[] $classes
 * @return string[]
 */
add_filter(
	'body_class',
	static function ( array $classes ): array {
		if ( is_singular( 'bcms_project' ) ) {
			$classes[] = 'meridian-case-study';
		}
		return $classes;
	}
);

/**
 * Performance: core emits a stylesheet per block plus a large global styles
 * blob. Loading block styles only when the block is on the page is the single
 * biggest front-end win available without touching content.
 */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * Remove the emoji detection script. It is two extra requests on every page to
 * polyfill something every browser this site targets already does natively.
 */
add_action(
	'init',
	static function (): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );
	}
);

/**
 * Preconnect only to hosts the page will genuinely use. Speculative hints to
 * hosts that are never contacted cost a DNS lookup for nothing.
 *
 * @param string[] $hints
 * @param string   $relation
 * @return string[]
 */
add_filter(
	'wp_resource_hints',
	static function ( array $hints, string $relation ): array {
		if ( 'dns-prefetch' !== $relation ) {
			return $hints;
		}

		if ( class_exists( 'BCMS_Settings' ) && BCMS_Settings::get( 'ga4_id', '' ) ) {
			$hints[] = 'https://www.googletagmanager.com';
		}

		return $hints;
	},
	10,
	2
);

/**
 * The hero image on a case study is the Largest Contentful Paint element, so
 * it must not be lazy-loaded — the default heuristic cannot know that a block
 * template put it above the fold.
 *
 * @param string|bool $value
 * @return string|bool
 */
add_filter(
	'wp_get_attachment_image_attributes',
	static function ( array $attr, $attachment, $size ) {
		if ( is_singular( 'bcms_project' ) && 'bcms-hero' === $size ) {
			$attr['loading']       = 'eager';
			$attr['fetchpriority'] = 'high';
			unset( $attr['decoding'] );
		}
		return $attr;
	},
	10,
	3
);
