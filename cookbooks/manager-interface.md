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
