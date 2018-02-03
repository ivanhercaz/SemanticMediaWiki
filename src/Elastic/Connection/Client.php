<?php

namespace SMW\Elastic\Connection;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use SMW\Options;
use Elasticsearch\Client as ElasticClient;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Onoi\Cache\Cache;
use Onoi\Cache\NullCache;
use Exception;

/**
 * Reduced interface to the Elasticsearch client class.
 *
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class Client {

	use LoggerAwareTrait;

	/**
	 * Identifies the cache namespace
	 */
	const CACHE_NAMESPACE = 'smw:elastic';

	const CACHE_CHECK_TTL = 3600;

	/**
	 * @see https://www.elastic.co/blog/index-vs-type
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/removal-of-types.html
	 *
	 * " ... Indices created in Elasticsearch 6.0.0 or later may only contain a
	 * single mapping type ..."
	 */
	const TYPE_DATA = 'data';

	/**
	 * Index, type to temporary store index lookups during the execution
	 * of subqueries.
	 */
	const TYPE_LOOKUP = 'lookup';

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * @var Cache
	 */
	private $cache;

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var boolean
	 */
	private $inTest = false;

	/**
	 * @var boolean
	 */
	private static $hasIndex = [];

	/**
	 * @since 3.0
	 *
	 * @param ElasticClient $client
	 * @param Cache|null $cache
	 * @param Options|null $options
	 */
	public function __construct( ElasticClient $client, Cache $cache = null, Options $options = null ) {
		$this->client = $client;
		$this->cache = $cache;
		$this->options = $options;
		$this->inTest = defined( 'MW_PHPUNIT_TEST' );

		if ( $this->cache === null ) {
			$this->cache = new NullCache();
		}

		if ( $this->options === null ) {
			$this->options = new Options();
		}

		$this->logger = new NullLogger();
	}

	/**
	 * @since 3.0
	 *
	 * @return Options
	 */
	public function getConfig() {
		return $this->options;
	}

	/**
	 * @since 3.0
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public static function getIndexNameByType( $type ) {
		static $indices = [];

		if ( !isset( $indices[$type] ) ) {
			$indices[$type] = "smw-$type-" . wfWikiID();
		}

		return $indices[$type];
	}

	/**
	 * @since 3.0
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public function getIndexDefByType( $type ) {
		static $indexDef = [];

		if ( isset( $indexDef[$type] ) ) {
			return $indexDef[$type];
		}

		$indexDef[$type] = file_get_contents( $this->options->dotGet( "index.$type" ) );

		// Modify setting on-the-fly from config
		if ( $this->options->dotGet( "settings.$type", [] ) !== [] ) {
			$definition = json_decode( $indexDef[$type], true );
			$definition['settings'] = $this->options->dotGet( "settings.$type" ) + $definition['settings'];
			$indexDef[$type] = json_encode( $definition );
		}

		return $indexDef[$type];
	}

	/**
	 * @since 1.2.0
	 *
	 * @return integer
	 */
	public function getIndexDefFileModificationTimeByType( $type ) {

		static $filemtime = [];

		if ( !isset( $filemtime[$type] ) ) {
			$filemtime[$type] = filemtime( $this->options->dotGet( "index.$type" ) );
		}

		return $filemtime[$type];
	}

	/**
	 * @since 3.0
	 *
	 * @param array
	 */
	public function info() {
		return $this->client->info( [] );
	}

	/**
	 * @since 3.0
	 *
	 * @param array
	 */
	public function stats( $params = [] ) {

		$indices = [
			$this->getIndexNameByType( self::TYPE_DATA ),
			$this->getIndexNameByType( self::TYPE_LOOKUP )
		];

		$params = [ 'index' => $indices ] + $params;

		$res = $this->client->indices()->stats(
			$params
		);

		return [
			$indices[0] => $res['indices'][$indices[0]],
			$indices[1] => $res['indices'][$indices[1]]
		];
	}

	/**
	 * @since 3.0
	 *
	 * @param array
	 */
	public function cat( $type, $params = [] ) {

		$res = [];

		if ( $type === 'indices' ) {
			$indices = $this->client->cat()->indices( $params );

			foreach ( $indices as $key => $value ) {
				$res[$value['index']] = $indices[$key];
				unset( $res[$value['index']]['index'] );
			}
		}

		return $res;
	}

	/**
	 * @since 3.0
	 *
	 * @param string $type
	 * @param boolean $useCache
	 *
	 * @param boolean
	 */
	public function hasIndex( $type, $useCache = true ) {

		if ( isset( self::$hasIndex[$type] ) && self::$hasIndex[$type] ) {
			return true;
		}

		$index = $this->getIndexNameByType( $type );

		$key = smwfCacheKey(
			self::CACHE_NAMESPACE,
			[
				$index,
				// A modified file causes a new cache key!
				$this->getIndexDefFileModificationTimeByType( $type )
			]
		);

		// Be dynamic to fetch the latest possible mapping
		if ( $this->inTest ) {
			$key = self::CACHE_NAMESPACE . ':test:' . uniqid();
		}

		if ( $useCache && $this->cache->fetch( $key ) !== false ) {
			return self::$hasIndex[$type] = true;
		}

		$ret = $this->client->indices()->exists(
			[
				'index' => $index
			]
		);

		if ( $ret ) {
			$this->cache->save( $key, true, self::CACHE_CHECK_TTL );
		}

		return self::$hasIndex[$type] = $ret;
	}

	/**
	 * @since 3.0
	 *
	 * @param string $type
	 */
	public function createIndex( $type ) {

		$index = $this->getIndexNameByType( $type );

		$params = [
			'index' => $index,
			'body'  => $this->getIndexDefByType( $type )
		];

		$response = $this->client->indices()->create( $params );

		$context = [
			'method' => __METHOD__,
			'role' => 'user',
			'index' => $index,
			'reponse' => json_encode( $response )
		];

		$this->logger->info( 'Created index {index} with: {reponse}', $context );
	}

	/**
	 * @since 3.0
	 *
	 * @param string $type
	 */
	public function deleteIndex( $type ) {

		$index = $this->getIndexNameByType( $type );

		$params = [
			'index' => $index,
		];

		try {
			$response = $this->client->indices()->delete( $params );
		} catch ( Exception $e ) {
			$response = $e->getMessage();
		}

		$key = smwfCacheKey(
			self::CACHE_NAMESPACE,
			[
				$index,
				// A modified file causes a new cache key!
				$this->getIndexDefFileModificationTimeByType( $type )
			]
		);

		$this->cache->delete( $key );

		$context = [
			'method' => __METHOD__,
			'role' => 'user',
			'index' => $index,
			'reponse' => json_encode( $response )
		];

		$this->logger->info( 'Deleted index {index} with: {reponse}', $context );
	}

	/**
	 * @since 3.0
	 *
	 * @param array $params
	 */
	public function putSettings( array $params ) {
		$this->client->indices()->putSettings( $params );
	}

	/**
	 * @since 3.0
	 *
	 * @param array $params
	 */
	public function putMapping( array $params ) {
		$this->client->indices()->putMapping( $params );
	}

	/**
	 * @since 3.0
	 *
	 * @param array $params
	 */
	public function getSettings( array $params ) {
		return $this->client->indices()->getSettings( $params );
	}

	/**
	 * @since 3.0
	 *
	 * @param array $params
	 */
	public function refresh( array $params ) {
		$this->client->indices()->refresh( [ 'index' => $params['index'] ] );
	}

	/**
	 * @since 3.0
	 *
	 * @param array $params
	 */
	public function validate( array $params ) {

		if ( $params === [] ) {
			return [];
		}

		$results = [];
		$context = [
			'method' => __METHOD__,
			'role' => 'production',
			'index' => $params['index']
		];

		unset( $params['body']['sort'] );
		unset( $params['body']['_source'] );
		unset( $params['body']['profile'] );
		unset( $params['body']['from'] );
		unset( $params['body']['size'] );

		try {
			$results = $this->client->indices()->validateQuery( $params );
		} catch ( Exception $e ) {
			$context['exception'] = $e->getMessage();
			$this->logger->info( 'Failed the validate with: {exception}', $context );
		}

		return $results;
	}

	/**
	 * @see Client::ping
	 * @since 3.0
	 *
	 * @return boolean
	 */
	public function ping() {
		return $this->client->ping( [] );
	}

	/**
	 * @see Client::exists
	 * @since 3.0
	 *
	 * @param array $params
	 *
	 * @return boolean
	 */
	public function exists( array $params ) {
		return $this->client->exists( $params );
	}

	/**
	 * @see Client::get
	 * @since 3.0
	 *
	 * @param array $params
	 *
	 * @return mixed
	 */
	public function get( array $params ) {
		return $this->client->get( $params );
	}

	/**
	 * @see Client::delete
	 * @since 3.0
	 *
	 * @param array $params
	 *
	 * @return mixed
	 */
	public function delete( array $params ) {
		return $this->client->delete( $params );
	}

	/**
	 * @see Client::update
	 * @since 3.0
	 *
	 * @param array $params
	 *
	 * @return mixed
	 */
	public function update( array $params ) {

		$context = [
			'method' => __METHOD__,
			'role' => 'production',
			'index' => $params['index'],
			'id' => $params['id']
		];

		try {
			$context['response'] = $this->client->update( $params );
		} catch( Exception $e ) {
			$context['exception'] = $e->getMessage();
			$this->logger->info( 'Updated failed for document {id} with: {exception}, DOC: {doc}', $context );
		}
	}

	/**
	 * @see Client::index
	 * @since 3.0
	 *
	 * @param array $params
	 *
	 * @return mixed
	 */
	public function index( array $params ) {

		$context = [
			'method' => __METHOD__,
			'role' => 'production',
			'index' => $params['index'],
			'id' => $params['id']
		];

		try {
			$context['response'] = $this->client->index( $params );
		} catch( Exception $e ) {
			$context['exception'] = $e->getMessage();
			$this->logger->info( 'Index failed for document {id} with: {exception}', $context );
		}
	}

	/**
	 * @see Client::index
	 * @since 3.0
	 *
	 * @param array $params
	 */
	public function bulk( array $params ) {

		if ( $params === [] ) {
			return;
		}

		$context = [
			'method' => __METHOD__,
			'role' => 'developer'
		];

		if ( $this->inTest ) {
			$params = $params + [ 'refresh' => true ];
		}

		try {
			$response = $this->client->bulk( $params );

			// No errors, just log the head otherwise show the entire
			// response
			if ( $response['errors'] === false ) {
				unset( $response['items'] );
			}

			$context['response'] = json_encode( $response );
		} catch( Exception $e ) {
			$context['response'] = $e->getMessage();
		}

		$this->logger->info( 'Bulk update: {response}', $context );
	}

	/**
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-count.html
	 * @see Client::count
	 * @since 3.0
	 *
	 * @param array $params
	 *
	 * @return mixed
	 */
	public function count( array $params ) {

		if ( $params === [] ) {
			return [];
		}

		$results = [];
		$context = [
			'method' => __METHOD__,
			'role' => 'production',
			'index' => $params['index'],
			'query' => json_encode( $params )
		];

		$this->logger->info( 'COUNT: {query}', $context );

		// https://discuss.elastic.co/t/es-5-2-refresh-interval-doesnt-work-if-set-to-0/79248/2
		// Make sure the replication/index lag doesn't hinder the search
		if ( $this->inTest ) {
			$this->client->indices()->refresh( [ 'index' => $params['index'] ] );
		}

		// ... "_source", "from", "profile", "query", "size", "sort" are not valid parameters.
		unset( $params['body']['sort'] );
		unset( $params['body']['_source'] );
		unset( $params['body']['profile'] );
		unset( $params['body']['from'] );
		unset( $params['body']['size'] );

		try {
			$results = $this->client->count( $params );
		} catch ( Exception $e ) {
			$context['exception'] = $e->getMessage();
			$this->logger->info( 'Failed the count with: {exception}', $context );
		}

		return $results;
	}

	/**
	 * @see Client::search
	 * @since 3.0
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	public function search( array $params ) {

		if ( $params === [] ) {
			return [];
		}

		$results = [];
		$errors = [];

		$context = [
			'method' => __METHOD__,
			'role' => 'production',
			'index' => $params['index'],
			'query' => json_encode( $params )
		];

		$this->logger->info( 'SEARCH: {query}', $context );

		// https://discuss.elastic.co/t/es-5-2-refresh-interval-doesnt-work-if-set-to-0/79248/2
		// Make sure the replication/index lag doesn't hinder the search
		if ( $this->inTest ) {
			$this->client->indices()->refresh( [ 'index' => $params['index'] ] );
		}

		try {
			$results = $this->client->search( $params );
		} catch ( NoNodesAvailableException $e ) {
			$errors[] = 'Elasticsearch endpoint returned with "' . $e->getMessage() . '" .';
		} catch ( Exception $e ) {
			$context['exception'] = $e->getMessage();
			$this->logger->info( 'Failed the search with: {exception}', $context );
		}

		return [ $results, $errors ];
	}

	/**
	 * @see Client::explain
	 * @since 3.0
	 *
	 * @param array $params
	 *
	 * @return mixed
	 */
	public function explain( array $params ) {

		if ( $params === [] ) {
			return [];
		}

		// https://discuss.elastic.co/t/es-5-2-refresh-interval-doesnt-work-if-set-to-0/79248/2
		// Make sure the replication/index lag doesn't hinder the search
		if ( $this->inTest ) {
			$this->client->indices()->refresh( [ 'index' => $params['index'] ] );
		}

		return $this->client->explain( $params );
	}

}
