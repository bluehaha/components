<?php

declare(strict_types=1);

namespace Hypervel\Tests\Redis;

use Hyperf\Contract\PoolInterface;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSource;
use Hyperf\Pool\PoolOption;
use Hypervel\Redis\RedisConnection;
use Hypervel\Tests\Redis\Stubs\RedisConnectionStub;
use Hypervel\Tests\TestCase;
use Mockery;
use Psr\Container\ContainerInterface;

/**
 * @internal
 * @coversNothing
 */
class RedisConnectionTest extends TestCase
{
    public function testShouldTransform(): void
    {
        $connection = $this->mockRedisConnection();

        $this->assertFalse($connection->getShouldTransform());

        $connection->shouldTransform(true);

        $this->assertTrue($connection->getShouldTransform());
    }

    public function testRelease(): void
    {
        $pool = $this->getMockedPool();
        $pool->shouldReceive('release')->once();

        $connection = $this->mockRedisConnection(pool: $pool);
        $connection->shouldTransform(true);

        $connection->release();

        $this->assertFalse($connection->getShouldTransform());
    }

    public function testCallGet(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('get')
            ->with($key = 'foo')
            ->once()
            ->andReturn($value = 'bar');

        $result = $connection->__call('get', [$key]);

        $this->assertEquals($value, $result);
    }

    public function testMget(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('mGet')
            ->with(['key1', 'key2', 'key3'])
            ->once()
            ->andReturn(['value1', false, 'value3']);

        $result = $connection->__call('mget', [['key1', 'key2', 'key3']]);

        $this->assertEquals(['value1', null, 'value3'], $result);
    }

    public function testSet(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('set')
            ->with('key', 'value', ['NX', 'EX' => 3600])
            ->once()
            ->andReturn(true);

        $result = $connection->__call('set', ['key', 'value', 'EX', 3600, 'NX']);

        $this->assertTrue($result);
    }

    public function testSetnx(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('setNx')
            ->with('key', 'value')
            ->once()
            ->andReturn(true);

        $result = $connection->__call('setnx', ['key', 'value']);

        $this->assertEquals(1, $result);
    }

    public function testHmgetSingleArray(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('hMGet')
            ->with('hash', ['field1', 'field2'])
            ->once()
            ->andReturn(['field1' => 'value1', 'field2' => 'value2']);

        $result = $connection->__call('hmget', ['hash', ['field1', 'field2']]);

        $this->assertEquals(['value1', 'value2'], $result);
    }

    public function testHmgetMultipleArgs(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('hMGet')
            ->with('hash', ['field1', 'field2'])
            ->once()
            ->andReturn(['field1' => 'value1', 'field2' => 'value2']);

        $result = $connection->__call('hmget', ['hash', 'field1', 'field2']);

        $this->assertEquals(['value1', 'value2'], $result);
    }

    public function testHmset(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('hMSet')
            ->with('hash', ['field1' => 'value1', 'field2' => 'value2'])
            ->once()
            ->andReturn(true);

        $result = $connection->__call('hmset', ['hash', ['field1' => 'value1', 'field2' => 'value2']]);

        $this->assertTrue($result);
    }

    public function testHsetnx(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('hSetNx')
            ->with('hash', 'field', 'value')
            ->once()
            ->andReturn(true);

        $result = $connection->__call('hsetnx', ['hash', 'field', 'value']);

        $this->assertEquals(1, $result);
    }

    public function testLrem(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('lRem')
            ->with('list', 'value', 2)
            ->once()
            ->andReturn(1);

        $result = $connection->__call('lrem', ['list', 2, 'value']);

        $this->assertEquals(1, $result);
    }

    public function testBlpopWithResult(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('blPop')
            ->with('list1', 'list2', 10)
            ->once()
            ->andReturn(['list1', 'value']);

        $result = $connection->__call('blpop', ['list1', 'list2', 10]);

        $this->assertEquals(['list1', 'value'], $result);
    }

