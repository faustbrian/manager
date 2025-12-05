<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Manager;

use Closure;
use InvalidArgumentException;

/**
 * This is the manager interface.
 *
 * Defines the contract for manager classes that handle multiple connections,
 * providing connection lifecycle management, configuration access, and extensibility.
 *
 * @author Graham Campbell <hello@gjcampbell.co.uk>
 */
interface ManagerInterface
{
    /**
     * Get a connection instance.
     *
     * Retrieves a cached connection or creates a new one if it doesn't exist.
     *
     * @param null|string $name The connection name, or null to use the default connection
     *
     * @throws InvalidArgumentException When the connection cannot be configured or created
     *
     * @return object The connection instance
     */
    public function connection(?string $name = null): object;

    /**
     * Reconnect to the given connection.
     *
     * Disconnects and recreates the connection instance.
     *
     * @param null|string $name The connection name, or null to use the default connection
     *
     * @throws InvalidArgumentException When the connection cannot be configured or created
     *
     * @return object The new connection instance
     */
    public function reconnect(?string $name = null): object;

    /**
     * Disconnect from the given connection.
     *
     * Removes the connection instance from the cache.
     *
     * @param null|string $name The connection name, or null to use the default connection
     */
    public function disconnect(?string $name = null): void;

    /**
     * Get the configuration for a connection.
     *
     * @param null|string $name The connection name, or null to use the default connection
     *
     * @throws InvalidArgumentException When the connection configuration is not found or invalid
     *
     * @return array<string, mixed> The connection configuration array
     */
    public function getConnectionConfig(?string $name = null): array;

    /**
     * Get the default connection name.
     *
     * @throws InvalidArgumentException When the default connection is not configured as a string
     *
     * @return string The default connection name from configuration
     */
    public function getDefaultConnection(): string;

    /**
     * Set the default connection name.
     *
     * @param string $name The connection name to set as default
     */
    public function setDefaultConnection(string $name): void;

    /**
     * Register an extension connection resolver.
     *
     * Allows custom connection drivers to be registered dynamically.
     *
     * @param string  $name     The extension name or driver name
     * @param Closure $resolver The resolver function that creates the connection
     */
    public function extend(string $name, Closure $resolver): void;

    /**
     * Return all of the created connections.
     *
     * @return array<string, object>
     */
    public function getConnections(): array;
}
