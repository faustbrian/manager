<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Manager\Exceptions;

use InvalidArgumentException;

use function sprintf;

/**
 * Base exception thrown when an extension resolver does not return an object.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ExtensionMustReturnObjectException extends InvalidArgumentException implements ManagerException
{
    public static function forExtension(string $name): self
    {
        return new self(sprintf('Extension for [%s] must return an object', $name));
    }

    public static function forDriver(string $driver): self
    {
        return new self(sprintf('Extension for driver [%s] must return an object', $driver));
    }
}
