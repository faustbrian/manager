## Table of Contents

1. [Quickstart Guide](#doc-cookbooks-quickstart) (`cookbooks/quickstart.md`)
2. [AbstractManager Pattern](#doc-cookbooks-abstract-manager) (`cookbooks/abstract-manager.md`)
3. [ManagerInterface Pattern](#doc-cookbooks-manager-interface) (`cookbooks/manager-interface.md`)
4. [ConnectorInterface Pattern](#doc-cookbooks-connector-interface) (`cookbooks/connector-interface.md`)
5. [Overview](#doc-docs-readme) (`docs/README.md`)
6. [Abstract Manager](#doc-docs-abstract-manager) (`docs/abstract-manager.md`)
7. [Connector Interface](#doc-docs-connector-interface) (`docs/connector-interface.md`)
8. [Manager Interface](#doc-docs-manager-interface) (`docs/manager-interface.md`)
<a id="doc-cookbooks-quickstart"></a>

# Quickstart Guide: Complete Manager Implementation

This guide shows a complete, production-ready implementation of the manager pattern for a cache service in a Laravel application.

## Overview

We'll build a cache manager that supports multiple cache drivers (Redis, Memcached) with:

- Manager class for connection lifecycle
- Factory classes for creating connections
- Configuration file
- Laravel facade for easy access
- Service provider for registration

## Directory Structure

```
src/Infrastructure/Cache/
├── Driver/
│   ├── CacheManager.php
│   ├── CacheFactory.php
│   ├── RedisCache.php
│   └── MemcachedCache.php
├── Facade/
│   └── Cache.php
└── CacheServiceProvider.php

config/
└── cache.php
```

## Step 1: Create the Manager

**File:** `src/Infrastructure/Cache/Driver/CacheManager.php`

```php
<?php declare(strict_types=1);

namespace App\Infrastructure\Cache\Driver;

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

## Step 2: Create the Factory

**File:** `src/Infrastructure/Cache/Driver/CacheFactory.php`

```php
<?php declare(strict_types=1);

namespace App\Infrastructure\Cache\Driver;

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

## Step 3: Create Cache Implementations

**File:** `src/Infrastructure/Cache/Driver/RedisCache.php`

```php
<?php declare(strict_types=1);

namespace App\Infrastructure\Cache\Driver;

use Redis;

class RedisCache
{
    private Redis $redis;

    public function __construct(
        string $host,
        int $port = 6379,
        int $database = 0,
        ?string $password = null,
        string $prefix = '',
    ) {
        $this->connect($host, $port, $database, $password, $prefix);
    }

    private function connect(
        string $host,
        int $port,
        int $database,
        ?string $password,
        string $prefix,
    ): void {
        $this->redis = new Redis();
        $this->redis->connect($host, $port);

        if ($password) {
            $this->redis->auth($password);
        }

        $this->redis->select($database);

        if ($prefix) {
            $this->redis->setOption(Redis::OPT_PREFIX, $prefix);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {

        $value = $this->redis->get($key);

        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {

        $serialized = serialize($value);

        if ($ttl === null) {
            return $this->redis->set($key, $serialized);
        }

        return $this->redis->setex($key, $ttl, $serialized);
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function clear(): bool
    {
        return $this->redis->flushDB();
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function increment(string $key, int $value = 1): int
    {
        return $this->redis->incrBy($key, $value);
    }

    public function decrement(string $key, int $value = 1): int
    {
        return $this->redis->decrBy($key, $value);
    }
}
```

**File:** `src/Infrastructure/Cache/Driver/MemcachedCache.php`

```php
<?php declare(strict_types=1);

namespace App\Infrastructure\Cache\Driver;

use Memcached;

class MemcachedCache
{
    private Memcached $memcached;

    public function __construct(
        array $servers,
        bool $persistent = false,
        string $prefix = '',
    ) {
        $this->connect($servers, $persistent, $prefix);
    }

    private function connect(array $servers, bool $persistent, string $prefix): void
    {
        $persistentId = $persistent ? 'app_cache' : null;
        $this->memcached = new Memcached($persistentId);

        if (!$persistent || !count($this->memcached->getServerList())) {
            foreach ($servers as $server) {
                $this->memcached->addServer(
                    $server['host'],
                    $server['port'] ?? 11211,
                    $server['weight'] ?? 0,
                );
            }
        }

        $this->memcached->setOption(Memcached::OPT_PREFIX_KEY, $prefix);
        $this->memcached->setOption(Memcached::OPT_COMPRESSION, true);
    }

    public function get(string $key, mixed $default = null): mixed
    {

        $value = $this->memcached->get($key);

        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return $default;
        }

        return $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {

        if ($ttl === null) {
            return $this->memcached->set($key, $value);
        }

        return $this->memcached->set($key, $value, time() + $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->memcached->delete($key);
    }

    public function clear(): bool
    {
        return $this->memcached->flush();
    }

    public function has(string $key): bool
    {
        $this->get($key);
        return $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND;
    }

    public function increment(string $key, int $value = 1): int|false
    {
        return $this->memcached->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->memcached->decrement($key, $value);
    }
}
```

## Step 4: Create Configuration File

**File:** `config/cache.php`

```php
<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used
    | when no specific store is requested.
    |
    */

    'default' => env('CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here are the store configurations for each supported cache driver.
    | Each store requires specific connection settings.
    |
    */

    'connections' => [
        'redis' => [
            'driver' => \App\Infrastructure\Cache\Driver\RedisCache::class,
            'config' => [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'database' => env('REDIS_CACHE_DB', 1),
                'password' => env('REDIS_PASSWORD'),
                'prefix' => env('CACHE_PREFIX', 'app_cache:'),
            ],
        ],

        'memcached' => [
            'driver' => \App\Infrastructure\Cache\Driver\MemcachedCache::class,
            'config' => [
                'persistent' => env('MEMCACHED_PERSISTENT', false),
                'prefix' => env('CACHE_PREFIX', 'app_cache:'),
                'servers' => [
                    [
                        'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                        'port' => env('MEMCACHED_PORT', 11211),
                        'weight' => 100,
                    ],
                ],
            ],
        ],

        'backup' => [
            'driver' => \App\Infrastructure\Cache\Driver\RedisCache::class,
            'config' => [
                'host' => env('REDIS_BACKUP_HOST', '127.0.0.1'),
                'port' => env('REDIS_BACKUP_PORT', 6380),
                'database' => 0,
                'password' => env('REDIS_BACKUP_PASSWORD'),
                'prefix' => 'backup:',
            ],
        ],
    ],
];
```

## Step 5: Create Facade

**File:** `src/Infrastructure/Cache/Facade/Cache.php`

```php
<?php declare(strict_types=1);

namespace App\Infrastructure\Cache\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Infrastructure\Cache\Driver\CacheManager connection(?string $name = null)
 * @method static \App\Infrastructure\Cache\Driver\CacheManager reconnect(?string $name = null)
 * @method static void disconnect(?string $name = null)
 * @method static array getConnectionConfig(?string $name = null)
 * @method static string getDefaultConnection()
 * @method static void setDefaultConnection(string $name)
 * @method static void extend(string $name, callable $resolver)
 * @method static array getConnections()
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string $key, mixed $value, ?int $ttl = null)
 * @method static bool delete(string $key)
 * @method static bool clear()
 *
 * @see \App\Infrastructure\Cache\Driver\CacheManager
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cache';
    }
}
```

## Step 6: Create Service Provider

**File:** `src/Infrastructure/Cache/CacheServiceProvider.php`

```php
<?php declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Infrastructure\Cache\Driver\CacheFactory;
use App\Infrastructure\Cache\Driver\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register facade
        $this->app->alias(CacheManager::class, 'cache');
    }

    public function boot(): void
    {
        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../../config/cache.php' => config_path('cache.php'),
            ], 'cache-config');
        }
    }
}
```

## Step 7: Register Service Provider

**File:** `config/app.php`

```php
'providers' => [
    // ... other providers
    App\Infrastructure\Cache\CacheServiceProvider::class,
],

'aliases' => [
    // ... other aliases
    'Cache' => App\Infrastructure\Cache\Facade\Cache::class,
],
```

## Usage Examples

### Basic Usage

```php
use App\Infrastructure\Cache\Facade\Cache;

// Store value (uses default connection - Redis)
Cache::set('user:123', ['name' => 'John Doe', 'email' => 'john@example.com']);

// Get value
$user = Cache::get('user:123');

// Get with default
$settings = Cache::get('settings', ['theme' => 'light']);

// Delete value
Cache::delete('user:123');

// Clear all cache
Cache::clear();
```

### Using Specific Connections

```php
// Use Memcached explicitly
Cache::connection('memcached')->set('session:abc', $data);

// Switch default at runtime
Cache::setDefaultConnection('memcached');
Cache::set('key', 'value'); // Now uses Memcached
```

### Using in Controllers

```php
namespace App\Http\Controllers;

use App\Infrastructure\Cache\Facade\Cache;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(string $id)
    {
        $cacheKey = "user:{$id}";

        // Try cache first
        $user = Cache::get($cacheKey);

        if (!$user) {
            $user = User::findOrFail($id);
            Cache::set($cacheKey, $user, 3600);
        }

        return response()->json($user);
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $user->update($request->validated());

        // Invalidate cache
        Cache::delete("user:{$id}");

        return response()->json($user);
    }
}
```

### Using in Services

```php
namespace App\Services;

use App\Infrastructure\Cache\Facade\Cache;

class RateLimiter
{
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $connection = Cache::connection('redis');

        $attempts = (int) $connection->get($key, 0);

        if ($attempts >= $maxAttempts) {
            return false;
        }

        $connection->set($key, $attempts + 1, $decaySeconds);

        return true;
    }

    public function clear(string $key): void
    {
        Cache::connection('redis')->delete($key);
    }
}
```

### Testing

```php
namespace Tests\Feature;

use App\Infrastructure\Cache\Facade\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CacheTest extends TestCase
{
    public function test_can_store_and_retrieve_values(): void
    {
        Cache::set('test_key', 'test_value');
        $value = Cache::get('test_key');

        $this->assertEquals('test_value', $value);
    }

    public function test_can_switch_connections(): void
    {
        $redis = Cache::connection('redis');
        $memcached = Cache::connection('memcached');

        $this->assertNotSame($redis, $memcached);
    }

    public function test_can_register_custom_driver(): void
    {
        Cache::extend('mock', function (array $config) {
            return new class {
                private array $data = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->data[$key] ?? $default;
                }

                public function set(string $key, mixed $value, ?int $ttl = null): bool
                {
                    $this->data[$key] = $value;
                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->data[$key]);
                    return true;
                }

                public function clear(): bool
                {
                    $this->data = [];
                    return true;
                }
            };
        });

        config(['cache.connections.mock' => ['driver' => 'mock']]);

        Cache::connection('mock')->set('key', 'value');
        $value = Cache::connection('mock')->get('key');

        $this->assertEquals('value', $value);
    }
}
```

### Environment Configuration

**File:** `.env`

```env
# Cache Configuration
CACHE_DRIVER=redis
CACHE_PREFIX=myapp:

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CACHE_DB=1
REDIS_PASSWORD=

# Redis Backup
REDIS_BACKUP_HOST=192.168.1.100
REDIS_BACKUP_PORT=6380
REDIS_BACKUP_PASSWORD=

# Memcached Configuration
MEMCACHED_HOST=127.0.0.1
MEMCACHED_PORT=11211
MEMCACHED_PERSISTENT=false
```

## Advanced Features

### Adding TTL Decorator

```php
class TtlAwareCache
{
    public function __construct(
        private readonly object $cache,
        private readonly int $defaultTtl = 3600,
    ) {}

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->cache->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->cache->set($key, $value, $ttl ?? $this->defaultTtl);

        return $value;
    }

    public function __call(string $method, array $parameters)
    {
        return $this->cache->{$method}(...$parameters);
    }
}
```

### Failover Strategy

```php
use App\Infrastructure\Cache\Facade\Cache;

class FailoverCache
{
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::connection('redis')->get($key, $default);
        } catch (\Exception $e) {
            logger()->warning('Redis failed, trying backup', ['error' => $e->getMessage()]);
            return Cache::connection('backup')->get($key, $default);
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $success = true;

        try {
            Cache::connection('redis')->set($key, $value, $ttl);
        } catch (\Exception $e) {
            logger()->error('Redis set failed', ['error' => $e->getMessage()]);
            $success = false;
        }

        try {
            Cache::connection('backup')->set($key, $value, $ttl);
        } catch (\Exception $e) {
            logger()->error('Backup set failed', ['error' => $e->getMessage()]);
            $success = false;
        }

        return $success;
    }
}
```

### Tagged Cache Implementation

```php
class TaggedCache
{
    public function __construct(
        private readonly object $cache,
        private readonly array $tags,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->taggedKey($key), $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->cache->set($this->taggedKey($key), $value, $ttl);
    }

    public function flush(): bool
    {
        $tagId = $this->cache->get($this->tagKey());
        $newTagId = uniqid('tag_', true);
        return $this->cache->set($this->tagKey(), $newTagId);
    }

    private function taggedKey(string $key): string
    {
        $tagId = $this->cache->get($this->tagKey()) ?? $this->initializeTag();
        return implode(':', array_merge($this->tags, [$tagId, $key]));
    }

    private function tagKey(): string
    {
        return 'tag:' . implode(':', $this->tags);
    }

    private function initializeTag(): string
    {
        $tagId = uniqid('tag_', true);
        $this->cache->set($this->tagKey(), $tagId);
        return $tagId;
    }
}
```

## Summary

This complete example demonstrates:

✅ Manager class extending `AbstractManager`
✅ Factory class for creating cache instances
✅ Multiple cache driver implementations (Redis, Memcached)
✅ Configuration file with multiple connections
✅ Laravel facade for easy access
✅ Service provider for registration
✅ Controller integration examples
✅ Testing examples
✅ Environment configuration
✅ Advanced patterns (TTL, failover, tagging)

The pattern provides a clean, extensible architecture for managing multiple cache connections with minimal boilerplate.

<a id="doc-cookbooks-abstract-manager"></a>

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

<a id="doc-cookbooks-manager-interface"></a>

# ManagerInterface Pattern

The `ManagerInterface` defines the contract for manager classes that handle multiple connections with lifecycle management, configuration access, and extensibility.

## Interface Contract

```php
interface ManagerInterface
{
    public function connection(?string $name = null): object;
    public function reconnect(?string $name = null): object;
    public function disconnect(?string $name = null): void;
    public function getConnectionConfig(?string $name = null): array;
    public function getDefaultConnection(): string;
    public function setDefaultConnection(string $name): void;
    public function extend(string $name, callable $resolver): void;
    public function getConnections(): array;
}
```

## Method Overview

### Connection Lifecycle

#### `connection(?string $name = null): object`

Retrieves a cached connection or creates a new one if it doesn't exist.

```php
// Get default connection
$service = $manager->connection();

// Get named connection
$redis = $manager->connection('redis');

// Connections are cached
$same = $manager->connection('redis'); // Returns same instance
```

**Behavior:**
- Uses default connection if `$name` is null
- Creates and caches connection on first access
- Returns cached instance on subsequent calls
- Throws `InvalidArgumentException` if configuration invalid

#### `reconnect(?string $name = null): object`

Forces recreation of a connection by disconnecting and reconnecting.

```php
// Reconnect default connection
$fresh = $manager->reconnect();

// Reconnect named connection
$freshRedis = $manager->reconnect('redis');
```

**Use Cases:**
- Connection has become stale or timed out
- Configuration has changed at runtime
- Need to reset connection state
- Testing scenarios requiring fresh instances

#### `disconnect(?string $name = null): void`

Removes a connection from the cache without closing it.

```php
// Disconnect default connection
$manager->disconnect();

// Disconnect named connection
$manager->disconnect('redis');

// Next call to connection() creates new instance
$new = $manager->connection('redis');
```

**Behavior:**
- Removes connection from internal cache
- Does NOT call close/cleanup on connection
- Next `connection()` call creates fresh instance
- Safe to call on non-existent connections

### Configuration Management

#### `getConnectionConfig(?string $name = null): array`

Retrieves the configuration array for a connection.

```php
// Get default connection config
$config = $manager->getConnectionConfig();

// Get named connection config
$redisConfig = $manager->getConnectionConfig('redis');

// Returns: ['name' => 'redis', 'driver' => 'redis', 'api_key' => '...', ...]
```

**Behavior:**
- Returns configuration from `connections.{name}` section
- Adds `name` key to configuration array
- Throws `InvalidArgumentException` if not found or invalid

#### `getDefaultConnection(): string`

Returns the name of the default connection.

```php
$default = $manager->getDefaultConnection(); // 'main'
```

**Behavior:**
- Reads from `{configName}.default` configuration
- Throws `InvalidArgumentException` if not a string

#### `setDefaultConnection(string $name): void`

Changes the default connection at runtime.

```php
$manager->setDefaultConnection('backup');

// Now connection() uses 'backup'
$service = $manager->connection(); // Gets 'backup'
```

**Use Cases:**
- Failover scenarios
- Testing with different configurations
- Runtime environment switching
- Feature flag implementations

### Extension System

#### `extend(string $name, callable $resolver): void`

Registers a custom connection resolver for dynamic driver registration.

```php
// Register by connection name
$manager->extend('custom', function (array $config) {
    return new CustomService($config);
});

// Register by driver name
$manager->extend('redis', function (array $config) {
    return new RedisConnection($config['host'], $config['port']);
});
```

**Resolution Order:**
1. Extension registered with connection name
2. Extension registered with driver from config
3. Manager's `createConnection()` method

**Use Cases:**
- Adding drivers without modifying manager
- Plugin systems
- Testing with mock connections
- Runtime driver registration

#### `getConnections(): array`

Returns all currently instantiated connections.

```php
$connections = $manager->getConnections();
// ['redis' => RedisCache, 'memcached' => MemcachedCache]

foreach ($connections as $name => $connection) {
    echo "Connection {$name} is active\n";
}
```

**Use Cases:**
- Monitoring active connections
- Cleanup on shutdown
- Debugging connection state
- Health checks

## Implementation Example

```php
use Cline\Manager\ManagerInterface;

class BookingServiceManager implements ManagerInterface
{
    private array $connections = [];
    private array $extensions = [];

    public function __construct(
        private readonly Repository $config,
    ) {}

    public function connection(?string $name = null): object
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!array_key_exists($name, $this->connections)) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    public function reconnect(?string $name = null): object
    {
        $name = $name ?: $this->getDefaultConnection();
        $this->disconnect($name);
        return $this->connection($name);
    }

    public function disconnect(?string $name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();
        unset($this->connections[$name]);
    }

    public function getConnectionConfig(?string $name = null): array
    {
        $name = $name ?: $this->getDefaultConnection();
        $connections = $this->config->get('booking.connections');

        if (!is_array($connections) || !isset($connections[$name])) {
            throw new InvalidArgumentException("Connection [{$name}] not configured");
        }

        $config = $connections[$name];
        $config['name'] = $name;

        return $config;
    }

    public function getDefaultConnection(): string
    {
        $default = $this->config->get('booking.default');

        if (!is_string($default)) {
            throw new InvalidArgumentException('Default connection must be a string');
        }

        return $default;
    }

    public function setDefaultConnection(string $name): void
    {
        $this->config->set('booking.default', $name);
    }

    public function extend(string $name, callable $resolver): void
    {
        $this->extensions[$name] = $resolver;
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    private function makeConnection(string $name): object
    {
        $config = $this->getConnectionConfig($name);

        // Check for extension by connection name
        if (isset($this->extensions[$name])) {
            return $this->extensions[$name]($config);
        }

        // Check for extension by driver
        $driver = $config['driver'] ?? null;
        if ($driver && isset($this->extensions[$driver])) {
            return $this->extensions[$driver]($config);
        }

        // Fall back to built-in creation
        return $this->createConnection($config);
    }

    private function createConnection(array $config): object
    {
        $driver = $config['driver'] ?? throw new InvalidArgumentException('Driver not specified');

        return match ($driver) {
            'redis' => new RedisCache($config),
            'memcached' => new MemcachedCache($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported"),
        };
    }
}
```

## Usage Patterns

### Basic Connection Management

```php
// Get connections
$default = $manager->connection();
$redis = $manager->connection('redis');
$memcached = $manager->connection('memcached');

// Check active connections
$active = $manager->getConnections();
echo count($active) . " connections active\n";

// Reconnect when needed
$manager->reconnect('redis');

// Cleanup
$manager->disconnect('memcached');
```

### Dynamic Default Switching

```php
// Start with main service
$manager->setDefaultConnection('redis');
$service = $manager->connection();

// Switch to backup on error
try {
    $service->process($data);
} catch (ServiceException $e) {
    $manager->setDefaultConnection('memcached');
    $service = $manager->connection();
    $service->process($data);
}
```

### Runtime Extension Registration

```php
// Register custom driver
$manager->extend('test', function (array $config) {
    return new MockService();
});

// Use in tests
$test = $manager->connection('test');
```

### Health Monitoring

```php
// Check all connections
foreach ($manager->getConnections() as $name => $connection) {
    if (!$connection->isHealthy()) {
        $manager->reconnect($name);
    }
}
```

## Contract Requirements

When implementing `ManagerInterface`:

1. **Connection caching** - Must cache instances and return same object
2. **Null parameter handling** - Must use default connection when null
3. **Configuration validation** - Must throw `InvalidArgumentException` on errors
4. **Extension priority** - Must check extensions before `createConnection()`
5. **Name injection** - Must add `name` key to connection config
6. **Disconnect safety** - Must handle disconnecting non-existent connections

## Best Practices

1. **Use the interface for type hints** - Depend on `ManagerInterface`, not concrete classes
2. **Handle null gracefully** - Always support null for default connection
3. **Validate configurations** - Throw clear exceptions for missing/invalid config
4. **Cache connections** - Don't create new instances on every call
5. **Support extensions** - Allow runtime driver registration
6. **Document driver requirements** - List supported drivers clearly
7. **Clean up resources** - Provide disconnect/cleanup mechanisms

## Testing

```php
public function test_connection_caching(): void
{
    $first = $manager->connection('redis');
    $second = $manager->connection('redis');

    $this->assertSame($first, $second);
}

public function test_reconnect_creates_new_instance(): void
{
    $original = $manager->connection('redis');
    $reconnected = $manager->reconnect('redis');

    $this->assertNotSame($original, $reconnected);
}

public function test_default_connection_behavior(): void
{
    $manager->setDefaultConnection('redis');

    $explicit = $manager->connection('redis');
    $implicit = $manager->connection();

    $this->assertSame($explicit, $implicit);
}
```

<a id="doc-cookbooks-connector-interface"></a>

# ConnectorInterface Pattern

The `ConnectorInterface` defines the contract for connector classes that establish connections based on configuration arrays.

## Purpose

Connectors encapsulate the logic for creating connection instances from configuration. They serve as factories that:

- Validate configuration parameters
- Initialize connection objects
- Handle connection-specific setup
- Throw clear errors for invalid configuration

## Interface Contract

```php
interface ConnectorInterface
{
    /**
     * Establish a connection.
     *
     * @param array<string, mixed> $config The connection configuration array
     *
     * @throws InvalidArgumentException When the configuration is invalid or connection fails
     *
     * @return object The established connection instance
     */
    public function connect(array $config): object;
}
```

## Implementing a Connector

### Basic Example

```php
use Cline\Manager\ConnectorInterface;
use InvalidArgumentException;

class RedisConnector implements ConnectorInterface
{
    public function connect(array $config): object
    {
        $this->validateConfig($config);

        return new RedisCache(
            host: $config['host'],
            port: $config['port'] ?? 6379,
            database: $config['database'] ?? 0,
            password: $config['password'] ?? null,
            prefix: $config['prefix'] ?? '',
        );
    }

    private function validateConfig(array $config): void
    {
        if (!isset($config['host'])) {
            throw new InvalidArgumentException('Redis host is required');
        }
    }
}
```

### Advanced Example with Dependencies

```php
use Cline\Manager\ConnectorInterface;
use Psr\Log\LoggerInterface;

class MemcachedConnector implements ConnectorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function connect(array $config): object
    {
        $this->validateConfig($config);

        $cache = new MemcachedCache(
            servers: $config['servers'],
            persistent: $config['persistent'] ?? false,
            prefix: $config['prefix'] ?? '',
        );

        // Add logging wrapper
        if ($config['logging_enabled'] ?? true) {
            $cache = new LoggedMemcachedCache($cache, $this->logger);
        }

        return $cache;
    }

    private function validateConfig(array $config): void
    {
        if (!isset($config['servers']) || !is_array($config['servers'])) {
            throw new InvalidArgumentException('Memcached servers array is required');
        }

        if (empty($config['servers'])) {
            throw new InvalidArgumentException('Memcached servers array cannot be empty');
        }
    }
}
```

## Using Connectors with Managers

### Factory Pattern Integration

```php
use Cline\Manager\AbstractManager;
use Illuminate\Container\Attributes\Singleton;

#[Singleton]
class CacheManager extends AbstractManager
{
    public function __construct(
        private readonly RedisConnector $redisConnector,
        private readonly MemcachedConnector $memcachedConnector,
    ) {}

    protected function createConnection(array $config): object
    {
        $driver = $config['driver'] ?? throw new InvalidArgumentException('Driver not specified');

        return match ($driver) {
            'redis' => $this->redisConnector->connect($config),
            'memcached' => $this->memcachedConnector->connect($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported"),
        };
    }

    protected function getConfigName(): string
    {
        return 'cache';
    }
}
```

### Without Direct Connector Classes

You can also implement the connector pattern inline:

```php
class CacheManager extends AbstractManager
{
    protected function createConnection(array $config): object
    {
        $driver = $config['driver'] ?? throw new InvalidArgumentException('Driver not specified');

        return match ($driver) {
            'redis' => $this->connectRedis($config),
            'memcached' => $this->connectMemcached($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported"),
        };
    }

    private function connectRedis(array $config): RedisCache
    {
        if (!isset($config['host'])) {
            throw new InvalidArgumentException('Redis host required');
        }

        return new RedisCache(
            host: $config['host'],
            port: $config['port'] ?? 6379,
            database: $config['database'] ?? 0,
            password: $config['password'] ?? null,
            prefix: $config['prefix'] ?? '',
        );
    }

    private function connectMemcached(array $config): MemcachedCache
    {
        if (!isset($config['servers']) || empty($config['servers'])) {
            throw new InvalidArgumentException('Memcached servers required');
        }

        return new MemcachedCache(
            servers: $config['servers'],
            persistent: $config['persistent'] ?? false,
            prefix: $config['prefix'] ?? '',
        );
    }

    protected function getConfigName(): string
    {
        return 'cache';
    }
}
```

## Benefits of the Connector Pattern

1. **Separation of Concerns** - Connection logic separate from manager logic
2. **Testability** - Easy to mock connectors in tests
3. **Reusability** - Same connector can be used in different managers
4. **Dependency Injection** - Connectors can have their own dependencies
5. **Configuration Validation** - Centralized validation logic
6. **Flexibility** - Easy to add middleware/decorators

## When to Use Separate Connector Classes

Use dedicated connector classes when:

- Connection setup is complex (multiple steps, validations)
- You need dependency injection (cache, logger, HTTP client)
- Multiple managers need the same connector
- You want to unit test connection logic separately
- You're adding middleware/decorators (caching, logging, retry logic)

Use inline connector methods when:

- Connection setup is simple (1-2 lines)
- No external dependencies needed
- Connector is used in only one manager
- Configuration validation is minimal

## Configuration Examples

### Redis Configuration

```php
'redis' => [
    'driver' => 'redis',
    'host' => env('REDIS_HOST', '127.0.0.1'),
    'port' => env('REDIS_PORT', 6379),
    'database' => env('REDIS_CACHE_DB', 1),
    'password' => env('REDIS_PASSWORD'),
    'prefix' => env('CACHE_PREFIX', 'app_cache:'),
],
```

### Memcached Configuration

```php
'memcached' => [
    'driver' => 'memcached',
    'persistent' => env('MEMCACHED_PERSISTENT', false),
    'prefix' => env('CACHE_PREFIX', 'app_cache:'),
    'logging_enabled' => true,
    'servers' => [
        [
            'host' => env('MEMCACHED_HOST', '127.0.0.1'),
            'port' => env('MEMCACHED_PORT', 11211),
            'weight' => 100,
        ],
    ],
],
```

## Best Practices

1. **Always validate required configuration** - Fail fast with clear messages
2. **Use type hints** - Specify exact return types, not just `object`
3. **Document required config keys** - In docblocks or comments
4. **Provide sensible defaults** - For optional configuration
5. **Throw InvalidArgumentException** - For configuration errors
6. **Keep connectors stateless** - Don't store connection instances
7. **Use constructor injection** - For connector dependencies

<a id="doc-docs-readme"></a>

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

- [Abstract Manager](#doc-docs-abstract-manager) - Implement your own manager
- [Connector Interface](#doc-docs-connector-interface) - Create custom connectors
- [Manager Interface](#doc-docs-manager-interface) - Manager API reference

<a id="doc-docs-abstract-manager"></a>

Implementing the AbstractManager for custom connection management.

## Creating a Manager

```php
use Cline\Manager\AbstractManager;

class DatabaseManager extends AbstractManager
{
    /**
     * Create a MySQL connection.
     */
    protected function createMysqlConnector(): ConnectionInterface
    {
        $config = $this->getConnectionConfig('mysql');

        return new MysqlConnection(
            host: $config['host'],
            port: $config['port'] ?? 3306,
            database: $config['database'],
            username: $config['username'],
            password: $config['password'],
        );
    }

    /**
     * Create a PostgreSQL connection.
     */
    protected function createPgsqlConnector(): ConnectionInterface
    {
        $config = $this->getConnectionConfig('pgsql');

        return new PgsqlConnection(
            host: $config['host'],
            port: $config['port'] ?? 5432,
            database: $config['database'],
            username: $config['username'],
            password: $config['password'],
        );
    }

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'mysql';
    }
}
```

## Using the Manager

```php
$config = [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => 'app',
            'username' => 'root',
            'password' => 'secret',
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'database' => 'analytics',
            'username' => 'postgres',
            'password' => 'secret',
        ],
    ],
];

$manager = new DatabaseManager($config);

// Get default connection
$db = $manager->connection();

// Get specific connection
$mysql = $manager->connection('mysql');
$pgsql = $manager->connection('pgsql');

// Connections are cached
$manager->connection('mysql') === $manager->connection('mysql'); // true
```

## Connection Lifecycle

```php
// Reconnect (close and reopen)
$manager->reconnect('mysql');

// Disconnect
$manager->disconnect('mysql');

// Check if connected
$manager->isConnected('mysql');

// Get all active connections
$connections = $manager->getConnections();
```

## Extending Connections

```php
// Add custom connector at runtime
$manager->extend('sqlite', function (array $config) {
    return new SqliteConnection($config['database']);
});

// Use the new driver
$sqlite = $manager->connection('sqlite');
```

## Configuration Access

```php
class DatabaseManager extends AbstractManager
{
    protected function createMysqlConnector(): ConnectionInterface
    {
        // Get full connection config
        $config = $this->getConnectionConfig('mysql');

        // Get specific config value
        $host = $this->getConnectionConfig('mysql')['host'];

        // Access raw config
        $allConfig = $this->config;

        return new MysqlConnection($config);
    }
}
```

<a id="doc-docs-connector-interface"></a>

Creating custom connectors for the Manager pattern.

## Connector Interface

```php
use Cline\Manager\Contracts\ConnectorInterface;

interface ConnectorInterface
{
    /**
     * Create a new connection instance.
     */
    public function connect(array $config): mixed;
}
```

## Implementing a Connector

```php
use Cline\Manager\Contracts\ConnectorInterface;

class RedisConnector implements ConnectorInterface
{
    public function connect(array $config): Redis
    {
        $redis = new Redis();

        $redis->connect(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 6379,
            $config['timeout'] ?? 0.0,
        );

        if (isset($config['password'])) {
            $redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $redis->select($config['database']);
        }

        return $redis;
    }
}
```

## Using Connectors with Manager

```php
class CacheManager extends AbstractManager
{
    protected array $connectors = [];

    public function __construct(array $config)
    {
        parent::__construct($config);

        // Register connectors
        $this->connectors['redis'] = new RedisConnector();
        $this->connectors['memcached'] = new MemcachedConnector();
    }

    protected function createConnection(string $name): mixed
    {
        $config = $this->getConnectionConfig($name);
        $driver = $config['driver'];

        if (!isset($this->connectors[$driver])) {
            throw new InvalidArgumentException("Driver [{$driver}] not supported.");
        }

        return $this->connectors[$driver]->connect($config);
    }
}
```

## Connector with Validation

```php
class DatabaseConnector implements ConnectorInterface
{
    public function connect(array $config): PDO
    {
        $this->validate($config);

        $dsn = $this->buildDsn($config);

        return new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $config['options'] ?? [],
        );
    }

    protected function validate(array $config): void
    {
        $required = ['host', 'database', 'username', 'password'];

        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new InvalidArgumentException("Missing required config: {$key}");
            }
        }
    }

    protected function buildDsn(array $config): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['database'],
            $config['charset'] ?? 'utf8mb4',
        );
    }
}
```

## Connector Factory

```php
class ConnectorFactory
{
    protected array $creators = [];

    public function register(string $driver, callable $creator): void
    {
        $this->creators[$driver] = $creator;
    }

    public function make(string $driver, array $config): mixed
    {
        if (!isset($this->creators[$driver])) {
            throw new InvalidArgumentException("Unknown driver: {$driver}");
        }

        return ($this->creators[$driver])($config);
    }
}

// Usage
$factory = new ConnectorFactory();
$factory->register('redis', fn($config) => (new RedisConnector())->connect($config));
$factory->register('memcached', fn($config) => (new MemcachedConnector())->connect($config));

$redis = $factory->make('redis', $config);
```

<a id="doc-docs-manager-interface"></a>

The Manager interface API reference.

## Interface Definition

```php
use Cline\Manager\Contracts\ManagerInterface;

interface ManagerInterface
{
    /**
     * Get a connection instance.
     */
    public function connection(?string $name = null): mixed;

    /**
     * Reconnect to a given connection.
     */
    public function reconnect(?string $name = null): mixed;

    /**
     * Disconnect from a given connection.
     */
    public function disconnect(?string $name = null): void;

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string;

    /**
     * Set the default connection name.
     */
    public function setDefaultConnection(string $name): void;
}
```

## Method Reference

### connection()

Get a connection instance by name, or the default connection.

```php
// Get default connection
$connection = $manager->connection();

// Get named connection
$connection = $manager->connection('secondary');

// Connections are cached - same instance returned
$a = $manager->connection('main');
$b = $manager->connection('main');
assert($a === $b); // true
```

### reconnect()

Close and reopen a connection.

```php
// Reconnect default
$manager->reconnect();

// Reconnect specific
$manager->reconnect('main');

// Returns the new connection
$fresh = $manager->reconnect('main');
```

### disconnect()

Close a connection and remove it from the cache.

```php
// Disconnect default
$manager->disconnect();

// Disconnect specific
$manager->disconnect('main');

// Next connection() call creates new instance
$manager->disconnect('main');
$new = $manager->connection('main'); // Fresh connection
```

### getDefaultConnection()

Get the name of the default connection.

```php
$default = $manager->getDefaultConnection();
// e.g., "main" or "mysql"
```

### setDefaultConnection()

Change the default connection.

```php
$manager->setDefaultConnection('secondary');

// Now connection() without args uses 'secondary'
$connection = $manager->connection(); // Returns 'secondary' connection
```

## Extended Interface

```php
interface ExtendedManagerInterface extends ManagerInterface
{
    /**
     * Get all active connections.
     */
    public function getConnections(): array;

    /**
     * Check if a connection exists.
     */
    public function hasConnection(string $name): bool;

    /**
     * Add a custom driver/connector.
     */
    public function extend(string $driver, callable $callback): void;

    /**
     * Get connection configuration.
     */
    public function getConnectionConfig(string $name): array;
}
```

## Usage Example

```php
class QueueManager extends AbstractManager implements ManagerInterface
{
    public function push(string $job, array $data = [], ?string $queue = null): void
    {
        $this->connection()->push($job, $data, $queue);
    }

    public function pop(?string $queue = null): ?Job
    {
        return $this->connection()->pop($queue);
    }

    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'sync';
    }

    protected function createSyncConnector(): QueueInterface
    {
        return new SyncQueue();
    }

    protected function createRedisConnector(): QueueInterface
    {
        return new RedisQueue($this->getConnectionConfig('redis'));
    }
}
```
