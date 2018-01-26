<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;

// Detect commandline args
$conffile = 'config.json';
$csvfile = 'list.csv';


if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$csvfile = $argv[2];
}

// Detect if files
if ( ! file_exists( $conffile ) || ! file_exists( $csvfile ) ) {
	die( "Files needed" );
}

$confjson = json_decode( file_get_contents( $conffile ), 1 );

$wikiconfig = null;
$wikidataconfig = null;
$orcidconfig = null;

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

if ( array_key_exists( "wikidata", $confjson ) ) {
	$wikidataconfig = $confjson["wikidata"];
}

if ( array_key_exists( "orcid", $confjson ) ) {
	$orcidconfig = $confjson["orcid"];
	# TODO: to check if needed
}

# Base 
# curl -X GET --header 'Accept: application/orcid+json' 'https://pub.orcid.org/v2.1/0000-0003-2016-6465'

# Check ORCIDS from query.wikidata.org

#Researchers with Certain ORCID
//SELECT ?human ?humanLabel ?orcid
//WHERE 
//{
//  ?human wdt:P31 wd:Q5 .
//  ?human wdt:P496 ?orcid .
//  FILTER ( 
//    ?orcid IN ( "0000-0002-5738-4477", "0000-0002-5738-4472" )
//  )
//  SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en". }
//}

#https://query.wikidata.org/sparql?query=encodedquery&format=json


