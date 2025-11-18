<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tests\Manager;

use Cline\Manager\AbstractManager;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * This is the abstract manager test class.
 *
 * @author Graham Campbell <hello@gjcampbell.co.uk>
 *
 * @internal
 */
final class AbstractManagerTest extends TestCase
{
    public function test_connection_name(): void
    {
        $config = ['driver' => 'manager'];

        $manager = $this->getConfigManager($config);

        $this->assertSame([], $manager->getConnections());

        $return = $manager->connection('example');

        $this->assertInstanceOf(ExampleClass::class, $return);

        $this->assertSame('example', $return->getName());

        $this->assertSame('manager', $return->getDriver());

        $this->assertArrayHasKey('example', $manager->getConnections());

        $return = $manager->reconnect('example');

        $this->assertInstanceOf(ExampleClass::class, $return);

        $this->assertSame('example', $return->getName());

        $this->assertSame('manager', $return->getDriver());

        $this->assertArrayHasKey('example', $manager->getConnections());

        $manager = $this->getManager();

        $manager->disconnect('example');

        $this->assertSame([], $manager->getConnections());
    }

    public function test_connection_null(): void
    {
        $config = ['driver' => 'manager'];

        $manager = $this->getConfigManager($config);

        $manager->getConfig()->shouldReceive('get')->twice()
            ->with('manager.default')->andReturn('example');

        $this->assertSame([], $manager->getConnections());

        $return = $manager->connection();

        $this->assertInstanceOf(ExampleClass::class, $return);

        $this->assertSame('example', $return->getName());

        $this->assertSame('manager', $return->getDriver());

        $this->assertArrayHasKey('example', $manager->getConnections());

        $return = $manager->reconnect();

        $this->assertInstanceOf(ExampleClass::class, $return);

        $this->assertSame('example', $return->getName());

        $this->assertSame('manager', $return->getDriver());

        $this->assertArrayHasKey('example', $manager->getConnections());

        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.default')->andReturn('example');

        $manager->disconnect();

        $this->assertSame([], $manager->getConnections());
    }

    public function test_connection_error(): void
    {
        $manager = $this->getManager();

        $config = ['driver' => 'error'];

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.connections')->andReturn(['example' => $config]);

        $this->assertSame([], $manager->getConnections());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection [error] not configured.');

        $manager->connection('error');
    }

    public function test_default_connection(): void
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.default')->andReturn('example');

        $this->assertSame('example', $manager->getDefaultConnection());

        $manager->getConfig()->shouldReceive('set')->once()
            ->with('manager.default', 'new');

        $manager->setDefaultConnection('new');

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.default')->andReturn('new');

        $this->assertSame('new', $manager->getDefaultConnection());
    }

    public function test_extend_name(): void
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.connections')->andReturn(['foo' => ['driver' => 'hello']]);

        $manager->extend('foo', fn (array $config): FooClass => new FooClass($config['name'], $config['driver']));

        $this->assertSame([], $manager->getConnections());

        $return = $manager->connection('foo');

        $this->assertInstanceOf(FooClass::class, $return);

        $this->assertSame('foo', $return->getName());

        $this->assertSame('hello', $return->getDriver());

        $this->assertArrayHasKey('foo', $manager->getConnections());
    }

    public function test_extend_driver(): void
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.connections')->andReturn(['qwerty' => ['driver' => 'bar']]);

        $manager->extend('bar', fn (array $config): BarClass => new BarClass($config['name'], $config['driver']));

        $this->assertSame([], $manager->getConnections());

        $return = $manager->connection('qwerty');

        $this->assertInstanceOf(BarClass::class, $return);

        $this->assertSame('qwerty', $return->getName());

        $this->assertSame('bar', $return->getDriver());

        $this->assertArrayHasKey('qwerty', $manager->getConnections());
    }

    public function test_extend_driver_callable(): void
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.connections')->andReturn(['qwerty' => ['driver' => 'bar']]);

        $manager->extend('bar', BarFactory::create(...));

        $this->assertSame([], $manager->getConnections());

        $return = $manager->connection('qwerty');

        $this->assertInstanceOf(BarClass::class, $return);

        $this->assertSame('qwerty', $return->getName());

        $this->assertSame('bar', $return->getDriver());

        $this->assertArrayHasKey('qwerty', $manager->getConnections());
    }

    public function test_call(): void
    {
        $config = ['driver' => 'manager'];

        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.default')->andReturn('example');

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.connections')->andReturn(['example' => $config]);

        $this->assertSame([], $manager->getConnections());

        $return = $manager->getName();

        $this->assertSame('example', $return);

        $this->assertArrayHasKey('example', $manager->getConnections());
    }

    public function test_extend_name_returns_non_object(): void
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.connections')->andReturn(['foo' => ['driver' => 'hello']]);

        $manager->extend('foo', fn (array $config): string => 'not an object');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension for [foo] must return an object');

        $manager->connection('foo');
    }

    public function test_extend_driver_returns_non_object(): void
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.connections')->andReturn(['qwerty' => ['driver' => 'bar']]);

        $manager->extend('bar', fn (array $config): string => 'not an object');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension for driver [bar] must return an object');

        $manager->connection('qwerty');
    }

    public function test_get_connection_config_with_non_array_type(): void
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.connections')->andReturn('not-an-array');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection [example] not configured.');

        $manager->getConnectionConfig('example');
    }

    public function test_get_connection_config_with_non_array_value(): void
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.connections')->andReturn(['example' => 'not-an-array']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection [example] configuration must be an array.');

        $manager->getConnectionConfig('example');
    }

    public function test_get_default_connection_not_string(): void
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->once()
            ->with('manager.default')->andReturn(['not', 'a', 'string']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Default connection must be a string');

        $manager->getDefaultConnection();
    }

    private function getManager(): ExampleManager
    {
        $repo = Mockery::mock(Repository::class);

        return new ExampleManager($repo);
    }

    private function getConfigManager(array $config): ExampleManager
    {
        $manager = $this->getManager();

        $manager->getConfig()->shouldReceive('get')->twice()
            ->with('manager.connections')->andReturn(['example' => $config]);

        return $manager;
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ExampleManager extends AbstractManager
{
    /**
     * Create the connection instance.
     *
     * @return string
     */
    protected function createConnection(array $config): ExampleClass
    {
        return new ExampleClass($config['name'], $config['driver']);
    }

    /**
     * Get the configuration name.
     */
    protected function getConfigName(): string
    {
        return 'manager';
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
abstract class AbstractClass
{
    public function __construct(
        private readonly string $name,
        private readonly string $driver,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class ExampleClass extends AbstractClass
{
    //
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class FooClass extends AbstractClass
{
    //
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class BarClass extends AbstractClass
{
    //
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class BarFactory
{
    public static function create(array $config): BarClass
    {
        return new BarClass($config['name'], $config['driver']);
    }
}
