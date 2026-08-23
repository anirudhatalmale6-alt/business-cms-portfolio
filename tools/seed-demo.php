<?php
/**
 * Demo content seeder.
 *
 * Run with:  wp eval-file tools/seed-demo.php
 *
 * Everything here is placeholder content for the demo build. It is safe to run
 * more than once — records are matched by slug and updated rather than
 * duplicated — and `wp eval-file tools/seed-demo.php --clean` removes it all
 * again before the client's real content goes in.
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

$bcms_clean = in_array( '--clean', (array) ( $args ?? array() ), true );

/* -------------------------------------------------------------------------
 * Taxonomy terms
 * ---------------------------------------------------------------------- */

$industries = array(
	'financial-services' => array( 'Financial Services', 'Banks, asset managers and the regulators who audit them.' ),
	'healthcare'         => array( 'Healthcare', 'Providers and payors, where downtime is measured in patients.' ),
	'logistics'          => array( 'Logistics', 'Freight, warehousing and the software that moves both.' ),
	'public-sector'      => array( 'Public Sector', 'Central and local government programmes under public scrutiny.' ),
	'manufacturing'      => array( 'Manufacturing', 'Plants, supply chains and the systems that keep them running.' ),
);

$services = array(
	'strategy'         => 'Strategy',
	'operating-model'  => 'Operating Model',
	'data-platform'    => 'Data Platform',
	'digital-delivery' => 'Digital Delivery',
	'change'           => 'Change Management',
	'due-diligence'    => 'Due Diligence',
);

foreach ( $industries as $slug => $meta ) {
	if ( ! term_exists( $slug, BCMS_Post_Types::INDUSTRY ) ) {
		wp_insert_term( $meta[0], BCMS_Post_Types::INDUSTRY, array( 'slug' => $slug, 'description' => $meta[1] ) );
	}
}

foreach ( $services as $slug => $name ) {
	if ( ! term_exists( $slug, BCMS_Post_Types::SERVICE ) ) {
		wp_insert_term( $name, BCMS_Post_Types::SERVICE, array( 'slug' => $slug ) );
	}
}

/* -------------------------------------------------------------------------
 * Media
 * ---------------------------------------------------------------------- */

/**
 * Side-loads an image from the repo and returns its attachment ID, reusing the
 * existing attachment if the seeder has already run.
 */
function bcms_seed_image( string $file, string $title ): int {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'name'           => sanitize_title( $title ),
			'fields'         => 'ids',
		)
	);

	if ( $existing ) {
		return (int) $existing[0];
	}

	$source = dirname( __DIR__ ) . '/assets/placeholders/' . $file;
	if ( ! file_exists( $source ) ) {
		WP_CLI::warning( "Missing placeholder: {$source}" );
		return 0;
	}

	$uploads = wp_upload_dir();
	$target  = trailingslashit( $uploads['path'] ) . $file;
	copy( $source, $target );

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $title,
			'post_name'      => sanitize_title( $title ),
			'post_status'    => 'inherit',
			'post_excerpt'   => $title,
		),
		$target
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $target ) );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );

	return (int) $attachment_id;
}

/* -------------------------------------------------------------------------
 * Projects
 * ---------------------------------------------------------------------- */

