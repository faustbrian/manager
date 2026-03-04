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
