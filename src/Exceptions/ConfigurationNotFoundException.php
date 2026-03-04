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
 * @author Brian Faust <brian@cline.sh>
 */
final class ConfigurationNotFoundException extends InvalidArgumentException implements ManagerException
{
    public static function forConfiguration(string $description, string $name): self
    {
        return new self(sprintf('%s [%s] not configured.', $description, $name));
    }
}
