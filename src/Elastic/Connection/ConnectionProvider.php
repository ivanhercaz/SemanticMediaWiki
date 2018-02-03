<?php

namespace SMW\Elastic\Connection;

use Elasticsearch\ClientBuilder;
use SMW\Connection\ConnectionProvider as IConnectionProvider;
use RuntimeException;
use SMW\ApplicationFactory;
use SMW\Options;

/**
 * @private
 *
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class ConnectionProvider implements IConnectionProvider {

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var ElasticClient
	 */
	private $connection;

	/**
	 * @since 3.0
	 *
	 * @param Options $options
	 */
	public function __construct( Options $options = null ) {
		$this->options = $options;
	}

	/**
	 * @see ConnectionProvider::getConnection
	 *
	 * @since 3.0
	 *
	 * @return Connection
	 */
	public function getConnection() {

		if ( $this->connection !== null ) {
			return $this->connection;
		}

		$applicationFactory = ApplicationFactory::getInstance();

		$conf = $applicationFactory->getSettings()->get( 'smwgElasticsearchConfig' );

		$params = [
			'hosts' => $applicationFactory->getSettings()->get( 'smwgElasticsearchEndpoints' ),
			'retries' => $conf['connection']['retries'],

		// Use `singleHandler` if you know you will never need async capabilities,
		// since it will save a small amount of overhead by reducing indirection
		//	'handler' => ClientBuilder::singleHandler()
		];

		if ( class_exists( '\Elasticsearch\ClientBuilder' ) ) {
			$this->connection = new Client(
				ClientBuilder::fromConfig( $params, true ),
				$applicationFactory->getCache(),
				new Options( $conf )
			);
		} else {
			$this->connection = new DummyClient();
		}

		$this->connection->setLogger(
			$applicationFactory->getMediaWikiLogger( 'smw-elastic' )
		);

		$context = [
			'role' => 'developer',
			'provider' => 'elastic',
			'hosts' => json_encode( $params['hosts'] )
		];

		$applicationFactory->getMediaWikiLogger( 'smw' )->info( "[Connection] '{provider}': {hosts}", $context );

		return $this->connection;
	}

	/**
	 * @see ConnectionProvider::releaseConnection
	 *
	 * @since 3.0
	 */
	public function releaseConnection() {
		$this->connection = null;
	}

}