$projects = array(
	array(
		'slug'     => 'ardent-capital-reporting',
		'title'    => 'Cutting a month-end close from nine days to two',
		'client'   => 'Ardent Capital',
		'industry' => 'financial-services',
		'services' => array( 'data-platform', 'operating-model' ),
		'year'     => '2025',
		'location' => 'London',
		'duration' => '14 weeks',
		'image'    => 'ardent.jpg',
		'featured' => true,
		'summary'  => 'A £4bn asset manager was closing its books nine days after month end. We rebuilt the reporting pipeline and the team around it.',
		'stats'    => array(
			array( 'value' => '9 → 2', 'label' => 'Days to close' ),
			array( 'value' => '£1.1m', 'label' => 'Annual run cost saved' ),
			array( 'value' => '0', 'label' => 'Restatements since' ),
		),
		'body'     => array(
			array( 'p', 'Ardent had grown by acquisition and inherited four general ledgers, three reporting tools and a month-end process that lived, in practice, inside one spreadsheet and two people\'s heads. The finance director could not sign anything off until day nine, which meant the board saw the previous month\'s numbers halfway through the next one.' ),
			array( 'h2', 'The brief we were given' ),
			array( 'p', 'The brief said "automate the close". The problem underneath it was that nobody agreed what the numbers meant. Two of the four ledgers used different definitions of committed capital, and the reconciliation between them was being done by hand every month by someone who was about to retire.' ),
			array( 'h2', 'What we changed' ),
			array( 'p', 'We started with the definitions rather than the tooling. Six weeks of work with the finance and investment teams produced a single agreed chart of accounts and a written data dictionary — the first one the firm had ever had. Only then did we build: an ingestion layer that pulls all four ledgers nightly, a reconciliation engine that flags breaks the moment they appear rather than at month end, and a reporting layer the finance team maintains themselves.' ),
			array( 'p', 'The reconciliation engine is the piece that actually moved the number. Breaks that used to surface on day six now surface on the day they happen, when the person who caused them still remembers why.' ),
			array( 'quote', 'The first close on the new process took two days and I spent most of the second one waiting for something to go wrong. It did not.', 'Finance Director, Ardent Capital' ),
			array( 'h2', 'Where it stands now' ),
			array( 'p', 'Ardent closed in two days for eleven consecutive months. The retiring reconciliations analyst retired. No part of the process depends on us, which was the point.' ),
		),
	),
	array(
		'slug'     => 'northbank-patient-flow',
		'title'    => 'Finding 40 beds without building a single one',
		'client'   => 'Northbank Health',
		'industry' => 'healthcare',
		'services' => array( 'operating-model', 'change' ),
		'year'     => '2025',
		'location' => 'Manchester',
		'duration' => '20 weeks',
		'image'    => 'northbank.jpg',
		'featured' => true,
		'summary'  => 'A hospital trust was holding patients in corridors while beds sat empty two floors up. The constraint was discharge, not capacity.',
		'stats'    => array(
			array( 'value' => '40', 'label' => 'Beds released' ),
			array( 'value' => '3.1 days', 'label' => 'Average stay reduced' ),
			array( 'value' => '18%', 'label' => 'Fewer corridor waits' ),
		),
		'body'     => array(
			array( 'p', 'Northbank was running at 98% occupancy and planning a capital bid for a new ward. Before signing off the business case the board asked for an independent look at whether the trust actually needed more beds.' ),
			array( 'h2', 'What the data said' ),
			array( 'p', 'It did not. Twelve weeks of discharge data showed a median of 2.4 patients per ward per day who were medically fit to leave but had not been discharged — waiting on a pharmacy round, a transport booking, or a social care assessment that nobody owned end to end.' ),
			array( 'h2', 'What we changed' ),
			array( 'p', 'We put a single named discharge coordinator on each ward with the authority to chase across departments, moved the pharmacy discharge round from afternoon to 8am, and built a simple board that showed every ward how many of its patients were waiting on what. No new system: the board is a screen in the ward office reading from data the trust already collected and never looked at.' ),
			array( 'p', 'The politics were harder than the process. Pharmacy did not want the earlier round; we ran it as a two-week trial on one ward and let the numbers make the argument.' ),
			array( 'quote', 'We came in expecting to be told to spend twenty million. We were told to move a pharmacy round.', 'Chief Operating Officer, Northbank Health' ),
			array( 'h2', 'Where it stands now' ),
			array( 'p', 'The capital bid was withdrawn. Occupancy sits at 89% and the trust has kept the discharge board running for eighteen months without our involvement.' ),
		),
	),
	array(
		'slug'     => 'verity-network-redesign',
		'title'    => 'Rebuilding a national network around where the freight actually goes',
		'client'   => 'Verity Logistics',
		'industry' => 'logistics',
		'services' => array( 'strategy', 'data-platform' ),
		'year'     => '2024',
		'location' => 'Birmingham',
		'duration' => '22 weeks',
		'image'    => 'verity.jpg',
		'featured' => true,
		'summary'  => 'Eleven depots laid out for a customer base that had moved south a decade earlier. We modelled the network from scratch and closed three.',
		'stats'    => array(
			array( 'value' => '11 → 8', 'label' => 'Depots' ),
			array( 'value' => '14%', 'label' => 'Cost per drop' ),
			array( 'value' => '99.1%', 'label' => 'On-time maintained' ),
		),
		'body'     => array(
			array( 'p', 'Verity\'s depot network had been assembled through fifteen years of acquisitions and never once designed. Three depots sat within forty miles of each other in the north west; the fastest-growing customer region had none within a hundred.' ),
			array( 'h2', 'The brief we were given' ),
			array( 'p', 'Reduce cost per drop by ten per cent without touching service levels. The board expected the answer to be route optimisation software.' ),
			array( 'h2', 'What we changed' ),
			array( 'p', 'We built a network model from three years of consignment data — every drop, its origin depot, its true cost to serve — and ran it against forty candidate footprints. The model said the network could serve the same customers from eight sites with better average drive times, and identified exactly which three to close and which two to expand.' ),
			array( 'p', 'The harder half of the work was the closure sequencing: which depot goes first, where its volume moves, and how to keep the drivers. We planned it over eleven months with redeployment offers issued before the announcements. Voluntary redundancy came in under forecast.' ),
			array( 'quote', 'The model was the easy sell. The eleven-month plan for how to do it without losing our drivers is what got it approved.', 'Operations Director, Verity Logistics' ),
			array( 'h2', 'Where it stands now' ),
			array( 'p', 'All three closures completed on schedule. Cost per drop is down fourteen per cent against a ten per cent target, and on-time delivery never dropped below 99% through the transition.' ),
		),
	),
	array(
		'slug'     => 'halliwell-crowe-diligence',
		'title'    => 'Nine days to tell a buyer what they were actually buying',
		'client'   => 'Halliwell & Crowe',
		'industry' => 'financial-services',
		'services' => array( 'due-diligence' ),
		'year'     => '2024',
		'location' => 'London',
		'duration' => '9 days',
		'image'    => 'halliwell.jpg',
		'featured' => false,
		'summary'  => 'Technical and operational diligence on a £60m acquisition, delivered inside an exclusivity window that had eleven days left on it.',
		'stats'    => array(
			array( 'value' => '9 days', 'label' => 'End to end' ),
			array( 'value' => '£8.4m', 'label' => 'Price adjustment' ),
		),
		'body'     => array(
			array( 'p', 'Halliwell & Crowe were eleven days from the end of exclusivity on a £60m acquisition and had just realised nobody had looked at the target\'s technology beyond a two-page summary from the vendor.' ),
			array( 'h2', 'What we did' ),
			array( 'p', 'Four people, nine days, full access to the target\'s codebase, infrastructure and engineering team. We were not trying to produce a perfect assessment; we were trying to find the things that would change the price or kill the deal, and to be honest about our confidence in each.' ),
			array( 'p', 'Two findings mattered. The target\'s core platform ran on a database version that left extended support in fourteen months, with an upgrade path that touched every integration. And a third of engineering headcount sat with one outsourcing partner on a contract that terminated on change of control.' ),
			array( 'h2', 'The outcome' ),
			array( 'p', 'Both were quantified into the model rather than presented as red flags. The buyer proceeded with an £8.4m adjustment and a retention plan agreed before completion.' ),
		),
	),
	array(
		'slug'     => 'meridian-rail-permits',
		'title'    => 'A permit system that stopped losing the permits',
		'client'   => 'Meridian Rail',
		'industry' => 'public-sector',
		'services' => array( 'digital-delivery', 'change' ),
		'year'     => '2024',
		'location' => 'Leeds',
		'duration' => '16 weeks',
		'image'    => 'meridian.jpg',
		'featured' => false,
		'summary'  => 'Track access permits were being approved by email and lost in inboxes. We replaced the process, not just the mailbox.',
		'stats'    => array(
			array( 'value' => '6 hrs → 40 min', 'label' => 'Approval time' ),
			array( 'value' => '100%', 'label' => 'Permits auditable' ),
		),
		'body'     => array(
			array( 'p', 'Every piece of engineering work on Meridian\'s track needed a permit, and every permit was an email thread. When an auditor asked to see the approval chain for a specific job, finding it took a day and a half.' ),
			array( 'h2', 'What we changed' ),
			array( 'p', 'We mapped the real approval path first, which turned out to have four steps rather than the seven in the written procedure — three had quietly stopped happening years earlier and nobody had noticed or missed them. We built for the four that mattered.' ),
			array( 'p', 'The system is deliberately small: a form, a queue, a mobile view for the people who are actually trackside, and an immutable log. It integrates with the existing rostering system so approvers see who is on shift.' ),
			array( 'quote', 'The best thing about it is how little of it there is. We have had systems before that did more and got used less.', 'Head of Engineering Delivery, Meridian Rail' ),
			array( 'h2', 'Where it stands now' ),
			array( 'p', 'Median approval time is forty minutes against a six-hour baseline, and the last audit request was answered in under a minute from the log.' ),
		),
	),
	array(
		'slug'     => 'caldwell-plant-downtime',
		'title'    => 'Halving unplanned downtime on a forty-year-old line',
		'client'   => 'Caldwell Industries',
		'industry' => 'manufacturing',
		'services' => array( 'data-platform', 'operating-model' ),
		'year'     => '2023',
		'location' => 'Sheffield',
		'duration' => '18 weeks',
		'image'    => 'caldwell.jpg',
		'featured' => false,
		'summary'  => 'The maintenance team knew which machines failed. Nobody had ever connected that to which shifts, which materials, or which operators.',
		'stats'    => array(
			array( 'value' => '52%', 'label' => 'Less unplanned downtime' ),
			array( 'value' => '£2.3m', 'label' => 'Recovered output' ),
			array( 'value' => '11 mos', 'label' => 'Payback' ),
		),
		'body'     => array(
			array( 'p', 'Caldwell ran three lines, one of them commissioned in 1984 and still producing a third of revenue. Unplanned downtime on that line averaged eleven hours a week and was accepted as the cost of running old equipment.' ),
			array( 'h2', 'What the data said' ),
			array( 'p', 'The plant had sensor data going back six years and had never analysed it against anything else. Joined against shift rosters, material batch records and ambient temperature, two patterns came out immediately: failures clustered on the first shift after a material supplier change, and a specific bearing assembly failed at four times the rate on night shifts.' ),
			array( 'p', 'The night-shift figure was not a people problem. The line ran marginally faster at night because the ambient temperature was lower and an uncalibrated feed rate control was compensating in the wrong direction.' ),
			array( 'h2', 'What we changed' ),
			array( 'p', 'A recalibration, a material acceptance check at goods-in, and a maintenance schedule driven by run hours rather than the calendar. Plus a one-page weekly report the plant manager actually reads, because he helped design it.' ),
			array( 'h2', 'Where it stands now' ),
			array( 'p', 'Unplanned downtime is down fifty-two per cent. The 1984 line is now the most reliable of the three.' ),
		),
	),
);

