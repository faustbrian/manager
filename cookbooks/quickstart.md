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
