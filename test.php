<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;

$api = new MwApi\MediawikiApi( "https://www.wikidata.org/w/api.php" );

$dataValueClasses = array(
    'unknown' => 'DataValues\UnknownValue',
    'string' => 'DataValues\StringValue',
);

$wbFactory = new WbApi\WikibaseFactory(
    $api,
    new DataValues\Deserializers\DataValueDeserializer( $dataValueClasses ),
    new DataValues\Serializers\DataValueSerializer()
);


$revision = $wbFactory->newRevisionGetter()->getFromId( 'Q16943393' );
$item = $revision->getContent()->getData();

var_dump( $item );

$api->logout();