/**
 * Turns the compact body definition above into block markup.
 *
 * @param array<int,array<int,string>> $body
 */
function bcms_seed_blocks( array $body ): string {
	$out = array();

	$out[] = '<!-- wp:bcms/project-stats /-->';

	foreach ( $body as $node ) {
		$type = $node[0];
		$text = $node[1];

		if ( 'p' === $type ) {
			$out[] = "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->";
		} elseif ( 'h2' === $type ) {
			$out[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $text ) . "</h2>\n<!-- /wp:heading -->";
		} elseif ( 'quote' === $type ) {
			$cite  = $node[2] ?? '';
			$out[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>"
				. esc_html( $text )
				. "</p>\n<!-- /wp:paragraph --><cite>" . esc_html( $cite ) . "</cite></blockquote>\n<!-- /wp:quote -->";
		}
	}

	return implode( "\n\n", $out );
}

if ( $bcms_clean ) {
	foreach ( $projects as $project ) {
		$existing = get_page_by_path( $project['slug'], OBJECT, BCMS_Post_Types::PROJECT );
		if ( $existing ) {
			wp_delete_post( $existing->ID, true );
		}
	}
	WP_CLI::success( 'Demo projects removed.' );
	return;
}

$order = 0;

foreach ( $projects as $project ) {
	++$order;

	$existing = get_page_by_path( $project['slug'], OBJECT, BCMS_Post_Types::PROJECT );

	$postarr = array(
		'post_type'    => BCMS_Post_Types::PROJECT,
		'post_status'  => 'publish',
		'post_title'   => $project['title'],
		'post_name'    => $project['slug'],
		'post_excerpt' => $project['summary'],
		'post_content' => bcms_seed_blocks( $project['body'] ),
		'menu_order'   => $order,
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
	}

	$post_id = wp_insert_post( $postarr, true );

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( $project['slug'] . ': ' . $post_id->get_error_message() );
		continue;
	}

	wp_set_object_terms( $post_id, array( $project['industry'] ), BCMS_Post_Types::INDUSTRY );
	wp_set_object_terms( $post_id, $project['services'], BCMS_Post_Types::SERVICE );

	update_post_meta( $post_id, '_bcms_client', $project['client'] );
	update_post_meta( $post_id, '_bcms_year', $project['year'] );
	update_post_meta( $post_id, '_bcms_location', $project['location'] );
	update_post_meta( $post_id, '_bcms_duration', $project['duration'] );
	update_post_meta( $post_id, '_bcms_summary', $project['summary'] );
	update_post_meta( $post_id, '_bcms_featured', $project['featured'] );
	// update_post_meta() unslashes what it is given, so JSON has to go in
	// slashed or every → and £ arrives as literal "u2192" / "u00a3".
	update_post_meta( $post_id, '_bcms_stats', wp_slash( (string) wp_json_encode( $project['stats'] ) ) );

	$image_id = bcms_seed_image( $project['image'], $project['client'] . ' — case study hero' );
	if ( $image_id ) {
		set_post_thumbnail( $post_id, $image_id );
	}

	WP_CLI::log( 'Project: ' . $project['title'] );
}

