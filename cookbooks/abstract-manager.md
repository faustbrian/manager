# AbstractManager Pattern

The `AbstractManager` class provides a foundation for managing multiple connections with support for dynamic connection creation, extension registration, and configuration management.

## Core Concepts

### Connection Lifecycle

The manager handles three key operations for connections:

1. **connection()** - Get or create a cached connection
2. **reconnect()** - Force recreation of an existing connection
3. **disconnect()** - Remove a connection from the cache

### Configuration Structure

The manager expects configuration in this format:

```php
[
    'default' => 'redis',
    'connections' => [
        'redis' => [
            'driver' => \App\Infrastructure\Cache\Driver\RedisCache::class,
            'config' => [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'database' => env('REDIS_CACHE_DB', 1),
            ],
        ],
        'memcached' => [
            'driver' => \App\Infrastructure\Cache\Driver\MemcachedCache::class,
            'config' => [
                'servers' => [
                    ['host' => env('MEMCACHED_HOST', '127.0.0.1'), 'port' => 11211],
                ],
            ],
        ],
    ],
]
```

## Creating a Manager

### 1. Extend AbstractManager

```php
use Cline\Manager\AbstractManager;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class CacheManager extends AbstractManager
{
    public function __construct(
        private readonly CacheFactory $factory,
    ) {}

    protected function createConnection(array $config): object
    {
        return $this->factory->make($config);
    }

    protected function getConfigName(): string
    {
        return 'cache';
    }
}
```

### 2. Create the Factory

The factory handles driver instantiation with minimal logic:

```php
use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;

#[Singleton]
class CacheFactory
{
    public function make(array $config): object
    {
        if (\array_key_exists('driver', $config)) {
            return \Illuminate\Support\Facades\App::make(
                $config['driver'],
                $config['config'] ?? []
            );
        }

        throw new InvalidArgumentException('The factory requires a driver.');
    }
}
```

### 3. Configuration Structure

Update your configuration to specify driver classes:

```php
return [
    'default' => 'redis',
    'connections' => [
        'redis' => [
            'driver' => \App\Infrastructure\Cache\Driver\RedisCache::class,
            'config' => [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'database' => env('REDIS_CACHE_DB', 1),
            ],
        ],
        'memcached' => [
            'driver' => \App\Infrastructure\Cache\Driver\MemcachedCache::class,
            'config' => [
                'servers' => [
                    ['host' => env('MEMCACHED_HOST', '127.0.0.1'), 'port' => 11211],
                ],
            ],
        ],
    ],
];
```

### 4. Required Methods

#### `createConnection(array $config): object`

Delegates to the factory to create the connection object. This is called when:
- A connection is requested for the first time
- A connection is being reconnected
- No extension resolver matches the connection name or driver

The `$config` array includes all configuration from the specific connection plus a `name` key containing the connection name.

#### `getConfigName(): string`

Returns the configuration key prefix. For example, if this returns `'cache'`, the manager will read from `config('cache.default')` and `config('cache.connections')`.

## Using the Manager

### Basic Usage

```php
$manager = new CacheManager($factory);

// Get default connection
$cache = $manager->connection();

// Get named connection
$redis = $manager->connection('redis');
$memcached = $manager->connection('memcached');

// Reconnect (forces fresh instance)
$redis = $manager->reconnect('redis');

// Disconnect (removes from cache)
$manager->disconnect('redis');
```

### Magic Method Delegation

The manager proxies method calls to the default connection:

```php
// These are equivalent:
$manager->connection()->get('user:123');
$manager->get('user:123');
```

### Connection Management

```php
// Get all active connections
$connections = $manager->getConnections();

// Get connection configuration
$config = $manager->getConnectionConfig('redis');

// Change default connection
$manager->setDefaultConnection('memcached');
$default = $manager->getDefaultConnection(); // 'memcached'
```

## Extension System

Register custom drivers dynamically:

```php
// Register by driver name
$manager->extend('custom', function (array $config) {
    return new CustomCacheDriver($config);
});

// Register by connection name
$manager->extend('special', function (array $config) {
    return new SpecialCacheDriver($config);
});

// Use closure binding to access manager internals
$manager->extend('advanced', function (array $config) {
    // $this refers to the manager instance
    $otherConnection = $this->connection('redis');
    return new AdvancedCacheDriver($config, $otherConnection);
});
```

### Extension Resolution Order

When creating a connection, the manager checks in this order:

1. Extension registered with the connection name
2. Extension registered with the driver name from config
3. Falls back to `createConnection()` method

## Configuration Access

The manager uses Laravel's static Config facade internally:

```php
use Illuminate\Support\Facades\Config;

$value = Config::get('cache.some.setting');
```

## Error Handling

The manager throws `InvalidArgumentException` when:

- Default connection is not a string
- Connection configuration is not found
- Connection configuration is not an array
- Driver is not supported in `createConnection()`
- Extension resolver returns non-object value

## Best Practices

1. **Validate configuration in createConnection()** - Check required keys exist
2. **Use specific return types** - Don't return generic `object`, be specific
3. **Document supported drivers** - Make it clear which drivers are available
4. **Handle missing drivers gracefully** - Provide clear error messages
5. **Keep connections stateless when possible** - Easier to reconnect/disconnect
6. **Use extensions for runtime customization** - Don't hardcode all drivers

## Testing

See `tests/AbstractManagerTest.php` for comprehensive examples covering:

- Connection creation and caching
- Reconnection behavior
- Default connection handling
- Extension registration (by name and driver)
- Magic method delegation
- Error scenarios
- Configuration validation
