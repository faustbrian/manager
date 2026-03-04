Manager provides an abstract pattern for managing multiple connections or drivers in PHP applications, commonly used for databases, caches, queues, and external services.

## Installation

```bash
composer require cline/manager
```

## Basic Usage

```php
use Cline\Manager\AbstractManager;

class CacheManager extends AbstractManager
{
    protected function createRedisConnector(): CacheInterface
    {
        return new RedisCache($this->config['redis']);
    }

    protected function createMemcachedConnector(): CacheInterface
    {
        return new MemcachedCache($this->config['memcached']);
    }
}

// Usage
$manager = new CacheManager($config);
$cache = $manager->connection('redis');
$cache->set('key', 'value');
```

## Concepts

### Manager
The manager maintains a pool of connections and provides access to them by name. It handles instantiation and caching of connections.

### Connector
A connector creates a specific type of connection. Each driver (redis, memcached, etc.) has its own connector.

### Connection
The actual connection instance that does the work. Created by connectors and cached by the manager.

## Configuration

```php
$config = [
    'default' => 'redis',
    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
        ],
        'memcached' => [
            'driver' => 'memcached',
            'servers' => ['127.0.0.1:11211'],
        ],
    ],
];

$manager = new CacheManager($config);
```

## Next Steps

- [Abstract Manager](./abstract-manager.md) - Implement your own manager
- [Connector Interface](./connector-interface.md) - Create custom connectors
- [Manager Interface](./manager-interface.md) - Manager API reference