    public function testBlpopEmpty(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('blPop')
            ->with('list1', 10)
            ->once()
            ->andReturn([]);

        $result = $connection->__call('blpop', ['list1', 10]);

        $this->assertNull($result);
    }

    public function testBrpopWithResult(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('brPop')
            ->with('list1', 'list2', 10)
            ->once()
            ->andReturn(['list2', 'value']);

        $result = $connection->__call('brpop', ['list1', 'list2', 10]);

        $this->assertEquals(['list2', 'value'], $result);
    }

    public function testBrpopEmpty(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('brPop')
            ->with('list1', 10)
            ->once()
            ->andReturn([]);

        $result = $connection->__call('brpop', ['list1', 10]);

        $this->assertNull($result);
    }

    public function testSpop(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('sPop')
            ->with('myset', 2)
            ->once()
            ->andReturn(['member1', 'member2']);

        $result = $connection->__call('spop', ['myset', 2]);

        $this->assertEquals(['member1', 'member2'], $result);
    }

    public function testScan(): void
    {
        $connection = $this->mockRedisConnection(transform: true);
        $cursor = 0;

        $connection->getConnection()
            ->shouldReceive('scan')
            ->with(0, '*', 10)
            ->once()
            ->andReturn(['key1', 'key2']);

        $result = $connection->scan($cursor, '*', 10);

        $this->assertEquals([0, ['key1', 'key2']], $result);
    }

    public function testScanWithOptions(): void
    {
        $connection = $this->mockRedisConnection(transform: true);
        $cursor = 0;

        $connection->getConnection()
            ->shouldReceive('scan')
            ->with(0, 'prefix:*', 20)
            ->once()
            ->andReturn(['key1', 'key2']);

        $result = $connection->scan($cursor, 'prefix:*', 20);

        $this->assertEquals([0, ['key1', 'key2']], $result);
    }

    public function testScanWithEmptyResult(): void
    {
        $connection = $this->mockRedisConnection(transform: true);
        $cursor = 0;

        $connection->getConnection()
            ->shouldReceive('scan')
            ->with(0, '*', 10)
            ->once()
            ->andReturn(false);

        $result = $connection->scan($cursor, '*', 10);

        $this->assertFalse($result);
    }

    public function testZscan(): void
    {
        $connection = $this->mockRedisConnection(transform: true);
        $cursor = 0;

        $connection->getConnection()
            ->shouldReceive('zscan')
            ->with('sortedset', 0, '*', 10)
            ->once()
            ->andReturn(['member1' => 1.0, 'member2' => 2.0]);

        $result = $connection->zscan('sortedset', $cursor, '*', 10);

        $this->assertEquals([0, ['member1' => 1.0, 'member2' => 2.0]], $result);
    }

    public function testHscan(): void
    {
        $connection = $this->mockRedisConnection(transform: true);
        $cursor = 0;

        $connection->getConnection()
            ->shouldReceive('hscan')
            ->with('hash', 0, '*', 10)
            ->once()
            ->andReturn(['field1' => 'value1', 'field2' => 'value2']);

        $result = $connection->hscan('hash', $cursor, '*', 10);

        $this->assertEquals([0, ['field1' => 'value1', 'field2' => 'value2']], $result);
    }

    public function testSscan(): void
    {
        $connection = $this->mockRedisConnection(transform: true);
        $cursor = 0;

        $connection->getConnection()
            ->shouldReceive('sscan')
            ->with('set', 0, '*', 10)
            ->once()
            ->andReturn(['member1', 'member2']);

        $result = $connection->sscan('set', $cursor, '*', 10);

        $this->assertEquals([0, ['member1', 'member2']], $result);
    }

