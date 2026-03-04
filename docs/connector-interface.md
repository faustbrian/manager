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
