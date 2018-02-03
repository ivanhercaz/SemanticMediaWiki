<?php

namespace SMW\Elastic\Indexer;

use Psr\Log\LoggerAwareTrait;
use SMW\SQLStore\ChangeOp\ChangeOp;
use SMW\SQLStore\ChangeOp\ChangeDiff;
use SMW\Utils\CharArmor;
use SMW\DataTypeRegistry;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\Elastic\Connection\Client as ElasticClient;
use Title;
use RuntimeException;

/**
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class Indexer {

	use LoggerAwareTrait;

	/**
	 * @var Store
	 */
	private $store;

	/**
	 * @var string
	 */
	private $origin = '';

	/**
	 * @var []
	 */
	private $profiling = [];

	/**
	 * @since 3.0
	 *
	 * @return array
	 */
	public function __construct( $store ) {
		$this->store = $store;
	}

	/**
	 * @since 3.0
	 *
	 * @param string $origin
	 */
	public function setOrigin( $origin ) {
		$this->origin = $origin;
	}

	/**
	 * @since 3.0
	 *
	 * @param ChangeOp $chnageOp
	 */
	public function delete( array $idList, $isConcept = false ) {

		$title = Title::newFromText( $this->origin . ':' . md5( json_encode( $idList ) ) );

		$params = [
			'delete' => $idList
		];

		if ( $this->isSafe( $title, $params ) === false ) {
			return;
		}

		$connection = $this->store->getConnection( 'elastic' );

		$index = $connection->getIndexNameByType(
			ElasticClient::TYPE_DATA
		);

		$params = [
			'index' => $index,
			'type'  => ElasticClient::TYPE_DATA
		];

		$bulk = new Bulk( $connection );
		$bulk->head( [ '_index' => $index, '_type' => ElasticClient::TYPE_DATA ] );

		$time = -microtime( true );

		foreach ( $idList as $id ) {

			$bulk->delete( [ '_id' => $id ] );

			if ( $isConcept ) {
				$bulk->delete(
					[
						'_index' => $connection->getIndexNameByType( ElasticClient::TYPE_LOOKUP ),
						'_type' => ElasticClient::TYPE_LOOKUP,
						'_id' => md5( $id )
					]
				);
			}
		}

		$bulk->execute();

		$context = [
			'method' => __METHOD__,
			'role' => 'developer',
			'origin' => $this->origin,
			'procTime' => $time + microtime( true )
		];

		$this->logger->info( 'Deleted: {origin}, procTime (in sec): {procTime}', $context );
	}

	/**
	 * @since 3.0
	 *
	 * @param DIWikiPage $dataItem
	 */
	public function create( DIWikiPage $dataItem ) {

		$title = $dataItem->getTitle();

		$params = [
			'create' => $dataItem->getHash()
		];

		if ( $this->isSafe( $title, $params ) === false ) {
			return;
		}

		$connection = $this->store->getConnection( 'elastic' );

		$index = $connection->getIndexNameByType(
			ElasticClient::TYPE_DATA
		);

		$params = [
			'index' => $index,
			'type'  => ElasticClient::TYPE_DATA,
			'id'    => $dataItem->getId()
		];

		$value['subject'] = [
			'subject' => [
				'title' => str_replace( '_', ' ', $dataItem->getDBKey() ),
				'subobject' => $dataItem->getSubobjectName(),
				'namespace' => $dataItem->getNamespace(),
				'interwiki' => $dataItem->getInterwiki(),
				'sortkey'   => $dataItem->getSortKey()
			]
		];

		$connection->index( $params + [ 'body' => $value ] );
	}

	/**
	 * @since 3.0
	 *
	 * @param ChangeDiff $changeDiff
	 */
	public function safeReplicate( ChangeDiff $changeDiff ) {

		$subject = $changeDiff->getSubject();

		$params = [
			'replicate' => $subject->getHash()
		];

		if ( $this->isSafe( $subject->getTitle(), $params ) ) {
			return $this->replicate( $changeDiff );
		}
	}

	/**
	 * @since 3.0
	 *
	 * @param ChangeDiff $changeDiff
	 */
	public function replicate( ChangeDiff $changeDiff ) {

		$time = -microtime( true );
		$connection = $this->store->getConnection( 'elastic' );

		$index = $connection->getIndexNameByType(
			ElasticClient::TYPE_DATA
		);

		$params = [
			'_index' => $index,
			'_type'  => ElasticClient::TYPE_DATA
		];

		$bulk = new Bulk( $connection );
		$bulk->head( $params );

		$this->doMap( $bulk, $changeDiff );
		$bulk->execute();

		$context = [
			'method' => __METHOD__,
			'role' => 'developer',
			'origin' => $this->origin,
			'subject' => $changeDiff->getSubject()->getHash(),
			'procTime' => $time + microtime( true )
		];

		$this->logger->info( 'Replicated: {subject}, procTime (in sec): {procTime}', $context );
	}

	private function isSafe( $title, array $params ) {

		$connection = $this->store->getConnection( 'elastic' );

		// Make sure a node is available
		if ( !$connection->ping() ) {
			$noNodesAvailableRecoveryJob = new NoNodesAvailableRecoveryJob(
				$title,
				$params
			);

			$noNodesAvailableRecoveryJob->insert();

			return false;
		}

		if ( !$connection->hasIndex( ElasticClient::TYPE_DATA ) ) {
			$connection->createIndex( ElasticClient::TYPE_DATA );
		}

		if ( !$connection->hasIndex( ElasticClient::TYPE_LOOKUP ) ) {
			$connection->createIndex( ElasticClient::TYPE_LOOKUP );
		}

		return true;
	}

	private function doMap( $bulk, $changeDiff ) {

		$inserts = [];
		$inverted = [];

		// In the event that a _SOBJ (or hereafter any inherited object)
		// is deleted, remove the reference directly from the index since
		// the object is embedded and is therefore handled outside of the
		// normal wikiPage delete action
		foreach ( $changeDiff->getTableChangeOps() as $tableChangeOp ) {
			foreach ( $tableChangeOp->getFieldChangeOps( ChangeOp::OP_DELETE ) as $fieldChangeOp ) {

				if ( !$fieldChangeOp->has( 'o_id' ) ) {
					continue;
				}

				$bulk->delete( [ '_id' => $fieldChangeOp->get( 'o_id' ) ] );
			}
		}

		foreach ( $changeDiff->getDataOps() as $tableChangeOp ) {
			foreach ( $tableChangeOp->getFieldChangeOps() as $fieldChangeOp ) {

				if ( !$fieldChangeOp->has( 's_id' ) ) {
					continue;
				}

				$this->mapRows( $fieldChangeOp, $inserts, $inverted );
			}
		}

		foreach ( $inverted as $id => $update ) {
			$bulk->upsert( [ '_id' => $id ], $update );
		}

		foreach ( $inserts as $id => $value ) {
			$bulk->index( [ '_id' => $id ], $value );
		}
	}

	private function mapRows( $fieldChangeOp, &$insertRows, &$invertedRows ) {

		// The structure to be expected in ES:
		//
		// "subject": {
		//    "title": "Foaf:knows",
		//    "subobject": "",
		//    "namespace": 102,
		//    "interwiki": "",
		//    "sortkey": "Foaf:knows"
		// },
		// "P:8": {
		//    "txtField": [
		//       "foaf knows http://xmlns.com/foaf/0.1/ Type:Page"
		//    ]
		// },
		// "P:29": {
		//    "datField": [
		//       2458150.6958333
		//    ]
		// },
		// "P:1": {
		//    "uriField": [
		//       "http://semantic-mediawiki.org/swivt/1.0#_wpg"
		//    ]
		// }

		// - datField (time value) is a numeric field (JD number) to allow using
		// ranges on dates with values being representable from January 1, 4713 BC
		// (proleptic Julian calendar)

		$sid = $fieldChangeOp->get( 's_id' );
		$pid = 'P:' . $fieldChangeOp->get( 'p_id' );

		if ( !isset( $insertRows[$sid] ) ) {
			$insertRows[$sid] = [];
		}

		if ( !isset( $insertRows[$sid]['subject'] ) ) {
			$dataItem = $this->store->getObjectIds()->getDataItemById( $sid );
			$sort = $dataItem->getSortKey();

			// Use collated sort field if available
			if ( $dataItem->getOption( 'sort' ) !== '' ) {
				$sort = $dataItem->getOption( 'sort' );
			}

			// Avoid issue with the Ealstic serializer
			$sort = CharArmor::removeSpecialChars(
				CharArmor::removeControlChars( $sort )
			);

			$insertRows[$sid]['subject'] = [
				'title' => str_replace( '_', ' ', $dataItem->getDBKey() ),
				'subobject' => $dataItem->getSubobjectName(),
				'namespace' => $dataItem->getNamespace(),
				'interwiki' => $dataItem->getInterwiki(),
				'sortkey'   => $sort
			];
		}

		$ins = $fieldChangeOp->getChangeOp();
		unset( $ins['s_id'] );

		$val = 'n/a';
		$type = 'wpgField';

		if ( $fieldChangeOp->has( 'o_blob' ) && $fieldChangeOp->has( 'o_hash' ) ) {
			$type = 'txtField';
			$val = $ins['o_blob'] === null ? $ins['o_hash'] : $ins['o_blob'];
		} elseif ( $fieldChangeOp->has( 'o_serialized' ) && $fieldChangeOp->has( 'o_blob' ) ) {
			$type = 'uriField';
			$val = $ins['o_blob'] === null ? $ins['o_serialized'] : $ins['o_blob'];
		} elseif ( $fieldChangeOp->has( 'o_serialized' ) && $fieldChangeOp->has( 'o_sortkey' ) ) {
			$type = strpos( $ins['o_serialized'], '/' ) !== false ? 'datField' : 'numField';
			$val = (float)$ins['o_sortkey'];
		} elseif ( $fieldChangeOp->has( 'o_value' ) ) {
			$type = 'booField';
			// Avoid a "Current token (VALUE_NUMBER_INT) not of boolean type ..."
			$val = $ins['o_value'] ? true : false;
		} elseif ( $fieldChangeOp->has( 'o_lat' ) ) {
			// https://www.elastic.co/guide/en/elasticsearch/reference/6.1/geo-point.html
			// Geo-point expressed as an array with the format: [ lon, lat ]
			// Geo-point expressed as a string with the format: "lat,lon".
			$type = 'geoField';
			$val = $ins['o_serialized'];
		} elseif ( $fieldChangeOp->has( 'o_id' ) ) {
			$type = 'wpgField';
			$val = CharArmor::removeSpecialChars( CharArmor::removeControlChars(
				$this->store->getObjectIds()->getDataItemById( $ins['o_id'] )->getSortKey()
			) );

			if ( !isset( $insertRows[$sid][$pid][$type] ) ) {
				$insertRows[$sid][$pid][$type] = [];
			}

			$insertRows[$sid][$pid][$type] = array_merge( $insertRows[$sid][$pid][$type], [ $val ] );
			$type = 'wpgID';
			$val = (int)$ins['o_id'];

			// Create a minimal body for an inverted relation
			//
			// When a query `[[-Has mother::Michael]]` inquiries that relationship
			// on the fact of `Michael` -> `[[Has mother::Carol]] with `Carol`
			// being redlinked (not exists as page) the query can match the object
			if ( !isset( $invertedRows[$val] ) ) {
				$invertedRows[$val] = [ 'noop' => [] ];
			}

			// A null, [] (an empty array), and [null] are all equivalent, they
			// simply don't exists in an inverted index
		}

		if ( !isset( $insertRows[$sid][$pid][$type] ) ) {
			$insertRows[$sid][$pid][$type] = [];
		}

		$insertRows[$sid][$pid][$type] = array_merge(
			$insertRows[$sid][$pid][$type],
			[ $val ]
		);
	}

}
