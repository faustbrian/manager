<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Manager;

use Cline\Manager\Exceptions\ConfigurationNotArrayException;
use Cline\Manager\Exceptions\ConfigurationNotFoundException;
use Cline\Manager\Exceptions\ExtensionMustReturnObjectException;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

use function array_key_exists;
use function is_array;
use function is_object;
use function is_string;
use function throw_unless;

/**
 * This is the abstract manager class.
 *
 * Provides a foundation for managing multiple connections with support for
 * dynamic connection creation, extension registration, and configuration management.
 *
 * @author Graham Campbell <hello@gjcampbell.co.uk>
 */
abstract class AbstractManager implements ManagerInterface
{
    /**
     * The active connection instances.
     *
     * @var array<string, object>
     */
    protected array $connections = [];

    /**
     * The custom connection resolvers.
     *
     * @var array<string, Closure>
     */
    protected array $extensions = [];

    /**
     * Dynamically pass methods to the default connection.
     *
     * @param string            $method     The method name to call on the connection
     * @param array<int, mixed> $parameters The parameters to pass to the method
     *
     * @throws InvalidArgumentException When the connection cannot be established
     *
     * @return mixed The result of the method call
     */
    public function __call(string $method, array $parameters)
    {
        return $this->connection()->{$method}(...$parameters);
    }

    /**
     * Get a connection instance.
     *
     * If the connection has not been created yet, it will be instantiated and cached.
     * Subsequent calls with the same name will return the cached instance.
     *
     * @param null|string $name The connection name, or null to use the default connection
     *
     * @throws InvalidArgumentException When the connection cannot be configured or created
     *
     * @return object The connection instance
     */
    public function connection(?string $name = null): object
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!array_key_exists($name, $this->connections)) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Reconnect to the given connection.
     *
     * Disconnects the existing connection and creates a fresh instance.
     *
     * @param null|string $name The connection name, or null to use the default connection
     *
     * @throws InvalidArgumentException When the connection cannot be configured or created
     *
     * @return object The new connection instance
     */
    public function reconnect(?string $name = null): object
    {
        $name = $name ?: $this->getDefaultConnection();

        $this->disconnect($name);

        return $this->connection($name);
    }

    /**
     * Disconnect from the given connection.
     *
     * Removes the connection instance from the cache. The connection will be
     * recreated on the next call to connection() or reconnect().
     *
     * @param null|string $name The connection name, or null to use the default connection
     */
    public function disconnect(?string $name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();

        unset($this->connections[$name]);
    }

    /**
     * Get the configuration for a connection.
     *
     * @param null|string $name The connection name, or null to use the default connection
     *
     * @throws InvalidArgumentException When the connection configuration is not found or invalid
     *
     * @return array<string, mixed> The connection configuration array
     */
    public function getConnectionConfig(?string $name = null): array
    {
        $name = $name ?: $this->getDefaultConnection();

        return $this->getNamedConfig('connections', 'Connection', $name);
    }

    /**
     * Get the default connection name.
     *
     * @throws InvalidArgumentException When the default connection is not configured as a string
     *
     * @return string The default connection name from configuration
     */
    public function getDefaultConnection(): string
    {
        $default = Config::get($this->getConfigName().'.default');

        throw_unless(is_string($default), InvalidArgumentException::class, 'Default connection must be a string');

        return $default;
    }

    /**
     * Set the default connection name.
     *
     * @param string $name The connection name to set as default
     */
    public function setDefaultConnection(string $name): void
    {
        Config::set($this->getConfigName().'.default', $name);
    }

    /**
     * Register an extension connection resolver.
     *
     * Extensions allow custom connection drivers to be registered. The resolver
     * will be called with the connection configuration and must return an object.
     *
     * @param string  $name     The extension name or driver name
     * @param Closure $resolver The resolver function that creates the connection
     */
    public function extend(string $name, Closure $resolver): void
    {
        $this->extensions[$name] = $resolver->bindTo($this, $this) ?? $resolver;
    }

    /**
     * Return all of the created connections.
     *
     * @return array<string, object>
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Make the connection instance.
     *
     * Attempts to resolve the connection using extensions first, then falls back
     * to the createConnection method. Checks both the connection name and driver
     * for registered extensions.
     *
     * @param string $name The connection name
     *
     * @throws InvalidArgumentException When the connection cannot be created or extension returns non-object
     *
     * @return object The created connection instance
     */
    protected function makeConnection(string $name): object
    {
        $config = $this->getConnectionConfig($name);

        if (array_key_exists($name, $this->extensions)) {
            $connection = $this->extensions[$name]($config);

            if (!is_object($connection)) {
                throw ExtensionMustReturnObjectException::forExtension($name);
            }

            return $connection;
        }

        $driver = Arr::get($config, 'driver');

        if (is_string($driver) && array_key_exists($driver, $this->extensions)) {
            $connection = $this->extensions[$driver]($config);

            if (!is_object($connection)) {
                throw ExtensionMustReturnObjectException::forDriver($driver);
            }

            return $connection;
        }

        return $this->createConnection($config);
    }

    /**
     * Get the given named configuration.
     *
     * Retrieves a named configuration section and validates that it exists and is
     * properly formatted. Adds the configuration name to the returned array.
     *
     * @param string $type The configuration type (e.g., 'connections')
     * @param string $desc The descriptive name for error messages (e.g., 'Connection')
     * @param string $name The specific configuration name to retrieve
     *
     * @throws InvalidArgumentException When the configuration is not found, invalid, or not an array
     *
     * @return array<string, mixed> The configuration array with 'name' key added
     */
    protected function getNamedConfig(string $type, string $desc, string $name): array
    {
        $data = Config::get($this->getConfigName().'.'.$type);

        if (!is_array($data)) {
            throw ConfigurationNotFoundException::forConfiguration($desc, $name);
        }

        $config = Arr::get($data, $name);

        if (!is_array($config) && !$config) {
            throw ConfigurationNotFoundException::forConfiguration($desc, $name);
        }

        if (!is_array($config)) {
            throw ConfigurationNotArrayException::forConfiguration($desc, $name);
        }

        /** @var array<string, mixed> $config */
        $config['name'] = $name;

        return $config;
    }

    /**
     * Create the connection instance.
     *
     * This method must be implemented by concrete manager classes to create
     * the actual connection object based on the provided configuration.
     *
     * @param array<string, mixed> $config The connection configuration array
     *
     * @throws InvalidArgumentException When the connection cannot be created
     *
     * @return object The created connection instance
     */
    abstract protected function createConnection(array $config): object;

    /**
     * Get the configuration name.
     *
     * Returns the configuration key prefix used to retrieve manager settings.
     * For example, 'database' would read from config('database.connections').
     *
     * @return string The configuration name/key prefix
     */
    abstract protected function getConfigName(): string;
}
