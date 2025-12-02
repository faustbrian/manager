<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Manager;

use InvalidArgumentException;

/**
 * This is the connector interface.
 *
 * Defines the contract for connector classes that establish connections
 * based on configuration arrays.
 *
 * @author Graham Campbell <hello@gjcampbell.co.uk>
 */
interface ConnectorInterface
{
    /**
     * Establish a connection.
     *
     * Creates and returns a connection object based on the provided configuration.
     * The implementation should validate the configuration and throw an exception
     * if required parameters are missing or invalid.
     *
     * @param array<string, mixed> $config The connection configuration array
     *
     * @throws InvalidArgumentException When the configuration is invalid or connection fails
     *
     * @return object The established connection instance
     */
    public function connect(array $config): object;
}
