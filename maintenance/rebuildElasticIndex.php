<?php

namespace SMW\Maintenance;

use SMW\ApplicationFactory;
use SMW\SQLStore\SQLStore;
use SMW\Elastic\ElasticFactory;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv(
'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class RebuildElasticIndex extends \Maintenance {

	public function __construct() {
		$this->mDescription = 'Rebuild the Elasticsearch index from property tables (those are not explicitly parsed!)';
		$this->addOption( 's', 'Start with a selected document ID', false, true );
		$this->addOption( 'update-settings', 'Update settings and mappings for all available indices', false, false );
		$this->addOption( 'force-refresh', 'Forces a refresh of all available indices', false, false );
		$this->addOption( 'initalize-indices', 'Delete and recreate all available indices without rebuilding the data', false, false );

		$this->addOption( 'debug', 'Sets global variables to support debug ouput while running the script', false );

		parent::__construct();
	}

	/**
	 * @see Maintenance::addDefaultParams
	 *
	 * @since 3.0
	 */
	protected function addDefaultParams() {
		parent::addDefaultParams();
	}

	/**
	 * @see Maintenance::execute
	 */
	public function execute() {

		if ( !defined( 'SMW_VERSION' ) ) {
			$this->output( "You need to have SMW enabled in order to use this maintenance script!\n\n" );
			exit;
		}

		$applicationFactory = ApplicationFactory::getInstance();
		$maintenanceFactory = $applicationFactory->newMaintenanceFactory();

		$maintenanceHelper = $maintenanceFactory->newMaintenanceHelper();
		$maintenanceHelper->initRuntimeValues();

		if ( $this->hasOption( 'debug' ) ) {
			$maintenanceHelper->setGlobalToValue( 'wgShowExceptionDetails', true );
			$maintenanceHelper->setGlobalToValue( 'wgShowSQLErrors', true );
			$maintenanceHelper->setGlobalToValue( 'wgShowDBErrorBacktrace', true );
		} else {
			$maintenanceHelper->setGlobalToValue( 'wgDebugLogFile', '' );
			$maintenanceHelper->setGlobalToValue( 'wgDebugLogGroups', [] );
		}

		$store = $applicationFactory->getStore( 'SMW\SQLStore\SQLStore' );
		$elasticFactory = new ElasticFactory();

		$rebuilder = $elasticFactory->newRebuilder(
			$store
		);

		if ( !$rebuilder->ping() ) {
			return $this->reportMessage(
				"\n" . 'Elasticsearch endpoint(s) are not available!' . "\n"
			);
		}

		$this->reportMessage(
			"\nThe script rebuilds the index from available property tables. Any\n".
			"change of the index rules (altered stopwords, new stemmer etc.) and\n".
			"or a newly added (or altered) table requires to run this script again\n".
			"to ensure that the index complies with the rules set forth by the SQL\n" .
			"back-end or the Elasticsearch field mapping.\n"
		);

		if ( $this->hasOption( 'update-settings' ) || $this->hasOption( 'initalize-indices' ) || $this->hasOption( 'force-refresh' )  ) {
			$this->reportMessage( "\nPerforming maintenance ...\n" );
		}

		if ( $this->hasOption( 'update-settings' ) ) {
			$this->reportMessage(
				"\n" . '   ... updating index settings and mappings ...' . "\n"
			);

			return $rebuilder->setDefaults();
		}

		if ( $this->hasOption( 'force-refresh' ) ) {
			$this->reportMessage(
				"\n" . '   ... forcing a refresh of known indices ...' . "\n"
			);

			return $rebuilder->refresh();
		}

		if ( $this->hasOption( 'initalize-indices' ) ) {
			$this->reportMessage(
				"\n" . '   ... deleting and creating indices (without any data rebuild) ...' . "\n"
			);

			return $rebuilder->deleteAndCreateIndices();
		}

		if ( !$this->hasOption( 'quick' ) ) {
			$this->reportMessage( "\n" . 'Abort the rebuild with control-c in the next five seconds ...  ' );
			wfCountDown( 5 );
		}

		$this->reportMessage( "\nRebuilding documents ...\n" );

		if ( !$this->hasOption( 's' ) ) {
			$this->reportMessage( "\n" . '   ... deleting and recreating required indices ...' );
			$rebuilder->deleteAndCreateIndices();
		}

		$this->performRebuild( $store, $rebuilder );

		return true;
	}

	private function performRebuild( $store, $rebuilder ) {

		$rebuilder->prepare();
		$i = 1;

		$connection = $store->getConnection( 'mw.db' );
		$conditions = [];

		$conditions[] = "smw_iw!=" . $connection->addQuotes( SMW_SQL3_SMWIW_OUTDATED );

		if ( $this->hasOption( 's' ) ) {
			$i = $this->getOption( 's' );
			$conditions[] = 'smw_id > ' . $connection->addQuotes( $this->getOption( 's' ) );
		}

		$res = $connection->select(
			SQLStore::ID_TABLE,
			[
				'smw_id',
				'smw_iw'
			],
			$conditions,
			__METHOD__,
			[ 'ORDER BY' => 'smw_id' ]
		);

		$last = $connection->selectField(
			SQLStore::ID_TABLE,
			'MAX(smw_id)',
			'',
			__METHOD__
		);

		if ( $res->numRows() > 0 ) {
			$this->reportMessage( "\n" );
		} else {
			$this->reportMessage( "\n" . '   ... no documents to process ...' );
		}

		foreach ( $res as $row ) {

			$i = $row->smw_id;

			$this->reportMessage(
				"\r". sprintf( "%-50s%s", "   ... updating document no.", sprintf( "%4.0f%% (%s/%s)", ( $i / $last ) * 100, $i, $last ) )
			);

			if ( $row->smw_iw === SMW_SQL3_SMWDELETEIW || $row->smw_iw === SMW_SQL3_SMWREDIIW ) {
				$rebuilder->delete( $row->smw_id );
				continue;
			}

			$dataItem = $store->getObjectIds()->getDataItemById(
				$row->smw_id
			);

			if ( $dataItem === null ) {
				continue;
			}

			$semanticData = $store->getSemanticData( $dataItem );

			$rebuilder->rebuild( $row->smw_id, $semanticData );
		}

		$this->reportMessage( "\n" . '   ... updating index settings and mappings ...' );
		$rebuilder->setDefaults();

		$this->reportMessage( "\n" . '   ... refreshing indices' . "\n" );
		$rebuilder->refresh();
	}

	/**
	 * @since 3.0
	 *
	 * @param string $message
	 */
	public function reportMessage( $message ) {
		$this->output( $message );
	}

}

$maintClass = 'SMW\Maintenance\RebuildElasticIndex';
require_once( RUN_MAINTENANCE_IF_MAIN );
