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
