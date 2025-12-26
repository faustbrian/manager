<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Manager\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when the default connection configuration is not a string.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class DefaultConnectionMustBeStringException extends InvalidArgumentException implements ManagerException
{
    public static function create(): self
    {
        return new self('Default connection must be a string');
    }
}