/* -------------------------------------------------------------------------
 * Pages
 * ---------------------------------------------------------------------- */

$pages = array(
	'home'     => array(
		'title'    => 'Home',
		'content'  => '',
		'template' => '',
	),
	'about'    => array(
		'title'    => 'About',
		'template' => '',
		'content'  => implode(
			"\n\n",
			array(
				"<!-- wp:paragraph {\"className\":\"meridian-lede\"} -->\n<p class=\"meridian-lede\">We are a senior-only consulting firm. There is no pyramid: the people who win the work are the people who do it.</p>\n<!-- /wp:paragraph -->",
				"<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">How we work</h2>\n<!-- /wp:heading -->",
				"<!-- wp:paragraph -->\n<p>Small teams, fixed scope, and a written handover from day one rather than day last. We would rather turn down work than staff it with people who have not done it before.</p>\n<!-- /wp:paragraph -->",
				"<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Sectors</h2>\n<!-- /wp:heading -->",
				"<!-- wp:paragraph -->\n<p>Financial services, healthcare, logistics, public sector and manufacturing. We say no to sectors we do not know.</p>\n<!-- /wp:paragraph -->",
			)
		),
	),
	'contact'  => array(
		'title'    => 'Contact',
		'template' => 'page-contact',
		'content'  => implode(
			"\n\n",
			array(
				"<!-- wp:paragraph {\"className\":\"meridian-lede\"} -->\n<p class=\"meridian-lede\">Tell us what you are trying to fix. If we are not the right people we will say so and point you at who is.</p>\n<!-- /wp:paragraph -->",
				"<!-- wp:paragraph -->\n<p>We reply to every enquiry within one working day.</p>\n<!-- /wp:paragraph -->",
			)
		),
	),
	'privacy-policy' => array(
		'title'    => 'Privacy',
		'template' => '',
		'content'  => "<!-- wp:paragraph -->\n<p>We collect only what you send us through the contact form, and use it only to reply. We do not sell or share it. Ask us to delete it and we will.</p>\n<!-- /wp:paragraph -->",
	),
);

