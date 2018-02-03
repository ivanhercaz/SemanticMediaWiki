# ElasticStore

- Elasticsearch (aka ES): Recommended 6.1+, Tested with 5.6.6
- Semantic MediaWiki: 3.0+
- `elasticsearch/elasticsearch` (PHP ^7.0 `~6.0` or PHP ^5.6.6 `~5.3`)

No other MediaWiki extension (e.g. `CirrusSearch`) is required. We only rely on [elasticsearch php-api][es:php-api] (maintained by ES itself) as means to establish a connection to an Elasticsearch cluster.

## Objective

The objective for this prototype is to instantaneously replicate objects called `SemanticData` to an Elasticsearch cluster during the storage of an article and allow ES to answer `#ask` queries instead of the default `SQLStore`. `#ask` queries are system agnostic therefore queries that worked with the `SQLStore` (or `SPARQLStore`) should work equally without having to learn any new syntax or modify existing queries.

A customized serialization format allows the transformation from the SQL backend to an ES specific format and hereby enables us to express a `#ask` query using the ES Query [DSL][es:dsl] and retrieve answers from Elasticsearch.

<img align="right" src="https://user-images.githubusercontent.com/1245473/35776102-85b978ee-09d9-11e8-84f6-ba9d070b2322.png" alt="...">

### Why Elasticsearch?

- It it is relatively easy to install and run an ES instance.
- ES allows to scale its cluster horizontally without requiring changes to Semantic MediaWiki or its query execution.
- It is more likely that a user can provided access to an ES instance than to a `SPARQL` triple store (or for that matter a SORL/Lucence backend) given that MediaWiki provides other extensions that make use of ES.

## Features and caveats

- Can handle property type changes without the need to re-index the entire index itself after it is ensured that all `ChangePropagation` jobs have been processed
- Inverse queries are supported `[[-Foo::Bar]]`
- Property paths are supported `[[Foo.Bar::Foobar]]`
- Catgeory and property hierarchies

It is not expected that ES returns highlighted text snippets or any other data object besides document IDs that match a query condition.

## Usage

Allowing Elasticsearch to act as drop-in replacement for the query answering some settings and actions are required:

- Set `$GLOBALS['smwgDefaultStore'] = 'SMWElasticStore';`
- Set `$GLOBALS['smwgElasticsearchEndpoints'] = [ ... ];`
- Rebuild the index using `php rebuildElasticIndex.php`

Please also consult the [elasticsearch][es:conf] manual to understand ES specific settings.

### Indexing, updates, and refresh intervals

Updates to an ES index happens instantaneously during the save of a page to guarantee that queries can use the latest available data set with the standard operation mode set to be `safe.replication` and entails that if for some reason __no__ connection could be established to an ES cluster during a page storage, a `SMW\ElasticNoNodesAvailableRecoveryJob` is scheduled. Those jobs should be executed on a regular basis to ensure that replicated data are kept in sync with the backend.

The [`refresh_interval`][es:indexing:speed] dictates how often Elasticsearch is to create new segments and is set to `1s` as default. During the rebuild process the setting is changed to `-1` as recommended by the [documentation][es:indexing:speed]. When checking the status of an index and if for some reason the `refresh_interval` remained at `-1`, changes to the index will not be visible until a refresh has been commanded therefore in those cases it is recommended to:

- Run `php rebuildElasticIndex.php --update-settings`
- Run `php rebuildElasticIndex.php --force-refresh`

### Settings and statistics

To make it easier for administrators to monitor the interface between Semantic MediaWiki and Elasticsearch, the following service links (and hereby functions) are provided for a better and quicker access to relevant information:

- `Special:SemanticMediaWiki/elastic`
- `Special:SemanticMediaWiki/elastic/settings`
- `Special:SemanticMediaWiki/elastic/indices`
- `Special:SemanticMediaWiki/elastic/statistics`

It should be noted that only users with the `smw-admin` right (which is required to access `Special:SemanticMediaWiki` page) can access the information.

## Configuration

Semantic MediaWiki provides configuration settings to help customize connection details and alter characteristics used during the query answering.

