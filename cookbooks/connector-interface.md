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

class FedexConnector implements ConnectorInterface
{
    public function connect(array $config): object
    {
        $this->validateConfig($config);

        return new FedexClient(
            apiKey: $config['api_key'],
            apiSecret: $config['api_secret'],
            sandbox: $config['sandbox'] ?? false,
        );
    }

    private function validateConfig(array $config): void
    {
        if (!isset($config['api_key'])) {
            throw new InvalidArgumentException('FedEx API key is required');
        }

        if (!isset($config['api_secret'])) {
            throw new InvalidArgumentException('FedEx API secret is required');
        }
    }
}
```

### Advanced Example with Dependencies

```php
use Cline\Manager\ConnectorInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;

class UpsConnector implements ConnectorInterface
{
    public function __construct(
        private readonly Cache $cache,
        private readonly LoggerInterface $logger,
    ) {}

    public function connect(array $config): object
    {
        $this->validateConfig($config);

        $client = new UpsClient(
            username: $config['username'],
            password: $config['password'],
            accessKey: $config['access_key'],
            mode: $config['mode'] ?? 'production',
        );

        // Add caching layer
        if ($config['cache_enabled'] ?? true) {
            $client = new CachedUpsClient($client, $this->cache);
        }

        // Add logging
        if ($config['logging_enabled'] ?? true) {
            $client = new LoggedUpsClient($client, $this->logger);
        }

        return $client;
    }

    private function validateConfig(array $config): void
    {
        $required = ['username', 'password', 'access_key'];

        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new InvalidArgumentException("UPS {$key} is required");
            }
        }

        if (isset($config['mode']) && !in_array($config['mode'], ['sandbox', 'production'])) {
            throw new InvalidArgumentException('UPS mode must be sandbox or production');
        }
    }
}
```

## Using Connectors with Managers

### Factory Pattern Integration

```php
use Cline\Manager\AbstractManager;

class BookingServiceManager extends AbstractManager
{
    public function __construct(
        Repository $config,
        private readonly FedexConnector $fedexConnector,
        private readonly UpsConnector $upsConnector,
    ) {
        parent::__construct($config);
    }

    protected function createConnection(array $config): object
    {
        $driver = $config['driver'] ?? throw new InvalidArgumentException('Driver not specified');

        return match ($driver) {
            'fedex' => $this->fedexConnector->connect($config),
            'ups' => $this->upsConnector->connect($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported"),
        };
    }

    protected function getConfigName(): string
    {
        return 'booking';
    }
}
```

### Without Direct Connector Classes

You can also implement the connector pattern inline:

```php
class BookingServiceManager extends AbstractManager
{
    protected function createConnection(array $config): object
    {
        $driver = $config['driver'] ?? throw new InvalidArgumentException('Driver not specified');

        return match ($driver) {
            'fedex' => $this->connectFedex($config),
            'ups' => $this->connectUps($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported"),
        };
    }

    private function connectFedex(array $config): FedexClient
    {
        if (!isset($config['api_key'], $config['api_secret'])) {
            throw new InvalidArgumentException('FedEx credentials required');
        }

        return new FedexClient(
            apiKey: $config['api_key'],
            apiSecret: $config['api_secret'],
            sandbox: $config['sandbox'] ?? false,
        );
    }

    private function connectUps(array $config): UpsClient
    {
        if (!isset($config['username'], $config['password'], $config['access_key'])) {
            throw new InvalidArgumentException('UPS credentials required');
        }

        return new UpsClient(
            username: $config['username'],
            password: $config['password'],
            accessKey: $config['access_key'],
            mode: $config['mode'] ?? 'production',
        );
    }

    protected function getConfigName(): string
    {
        return 'booking';
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

### FedEx Configuration

```php
'fedex' => [
    'driver' => 'fedex',
    'api_key' => env('FEDEX_API_KEY'),
    'api_secret' => env('FEDEX_API_SECRET'),
    'sandbox' => env('FEDEX_SANDBOX', true),
],
```

### UPS Configuration

```php
'ups' => [
    'driver' => 'ups',
    'username' => env('UPS_USERNAME'),
    'password' => env('UPS_PASSWORD'),
    'access_key' => env('UPS_ACCESS_KEY'),
    'mode' => env('UPS_MODE', 'production'),
    'cache_enabled' => true,
    'logging_enabled' => true,
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
