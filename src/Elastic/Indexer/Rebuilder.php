<?php

namespace SMW\Elastic\Indexer;

use SMW\SQLStore\SQLStore;
use SMW\SQLStore\PropertyTableRowMapper;
use SMW\Elastic\Connection\Client as ElasticClient;
use SMW\ApplicationFactory;
use SMW\SemanticData;
use RuntimeException;

/**
 * @private
 *
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class Rebuilder {

	/**
	 * @var ElasticClient
	 */
	private $client;

	/**
	 * @var Indexer
	 */
	private $indexer;

	/**
	 * @var PropertyTableRowMapper
	 */
	private $propertyTableRowMapper;

	/**
	 * @var array
	 */
	private $settings = [];

	/**
	 * @since 3.0
	 *
	 * @param ElasticClient $client
	 * @param Indexer $indexer
	 * @param PropertyTableRowMapper $propertyTableRowMapper
	 */
	public function __construct( ElasticClient $client, Indexer $indexer, PropertyTableRowMapper $propertyTableRowMapper ) {
		$this->client = $client;
		$this->indexer = $indexer;
		$this->propertyTableRowMapper = $propertyTableRowMapper;
	}

	/**
	 * @since 3.0
	 *
	 * @return boolean
	 */
	public function ping() {
		return $this->client->ping();
	}

	/**
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-update-settings.html#bulk
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/tune-for-indexing-speed.html#_disable_refresh_and_replicas_for_initial_loads
	 *
	 * @since 3.0
	 */
	public function prepare() {
		$this->doPrepareByType( ElasticClient::TYPE_DATA );
		$this->doPrepareByType( ElasticClient::TYPE_LOOKUP );
	}

	/**
	 * @since 3.0
	 */
	public function deleteAndCreateIndices() {
		$this->doDeleteAndCreateIndicesByType( ElasticClient::TYPE_DATA );
		$this->doDeleteAndCreateIndicesByType( ElasticClient::TYPE_LOOKUP );
	}

	/**
	 * @since 3.0
	 */
	public function setDefaults() {
		$this->doSetDefaultByType( ElasticClient::TYPE_DATA );
		$this->doSetDefaultByType( ElasticClient::TYPE_LOOKUP );
	}

	/**
	 * @since 3.0
	 *
	 * @param integer $id
	 */
	public function delete( $id ) {
		$this->indexer->delete( [ $id ] );
	}

	/**
	 * @since 3.0
	 *
	 * @param integer $id
	 * @param SemanticData $semanticData
	 */
	public function rebuild( $id, SemanticData $semanticData ) {

		$changeOp = $this->propertyTableRowMapper->newChangeOp(
			$id,
			$semanticData
		);

		$this->indexer->replicate( $changeOp->newChangeDiff() );
	}

	/**
	 * @since 3.0
	 */
	public function refresh() {
		$this->doRefreshByType( ElasticClient::TYPE_DATA );
		$this->doRefreshByType( ElasticClient::TYPE_LOOKUP );
	}

	public function doPrepareByType( $type ) {

		$index = $this->client->getIndexNameByType( $type );

		if ( !$this->client->hasIndex( $type, false ) ) {
			$this->client->createIndex( $type );
		}

		$settings = $this->client->getSettings(
			[ 'index' => $index ]
		);

		$this->settings[$type] = [
			'number_of_replicas' => $settings[$index]['settings']['index']['number_of_replicas'],
			'refresh_interval' => $settings[$index]['settings']['index']['refresh_interval']
		];

		$params = [
			'index' => $index,
			'body' => [
				'settings' => [
					'number_of_replicas' => 0,
					'refresh_interval' => -1
				]
			]
		];

		$this->client->putSettings( $params );
	}

	public function doRefreshByType( $type ) {

		$index = $this->client->getIndexNameByType( $type );

		$this->client->refresh(
			[ 'index' => $index ]
		);
	}

	private function doSetDefaultByType( $type ) {

		$index = $this->client->getIndexNameByType(
			$type
		);

		$indexDef = $this->client->getIndexDefByType(
			$type
		);

		if ( !$this->client->hasIndex( $type ) ) {
			$this->client->createIndex( $type );
		}

		$indexDef = json_decode( $indexDef, true );

		// "Can't update non dynamic settings [[index.analysis. ...
		unset( $indexDef['settings']['analysis'] );
		unset( $indexDef['settings']['number_of_shards'] );

		$params = [
			'index' => $index,
			'body' => [
				'settings' => $indexDef['settings']
			]
		];

		$this->client->putSettings( $params );

		$params = [
			'index' => $index,
			'type'  => $type,
			'body'  => $indexDef['mappings']
		];

		$this->client->putMapping( $params );;
	}

	private function doDeleteAndCreateIndicesByType( $type ) {
		$this->client->deleteIndex( $type );
		$this->client->createIndex( $type );
	}

}
