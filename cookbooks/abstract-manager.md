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
    'default' => 'main',
    'connections' => [
        'main' => [
            'driver' => 'example',
            // ... driver-specific config
        ],
        'backup' => [
            'driver' => 'example',
            // ... driver-specific config
        ],
    ],
]
```

## Creating a Manager

### 1. Extend AbstractManager

```php
use Cline\Manager\AbstractManager;

class BookingServiceManager extends AbstractManager
{
    protected function createConnection(array $config): object
    {
        $driver = $config['driver'] ?? throw new InvalidArgumentException('Driver not specified');

        return match ($driver) {
            'fedex' => new FedexBookingService($config),
            'ups' => new UpsBookingService($config),
            default => throw new InvalidArgumentException("Driver [{$driver}] not supported"),
        };
    }

    protected function getConfigName(): string
    {
        return 'booking';
    }
}
```

### 2. Required Methods

#### `createConnection(array $config): object`

Creates the actual connection object. This is called when:
- A connection is requested for the first time
- A connection is being reconnected
- No extension resolver matches the connection name or driver

The `$config` array includes all configuration from the specific connection plus a `name` key containing the connection name.

#### `getConfigName(): string`

Returns the configuration key prefix. For example, if this returns `'booking'`, the manager will read from `config('booking.default')` and `config('booking.connections')`.

## Using the Manager

### Basic Usage

```php
use Illuminate\Contracts\Config\Repository;

$manager = new BookingServiceManager($config);

// Get default connection
$service = $manager->connection();

// Get named connection
$fedex = $manager->connection('fedex');
$ups = $manager->connection('ups');

// Reconnect (forces fresh instance)
$fedex = $manager->reconnect('fedex');

// Disconnect (removes from cache)
$manager->disconnect('fedex');
```

### Magic Method Delegation

The manager proxies method calls to the default connection:

```php
// These are equivalent:
$manager->connection()->createShipment($data);
$manager->createShipment($data);
```

### Connection Management

```php
// Get all active connections
$connections = $manager->getConnections();

// Get connection configuration
$config = $manager->getConnectionConfig('fedex');

// Change default connection
$manager->setDefaultConnection('ups');
$default = $manager->getDefaultConnection(); // 'ups'
```

## Extension System

Register custom drivers dynamically:

```php
// Register by driver name
$manager->extend('custom', function (array $config) {
    return new CustomBookingService($config);
});

// Register by connection name
$manager->extend('special', function (array $config) {
    return new SpecialBookingService($config);
});

// Use closure binding to access manager internals
$manager->extend('advanced', function (array $config) {
    // $this refers to the manager instance
    $otherConnection = $this->connection('other');
    return new AdvancedService($config, $otherConnection);
});
```

### Extension Resolution Order

When creating a connection, the manager checks in this order:

1. Extension registered with the connection name
2. Extension registered with the driver name from config
3. Falls back to `createConnection()` method

## Configuration Access

The manager provides direct access to the configuration repository:

```php
$config = $manager->getConfig();
$value = $config->get('booking.some.setting');
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