$page_ids = array();

foreach ( $pages as $slug => $page ) {
	$existing = get_page_by_path( $slug );

	$postarr = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $page['title'],
		'post_name'    => $slug,
		'post_content' => $page['content'],
	);

	if ( $existing ) {
		$postarr['ID'] = $existing->ID;
	}

	$page_id = wp_insert_post( $postarr, true );

	if ( is_wp_error( $page_id ) ) {
		continue;
	}

	if ( $page['template'] ) {
		update_post_meta( $page_id, '_wp_page_template', $page['template'] );
	}

	$page_ids[ $slug ] = (int) $page_id;
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_ids['home'] ?? 0 );

/* -------------------------------------------------------------------------
 * Navigation
 * ---------------------------------------------------------------------- */

$nav_markup = implode(
	"\n",
	array(
		'<!-- wp:navigation-link {"label":"Work","url":"' . esc_url( (string) get_post_type_archive_link( BCMS_Post_Types::PROJECT ) ) . '","kind":"custom","isTopLevelLink":true} /-->',
		'<!-- wp:navigation-link {"label":"About","url":"' . esc_url( (string) get_permalink( $page_ids['about'] ?? 0 ) ) . '","kind":"custom","isTopLevelLink":true} /-->',
		'<!-- wp:navigation-link {"label":"Contact","url":"' . esc_url( (string) get_permalink( $page_ids['contact'] ?? 0 ) ) . '","kind":"custom","isTopLevelLink":true} /-->',
	)
);

