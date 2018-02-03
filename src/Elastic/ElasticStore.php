<?php

namespace SMW\Elastic;

use SMW\SQLStore\SQLStore;
use SMW\ApplicationFactory;
use SMW\SemanticData;
use SMW\DIWikiPage;
use SMW\Options;
use SMWQuery as Query;
use Title;
use RuntimeException;

/**
 * @private
 *
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class ElasticStore extends SQLStore {

	/**
	 * @var ElasticFactory
	 */
	private $elasticFactory;

	/**
	 * @var Indexer
	 */
	private $indexer;

	/**
	 * @var QueryEngine
	 */
	private $queryEngine;

	/**
	 * @since 3.0
	 */
	public function __construct() {
		parent::__construct();
		$this->elasticFactory = new ElasticFactory();
	}

	/**
	 * @see SQLStore::deleteSubject
	 * @since 3.0
	 *
	 * @param Title $title
	 */
	public function deleteSubject( Title $title ) {
		parent::deleteSubject( $title );

		if ( $this->indexer === null ) {
			$this->indexer = $this->elasticFactory->newIndexer( $this );
		}

		$this->indexer->setOrigin( 'ElasticStore::DeleteSubject' );
		$idList = [];

		if ( $this->getExtensionData( 'delete.list' ) ) {
			$idList = $this->getExtensionData( 'delete.list' );
		}

		$this->indexer->delete( $idList, $title->getNamespace() === SMW_NS_CONCEPT );

		$this->setExtensionData( 'delete.list', null );
	}

	/**
	 * @see SQLStore::changeTitle
	 * @since 3.0
	 *
	 * @param Title $oldtitle
	 * @param Title $newtitle
	 * @param integer $pageid
	 * @param integer $redirid
	 */
	public function changeTitle( Title $oldTitle, Title $newTitle, $pageId, $redirectId = 0 ) {
		parent::changeTitle( $oldTitle, $newTitle, $pageId, $redirectId );

		$id = $this->getObjectIds()->getSMWPageID(
			$oldTitle->getDBkey(),
			$oldTitle->getNamespace(),
			'',
			'',
			false
		);

		if ( $this->indexer === null ) {
			$this->indexer = $this->elasticFactory->newIndexer( $this );
		}

		$this->indexer->setOrigin( 'ElasticStore::ChangeTitle' );
		$idList = [ $id ];

		if ( $this->getExtensionData( 'delete.list' ) ) {
			$idList = array_merge( $idList, $this->getExtensionData( 'delete.list' ) );
		}

		$this->indexer->delete( $idList );

		// Use case [[Foo]] redirects to #REDIRECT [[Bar]] with Bar not yet being
		// materialized and with the update not having created any reference,
		// fulfill T:Q0604 by allowing to create a minimized document body
		if ( $newTitle->exists() === false ) {
			$id = $this->getObjectIds()->getSMWPageID(
				$newTitle->getDBkey(),
				$newTitle->getNamespace(),
				'',
				'',
				false
			);

			$dataItem = DIWikiPage::newFromTitle( $newTitle );
			$dataItem->setId( $id );

			$this->indexer->create( $dataItem );
		}

		$this->setExtensionData( 'delete.list', null );
	}

	/**
	 * @see SQLStore::fetchQueryResult
	 * @since 3.0
	 *
	 * @param Query $query
	 */
	protected function fetchQueryResult( Query $query ) {

		if ( $this->queryEngine === null ) {
			$this->queryEngine = $this->elasticFactory->newQueryEngine( $this );
		}

		return $this->queryEngine->getQueryResult( $query );
	}

	/**
	 * @see SQLStore::doDataUpdate
	 * @since 3.0
	 *
	 * @param SemanticData $semanticData
	 */
	protected function doDataUpdate( SemanticData $semanticData ) {
		parent::doDataUpdate( $semanticData );

		if ( $this->indexer === null ) {
			$this->indexer = $this->elasticFactory->newIndexer( $this );
		}

		$this->indexer->setOrigin( 'ElasticStore::DoDataUpdate' );

		if ( $this->getExtensionData( 'delete.list' ) ) {
			$this->indexer->delete( $this->getExtensionData( 'delete.list' ) );
		}

		$this->indexer->safeReplicate(
			$this->getExtensionData( 'change.diff' )
		);

		$this->setExtensionData( 'delete.list', null );
		$this->setExtensionData( 'change.diff', null );
	}

	/**
	 * @see SQLStore::doDataUpdate
	 * @since 3.0
	 */
	public function clear() {
		parent::clear();
		$this->indexer = null;
		$this->queryEngine = null;
	}

}