### Endpoints

This setting contains a list of available endpoints used by the ES cluster. Please consult the [reference material][es:conf:hosts] for details about which notations are valid.

```
$GLOBALS['smwgElasticsearchEndpoints'] = [
	[ 'host' => '192.168.1.126', 'port' => 9200, 'scheme' => 'http' ],
	'localhost:9200'
];
```

### Settings

The `$smwgElasticsearchConfig` compound setting covers:

- `index` points to files that contain index and field mappings (should not be altered by a user)
- `connection` defines connection details for ES endpoints
- `settings` can be used to modify ES specific settings
- `query` contains a list of settings directed towards query optimization and execution in connection with Semantic MediaWiki

```
$GLOBALS['smwgElasticsearchConfig'] = [
	'index' => [
		'data' => '...',
		'lookup' => '...',
	],
	'connection' =>[
		'retries' => 2
	],
	'settings' => [
		...
	],
	'query' => [
		...
	]
];

```

A detailed list of settings and their explanations are available in `DefaultSettings.php`. Please make sure that after changing any setting, to run `php rebuildElasticIndex.php --update-settings`.

#### Shards and replicas

The default shards and replica configuration is specified with:

- Index `data` has two primary shards and two replicas
- Index `lookup` has one primary shard and no replica with the documentation noting that "... consider using an index with a single shard ... lookup terms filter will prefer to execute the get request on a local node if possible ..."

In case shards and replicas are required to be altered the `$smwgElasticsearchConfig` setting can set related variables but any change to the `number_of_shards` requires the entire index to be rebuild.

```
$GLOBALS['smwgElasticsearchConfig']['settings']['data'] = [
	'number_of_shards' => 3,
	'number_of_replicas' => 3
];
```
#### Property chains, paths, and subqueries

ES doesn't support [subqueries][es:subqueries] or [joins][es:joins] but in order to execute a path or chain of matchable properties it is necessary to create a set of results that match a path condition (e.g. `Foo.bar.foobar`) with each element holding a restricted list of results from the previous path element to allow for a traversal along the path.

Semantic MediaWiki has to split the path and execute each part individually to provide a list of elements as input for the next iteration. To avoid issues with possible large results sets, `subquery.terms.lookup.index.write.threshold` (default is 100) defines as to when to make use of the ES [terms lookup][es:terms-lookup] feature by "parking" results in a separate `lookup` index.

## Technical notes

All classes and objects related to the Elasticsearch binding are isolated and placed under the `SMW\Elastic` namespace.

```
Elastic
├ Connection    # Responsible for building a connection to ES
├ Indexer       # Contains all necessary classes for updating the ES index
└ QueryEngine   # Hosts the query builder and the `#ask` language interpreter classes
  ElasticFactory
  ElasticStore