$nav = get_posts(
	array(
		'post_type'      => 'wp_navigation',
		'posts_per_page' => 1,
		'post_status'    => 'any',
	)
);

wp_insert_post(
	array(
		'ID'           => $nav ? $nav[0]->ID : 0,
		'post_type'    => 'wp_navigation',
		'post_status'  => 'publish',
		'post_title'   => 'Main navigation',
		'post_name'    => 'main-navigation',
		'post_content' => $nav_markup,
	)
);

/* -------------------------------------------------------------------------
 * Settings + roles demo users
 * ---------------------------------------------------------------------- */

$settings = BCMS_Settings::all();
$settings['company_name']    = 'Meridian Group';
$settings['company_tagline'] = 'Strategy, delivery and measurable outcomes for organisations that cannot afford to guess.';
$settings['contact_email']   = 'hello@example.com';
$settings['contact_phone']   = '+44 20 7000 0000';
$settings['contact_address'] = "12 Ludgate Square\nLondon EC4M 7DR";
$settings['linkedin_url']    = 'https://www.linkedin.com/';
update_option( BCMS_Settings::OPTION, $settings );

update_option( 'blogname', 'Meridian Group' );
update_option( 'blogdescription', 'Strategy, delivery and measurable outcomes.' );
update_option( 'timezone_string', 'Europe/London' );

foreach ( array(
	'portfolio.manager' => 'bcms_portfolio_manager',
	'case.writer'       => 'bcms_case_study_writer',
) as $login => $role ) {
	if ( ! get_user_by( 'login', $login ) ) {
		wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password( 20 ),
				'user_email' => $login . '@example.com',
				'role'       => $role,
			)
		);
	}
}

flush_rewrite_rules();

WP_CLI::success( 'Demo content seeded.' );
