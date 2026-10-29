<?php
/**
 * Hand-written structured data (JSON-LD) for each apartment page, so search engines know these are holiday rentals.
 *
 * Rank Math adds the site-wide LodgingBusiness / WebSite markup; page-level "Article" markup is switched off in
 * Rank Math (Titles & Meta → Pages → Schema type: None). This adds a VacationRental block to the apartment pages in
 * every language. The facts are copied from the apartment pages; edit them here if an apartment changes.
 * The description comes from the page's Rank Math meta description, so it follows the page language.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Apartment facts, keyed by every page ID (EN, DE, FR) that describes that apartment. */
function za_schema_apartments() {
	$uploads = 'https://zermattapartments.ch/wp-content/uploads/';

	$gornerwald = array(
		'name'      => 'Haus Gornerwald',
		'street'    => 'Schluhmattstrasse 63',
		'bedrooms'  => 2,
		'bathrooms' => 2,
		'guests'    => 5,
		'amenities' => array( 'Wifi', 'Balcony with Matterhorn view', 'Dishwasher', 'Washing machine', 'Smart TV', 'Lift' ),
		'images'    => array( $uploads . '2025/05/gorner-20250531-18.jpg', $uploads . '2025/05/gorner-20250531-17.jpg' ),
	);
	$imperial = array(
		'name'      => 'Haus Imperial',
		'street'    => 'Unterer Mattenweg 22',
		'bedrooms'  => 2,
		'bathrooms' => 2,
		'guests'    => 5,
		'amenities' => array( 'Wifi', 'Balcony with Matterhorn view', 'Dishwasher', 'Smart TV', 'Non-smoking' ),
		'images'    => array( $uploads . '2025/05/Imperial-20250531-2-scaled.jpg', $uploads . '2025/05/Imperial-20250531-1-scaled.jpg' ),
	);

	return array(
		133 => $gornerwald, // EN
		300 => $gornerwald, // DE
		305 => $gornerwald, // FR
		138 => $imperial,   // EN
		318 => $imperial,   // DE
		313 => $imperial,   // FR
	);
}

/** The JSON-LD array for an apartment page. */
function za_schema_for_page( $page_id, $apartment ) {
	$url         = get_permalink( $page_id );
	$description = trim( (string) get_post_meta( $page_id, 'rank_math_description', true ) );

	$amenities = array();
	foreach ( $apartment['amenities'] as $amenity ) {
		$amenities[] = array(
			'@type' => 'LocationFeatureSpecification',
			'name'  => $amenity,
			'value' => true,
		);
	}

	$schema = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'VacationRental',
		'@id'           => $url . '#rental',
		'name'          => $apartment['name'],
		'url'           => $url,
		'image'         => $apartment['images'],
		'checkinTime'   => '15:00',
		'address'       => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $apartment['street'],
			'postalCode'      => '3920',
			'addressLocality' => 'Zermatt',
			'addressRegion'   => 'Valais',
			'addressCountry'  => 'CH',
		),
		'containsPlace' => array(
			'@type'                  => 'Accommodation',
			'additionalType'         => 'EntirePlace',
			'numberOfBedrooms'       => $apartment['bedrooms'],
			'numberOfBathroomsTotal' => $apartment['bathrooms'],
			'occupancy'              => array(
				'@type'    => 'QuantitativeValue',
				'maxValue' => $apartment['guests'],
			),
			'amenityFeature'         => $amenities,
		),
		'brand'         => array( '@id' => 'https://zermattapartments.ch/#organization' ),
	);
	if ( '' !== $description ) {
		$schema['description'] = $description;
	}
	return $schema;
}

add_action(
	'wp_head',
	function () {
		if ( ! is_page() ) {
			return;
		}
		$page_id    = (int) get_queried_object_id();
		$apartments = za_schema_apartments();
		if ( ! isset( $apartments[ $page_id ] ) ) {
			return;
		}
		echo "\n" . '<script type="application/ld+json" class="za-schema">'
			. wp_json_encode( za_schema_for_page( $page_id, $apartments[ $page_id ] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";
	},
	20
);