```

### Indices, mappings, and serialization

[Create index][es:create:index] help page has details about the index creation in ES, Semantic MediaWiki provides two index types, the first being `data` that hosts all indexable data and `lookup` to store lookup queries used for concept, property path, and inverse match computations.

A default [mapping][es:mapping] is provided by Semantic MediaWiki building upon [`dynamic_templates`][es:dynamic:templates] to define the expected data types, their attributes, and possibly extra index fields (see [multi-fields][es:multi-fields]).

New fields (aka properties) will be mapped dynamically to the predefined field type.

The naming convention follows a very pragmatic naming scheme, `P:<ID>.<type>Field` represents a property with the ID part being the same as the `smw_id` found in the `SQLStore`.

![image](https://user-images.githubusercontent.com/1245473/35778113-cc6b2710-09fc-11e8-89ce-ab2c4841fa36.png)

- `P:<ID>` identifies the property with the number being the same as the internal ID in the `SQLStore`
- `<type>Field` declares a typed field (e.g. `txtField` which is important in case the type changes from `wpg` to `txt` and vice versa) and holds the actual indexable data.
- Dates are indexed using the julian day number (JDN) otherwise matching historic dates is unattainable due to a limited date format in ES

### Logging

The enable connector specific logging, please use `smw-elastic` in your LocalSettings.

```
$wgDebugLogGroups  = [
	'smw-elastic' => ".../logs/smw-elastic-{$wgDBname}.log",
];
```

### Notes

Why not combine the `SQLStore` and ES search where ES only handles the text search? The need to support ordering of results requires that the ordering happens over the entire set of conditions and it is not possible to split a search between two systems while retaining consistency for the offset (from where result starts and end) pointer.

Why not use ES as complete replacement? Because ES is a search engine and not a storage backend therefore the master data remain as part of the `SQLStore`.

Given above arguments, the `SQLStore` remains and is responsible to manage the creation of IDs, the storage of data objects, and provide answers to requested like (e.g `Store::getPropertyValues` etc.).

## Glossary

- `Document` is called in ES a content container to holds indexable content and is equivalent to an entity (subject) in Semantic MediaWiki
- `Index` holds all documents within a collection of types and contains inverted indices to search across everything within those documents at once

## FAQ

> IllegalArgumentException: Limit of total fields [3000] in index [...] has been exceeded

If the rebuilder or ES returns with a similar message then the preconfigured limit needs to be changed which is most likely caused by an excessive use of property declarations. The user should question such usage patterns and analyze why so many properties are used and whether or not some can
be merged or properties are in fact misused as fact statements.

The limit is set to prevent [mapping explosion][es:map:explosion] but can be readjusted using:

- [index.mapping.total_fields.limit][es:mapping] maximum number of fields in an index

```
$GLOBALS['smwgElasticsearchConfig']['settings']['data'] = [
	'index.mapping.total_fields.limit' => 6000
];
```

After changing any setting, ensure to run `php rebuildElasticIndex.php --update-settings`.

> Your version of PHP / json-ext does not support the constant 'JSON_PRESERVE_ZERO_FRACTION', which is important for proper type mapping in Elasticsearch. Please upgrade your PHP or json-ext.

See [elasticsearch-php#534](https://github.com/elastic/elasticsearch-php/issues/534) and requires:

- ES 6.1 with `elasticsearch/elasticsearch` `~6.0` and PHP 7.0+
- ES 5.6 with `elasticsearch/elasticsearch` `~5.3` and at least PHP 5.6.6+

## Recommendations

- Analysis ICU ( tokenizer and token filters from the Unicode ICU library), see `bin/elasticsearch-plugin install analysis-icu`
- A [curated list](https://github.com/dzharii/awesome-elasticsearch) of useful resources about elasticsearch including articles, videos, blogs, tips and tricks, use cases
- [Elasticsearch: The Definitive Guide](http://oreilly.com/catalog/errata.csp?isbn=9781449358549) by Clinton Gormley and Zachary Tonge should provide insights in how to run and use Elasticsearch

[es:conf]: https://www.elastic.co/guide/en/elasticsearch/reference/6.1/system-config.html
[es:conf:hosts]: https://www.elastic.co/guide/en/elasticsearch/client/php-api/6.0/_configuration.html#_extended_host_configuration
[es:php-api]: https://www.elastic.co/guide/en/elasticsearch/client/php-api/6.0/_installation_2.html
[es:joins]: https://github.com/elastic/elasticsearch/issues/6769
[es:subqueries]: https://discuss.elastic.co/t/question-about-subqueries/20767/2
[es:terms-lookup]: https://www.elastic.co/blog/terms-filter-lookup
[es:dsl]: https://www.elastic.co/guide/en/elasticsearch/reference/6.1/query-dsl.html
[es:mapping]: https://www.elastic.co/guide/en/elasticsearch/reference/6.1/mapping.html
[es:multi-fields]: https://www.elastic.co/guide/en/elasticsearch/reference/6.1/multi-fields.html
[es:map:explosion]: https://www.elastic.co/blog/found-crash-elasticsearch#mapping-explosion
[es:indexing:speed]: https://www.elastic.co/guide/en/elasticsearch/reference/current/tune-for-indexing-speed.html
[es:create:index]: https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html
[es:dynamic:templates]: https://www.elastic.co/guide/en/elasticsearch/reference/6.1/dynamic-templates.html