    public function testEvalsha(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $redisConnection = $connection->getConnection();
        $redisConnection->shouldReceive('script')
            ->with('load', 'script')
            ->once()
            ->andReturn('sha1');

        $redisConnection->shouldReceive('evalSha')
            ->with('sha1', ['key1', 'key2'], 2)
            ->once()
            ->andReturn('result');

        $result = $connection->__call('evalsha', ['script', 2, 'key1', 'key2']);

        $this->assertEquals('result', $result);
    }

    public function testZaddWithOptions(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('zAdd')
            ->with('sortedset', ['NX', 'CH'], 1.0, 'member1', 2.0, 'member2')
            ->once()
            ->andReturn(2);

        $result = $connection->__call('zadd', ['sortedset', 'NX', 'CH', 1.0, 'member1', 2.0, 'member2']);

        $this->assertEquals(2, $result);
    }

    public function testZaddWithArray(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('zAdd')
            ->with('sortedset', [], 1.0, 'member1', 2.0, 'member2')
            ->once()
            ->andReturn(2);

        $result = $connection->__call('zadd', ['sortedset', ['member1' => 1.0, 'member2' => 2.0]]);

        $this->assertEquals(2, $result);
    }

    public function testZrangebyscoreWithOptions(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('zRangeByScore')
            ->with('sortedset', '1', '5', ['limit' => [0, 10]])
            ->once()
            ->andReturn(['member1', 'member2']);

        $result = $connection->__call('zrangebyscore', ['sortedset', '1', '5', ['limit' => ['offset' => 0, 'count' => 10]]]);

        $this->assertEquals(['member1', 'member2'], $result);
    }

    public function testFlushdbAsync(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('flushdb')
            ->with(true)
            ->once()
            ->andReturn(true);

        $result = $connection->__call('flushdb', ['ASYNC']);

        $this->assertTrue($result);
    }

    public function testFlushdbSync(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('flushdb')
            ->with()
            ->once()
            ->andReturn(true);

        $result = $connection->__call('flushdb', []);

        $this->assertTrue($result);
    }

    public function testExecuteRaw(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('rawCommand')
            ->with('CUSTOM', 'arg1', 'arg2')
            ->once()
            ->andReturn('result');

        $result = $connection->__call('executeRaw', [['CUSTOM', 'arg1', 'arg2']]);

        $this->assertEquals('result', $result);
    }

    public function testZinterstoreWithOptions(): void
    {
        $connection = $this->mockRedisConnection(transform: true);

        $connection->getConnection()
            ->shouldReceive('zinterstore')
            ->with('output', ['set1', 'set2'], [1, 2], 'max')
            ->once()
            ->andReturn(3);

        $result = $connection->__call('zinterstore', ['output', ['set1', 'set2'], ['weights' => [1, 2], 'aggregate' => 'max']]);

        $this->assertEquals(3, $result);
    }

    public function testZunionstoreSimple(): void
    {
        $connection = $this->mockRedisConnection();
        $connection->shouldTransform(false);

        $connection->getConnection()
            ->shouldReceive('zunionstore')
            ->withAnyArgs()
            ->once()
            ->andReturn(5);

        $result = $connection->__call('zunionstore', ['output', ['set1', 'set2']]);

        $this->assertEquals(5, $result);
    }

    protected function mockRedisConnection(?ContainerInterface $container = null, ?PoolInterface $pool = null, array $options = [], bool $transform = false): RedisConnection
    {
        $connection = new RedisConnectionStub(
            $container ?? $this->getContainer(),
            $pool ?? $this->getMockedPool(),
            $options
        );

        if ($transform) {
            $connection->shouldTransform(true);
        }

        return $connection;
    }

    protected function getMockedPool(): PoolInterface
    {
        $pool = Mockery::mock(PoolInterface::class);
        $pool->shouldReceive('getOption')
            ->andReturn(Mockery::mock(PoolOption::class));

        return $pool;
    }

    protected function getContainer(array $definitions = []): Container
    {
        return new Container(
            new DefinitionSource($definitions)
        );
    }
}
