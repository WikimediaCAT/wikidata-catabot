<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;

$api = new MwApi\MediawikiApi( "https://www.wikidata.org/w/api.php" );

$dataValueClasses = array(
    'unknown' => 'DataValues\UnknownValue',
    'string' => 'DataValues\StringValue',
    'boolean' => 'DataValues\BooleanValue',
    'number' => 'DataValues\NumberValue',
    'time' => 'DataValues\TimeValue',
    'globecoordinate' => 'DataValues\Geo\Values\GlobeCoordinateValue',
    'wikibase-entityid' => 'Wikibase\DataModel\Entity\EntityIdValue',
);

$wbFactory = new WbApi\WikibaseFactory(
    $api,
    new DataValues\Deserializers\DataValueDeserializer( $dataValueClasses ),
    new DataValues\Serializers\DataValueSerializer()
);


$revision = $wbFactory->newRevisionGetter()->getFromId( 'Q16943393' );
$item = $revision->getContent()->getData();

#var_dump( $item );

$statementList = $item->getStatements();

# ./vendor/wikibase/data-model/src/Statement/StatementList.php

$propIds = $statementList->getPropertyIds();

var_dump( array_keys( $propIds ) );

var_dump( $statementList );


$api->logout();
