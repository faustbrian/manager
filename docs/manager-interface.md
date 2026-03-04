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
