<?php

declare(strict_types=1);

namespace Hypervel\Tests\Router;

use Hyperf\Config\Config;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;
use Hyperf\Context\RequestContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\SessionInterface;
use Hyperf\HttpMessage\Server\Request as ServerRequest;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Request;
use Hyperf\HttpServer\Router\DispatcherFactory as HyperfDispatcherFactory;
use Hypervel\Router\DispatcherFactory;
use Hypervel\Router\RouteCollector;
use Hypervel\Router\UrlGenerator;
use Hypervel\Tests\Router\Stub\UrlRoutableStub;
use Hypervel\Tests\TestCase;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;

/**
 * @internal
 * @coversNothing
 */
class UrlGeneratorTest extends TestCase
{
    /**
     * @var ContainerInterface|MockInterface
     */
    private ContainerInterface $container;

    /**
     * @var MockInterface|RouteCollector
     */
    private RouteCollector $router;

    protected function setUp(): void
    {
        $this->mockContainer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Context::destroy('__request.root.uri');
        Context::destroy(ServerRequestInterface::class);
    }

    public function testRoute()
    {
        $this->skipDirtyBasePath();

        $this->mockRouter();

        $config = Mockery::mock(ConfigInterface::class);
        $config->shouldReceive('get')
            ->with('app.url')
            ->andReturn('http://example.com');
        $this->container->shouldReceive('get')
            ->with(ConfigInterface::class)
            ->andReturn($config);

        $this->router
            ->shouldReceive('getNamedRoutes')
            ->andReturn([
                'foo' => ['/foo'],
                'bar' => ['/foo/', ['bar', '[^/]+']],
                'baz' => ['/foo/', ['bar', '[^/]+'], '/baz'],
            ]);

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertEquals('http://example.com/foo', $urlGenerator->route('foo'));
        $this->assertEquals('http://example.com/foo?bar=1', $urlGenerator->route('foo', ['bar' => 1]));
        $this->assertEquals('http://example.com/foo?bar=1&baz=2', $urlGenerator->route('foo', ['bar' => 1, 'baz' => 2]));
        $this->assertEquals('http://example.com/foo', $urlGenerator->route('bar'));

        $this->assertEquals('/foo/1', $urlGenerator->route('bar', ['bar' => 1], false));
        $this->assertEquals('/foo/1?baz=2', $urlGenerator->route('bar', ['bar' => 1, 'baz' => 2], false));
        $this->assertEquals('/foo/1/baz', $urlGenerator->route('baz', ['bar' => 1], false));
        $this->assertEquals('/foo/1/baz?baz=2', $urlGenerator->route('baz', ['bar' => 1, 'baz' => 2], false));
    }

    public function testRouteWithNotDefined()
    {
        $this->skipDirtyBasePath();

        $this->mockRouter();

        $this->router
            ->shouldReceive('getNamedRoutes')
            ->andReturn([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route [foo] not defined.');

        $urlGenerator = new UrlGenerator($this->container);
        $urlGenerator->route('foo');
    }

    public function testTo()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertSame('http://example.com/foo/bar', $urlGenerator->to('foo/bar'));
        $this->assertSame('https://example.com/foo/bar', $urlGenerator->to('foo/bar', [], true));
        $this->assertSame('https://example.com/foo/bar/baz/boom', $urlGenerator->to('foo/bar', ['baz', 'boom'], true));
        $this->assertSame('https://example.com/foo/bar/baz?foo=bar', $urlGenerator->to('foo/bar?foo=bar', ['baz'], true));
    }

    public function testToWithValidUrl()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertEquals('http://example.com', $urlGenerator->to('http://example.com'));
        $this->assertEquals('https://example.com', $urlGenerator->to('https://example.com'));
        $this->assertEquals('//example.com', $urlGenerator->to('//example.com'));
        $this->assertEquals('mailto:hello@example.com', $urlGenerator->to('mailto:hello@example.com'));
        $this->assertEquals('tel:1234567890', $urlGenerator->to('tel:1234567890'));
        $this->assertEquals('sms:1234567890', $urlGenerator->to('sms:1234567890'));
        $this->assertEquals('#foo', $urlGenerator->to('#foo'));
        $this->assertEquals('ftp://example.com', $urlGenerator->to('ftp://example.com'));
    }

    public function testToWithExtra()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertEquals('http://example.com/foo/bar/baz', $urlGenerator->to('foo', ['bar', 'baz']));
        $this->assertEquals('http://example.com/foo/%3F/%3D', $urlGenerator->to('foo', ['?', '=']));
        $this->assertEquals('http://example.com/foo/1', $urlGenerator->to('foo', [new UrlRoutableStub()]));
    }

    public function testToWithSecure()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertEquals('https://example.com/foo', $urlGenerator->to('foo', secure: true));
    }

    public function testToWithRootUrlCache()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertEquals('http://example.com/foo', $urlGenerator->to('foo'));
        $this->assertEquals('http://example.com', Context::get('__request.root.uri')->toString());
    }

    public function testQueryGeneration()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertSame('http://example.com/foo/bar', $urlGenerator->query('foo/bar'));
        $this->assertSame('http://example.com/foo/bar?0=foo', $urlGenerator->query('foo/bar', ['foo']));
        $this->assertSame('http://example.com/foo/bar?baz=boom', $urlGenerator->query('foo/bar', ['baz' => 'boom']));
        $this->assertSame('http://example.com/foo/bar?baz=zee&zal=bee', $urlGenerator->query('foo/bar?baz=boom&zal=bee', ['baz' => 'zee']));
        $this->assertSame('http://example.com/foo/bar?zal=bee', $urlGenerator->query('foo/bar?baz=boom&zal=bee', ['baz' => null]));
        $this->assertSame('http://example.com/foo/bar?baz=boom', $urlGenerator->query('foo/bar?baz=boom', ['nonexist' => null]));
        $this->assertSame('http://example.com/foo/bar', $urlGenerator->query('foo/bar?baz=boom', ['baz' => null]));
        $this->assertSame('http://example.com/foo/bar?baz[0]=boom&baz[1]=bam&baz[2]=bim', urldecode($urlGenerator->query('foo/bar', ['baz' => ['boom', 'bam', 'bim']])));
    }

    public function testAssetGeneration()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertSame('http://example.com/foo/bar', $urlGenerator->asset('foo/bar'));
        $this->assertSame('https://example.com/foo/bar', $urlGenerator->asset('foo/bar', true));
    }

    public function testBasicGenerationWithHostFormatting()
    {
        $this->skipDirtyBasePath();

        $this->mockRequest();
        $this->mockRouter();

        $this->router
            ->shouldReceive('getNamedRoutes')
            ->andReturn([
                'plain' => ['/named-route'],
            ]);

        $urlGenerator = new UrlGenerator($this->container);

        $urlGenerator->formatHostUsing(function ($host) {
            return str_replace('example.com', 'example.org', $host);
        });

        $this->assertSame('http://example.org/foo/bar', $urlGenerator->to('foo/bar'));
        $this->assertSame('/named-route', $urlGenerator->route('plain', [], false));
    }

    public function testBasicGenerationWithPathFormatting()
    {
        $this->skipDirtyBasePath();

        $this->mockRequest();
        $this->mockRouter();

        $this->router
            ->shouldReceive('getNamedRoutes')
            ->andReturn([
                'plain' => ['/named-route'],
            ]);

        $urlGenerator = new UrlGenerator($this->container);

        $urlGenerator->formatPathUsing(function ($path) {
            return '/something' . $path;
        });

        $this->assertSame('http://example.com/something/foo/bar', $urlGenerator->to('foo/bar'));
        $this->assertSame('/something/named-route', $urlGenerator->route('plain', [], false));
    }

    public function testSecure()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertEquals('https://example.com/foo', $urlGenerator->secure('foo'));
        $this->assertEquals('https://example.com/foo/bar', $urlGenerator->secure('foo', ['bar']));
    }

    public function testNoRequestContext()
    {
        $urlGenerator = new UrlGenerator($this->container);

        $this->container->shouldReceive('get')->with(ConfigInterface::class)->andReturn(new Config([
            'app' => [
                'url' => 'http://localhost',
            ],
        ]));

        $this->assertEquals('http://localhost/foo', $urlGenerator->to('foo'));
    }

    public function testFull()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertEquals('http://example.com/foo?bar=baz#boom', $urlGenerator->full());
    }

    public function testCurrent()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        $this->assertEquals('http://example.com/foo', $urlGenerator->current());
    }

    public function testPrevious()
    {
        $urlGenerator = new UrlGenerator($this->container);

        // Test with referer header
        RequestContext::set(
            new ServerRequest(
                'GET',
                'http://example.com/foo',
                ['referer' => 'http://example.com/previous']
            )
        );
        $this->container->shouldReceive('has')
            ->with(SessionInterface::class)
            ->andReturnFalse();

        $this->assertEquals('http://example.com/previous', $urlGenerator->previous());

        // Test without referer header and no session
        RequestContext::set(
            new ServerRequest(
                'GET',
                'http://example.com/foo'
            )
        );

        $this->assertEquals('http://example.com', $urlGenerator->previous());

        // Test with fallback
        $this->assertEquals('http://example.com/fallback', $urlGenerator->previous('fallback'));
    }

    public function testPreviousPath()
    {
        $urlGenerator = new UrlGenerator($this->container);

        // Mock RequestContext
        RequestContext::set(
            new ServerRequest(
                'GET',
                'http://example.com/foo',
                ['referer' => 'http://example.com/previous/path']
            )
        );

        // Mock ConfigInterface for app.url
        $mockConfig = Mockery::mock(ConfigInterface::class);
        $mockConfig->shouldReceive('get')
            ->with('app.url')
            ->andReturn('http://example.com');
        $this->container->shouldReceive('get')
            ->with(ConfigInterface::class)
            ->andReturn($mockConfig);

        // Test with referer header
        $this->container->shouldReceive('has')
            ->with(SessionInterface::class)
            ->andReturnFalse();

        $this->assertEquals('/previous/path', $urlGenerator->previousPath());

        // Test without referer header and no session
        RequestContext::set(
            new ServerRequest(
                'GET',
                'http://example.com/foo'
            )
        );

        $this->assertEquals('/', $urlGenerator->previousPath());

        // Test with fallback
        $this->assertEquals('/fallback', $urlGenerator->previousPath('fallback'));
    }

    public function testIsValidUrl()
    {
        $urlGenerator = new UrlGenerator($this->container);
        $method = new ReflectionMethod(UrlGenerator::class, 'isValidUrl');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($urlGenerator, 'http://example.com'));
        $this->assertTrue($method->invoke($urlGenerator, 'https://example.com'));
        $this->assertTrue($method->invoke($urlGenerator, '//example.com'));
        $this->assertTrue($method->invoke($urlGenerator, 'mailto:test@example.com'));
        $this->assertTrue($method->invoke($urlGenerator, 'tel:1234567890'));
        $this->assertTrue($method->invoke($urlGenerator, 'sms:1234567890'));
        $this->assertTrue($method->invoke($urlGenerator, '#anchor'));
        $this->assertFalse($method->invoke($urlGenerator, 'not-a-url'));
    }

    public function testForceScheme()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        // Test forcing https scheme
        $urlGenerator->forceScheme('https');
        $this->assertEquals('https://example.com/foo', $urlGenerator->to('foo'));

        // Test forcing http scheme
        $urlGenerator->forceScheme('http');
        $this->assertEquals('http://example.com/foo', $urlGenerator->to('foo'));

        // Test forcing custom scheme
        $urlGenerator->forceScheme('ftp');
        $this->assertEquals('ftp://example.com/foo', $urlGenerator->to('foo'));

        // Test clearing forced scheme (passing null)
        $urlGenerator->forceScheme(null);
        $this->assertEquals('http://example.com/foo', $urlGenerator->to('foo'));
    }

    public function testForceHttps()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        // Test original scheme
        $this->assertEquals('http://example.com/foo', $urlGenerator->to('foo'));

        // Test forcing HTTPS
        $urlGenerator->forceHttps();
        $this->assertEquals('https://example.com/foo', $urlGenerator->to('foo'));
    }

    public function testFormatScheme()
    {
        $this->mockRequest();

        $urlGenerator = new UrlGenerator($this->container);

        // Test with secure = true
        $this->assertEquals('https://', $urlGenerator->formatScheme(true));

        // Test with secure = false
        $this->assertEquals('http://', $urlGenerator->formatScheme(false));

        // Test with secure = null (should use request scheme)
        $this->assertEquals('http://', $urlGenerator->formatScheme(null));

        // Test with forceScheme set
        $urlGenerator->forceScheme('https');
        $this->assertEquals('https://', $urlGenerator->formatScheme(null));

        // Test with different forceScheme
        $urlGenerator->forceScheme('ftp');
        $this->assertEquals('ftp://', $urlGenerator->formatScheme(null));

        // Test that explicit secure parameter overrides forceScheme
        $urlGenerator->forceScheme('https');
        $this->assertEquals('http://', $urlGenerator->formatScheme(false));
        $this->assertEquals('https://', $urlGenerator->formatScheme(true));
    }

    public function testFormatParameters()
    {
        $urlGenerator = new UrlGenerator($this->container);
        $method = new ReflectionMethod(UrlGenerator::class, 'formatParameters');
        $method->setAccessible(true);

        $urlRoutable = new UrlRoutableStub();
        $parameters = ['key' => $urlRoutable, 'normal' => 'value'];

        $result = $method->invoke($urlGenerator, $parameters);
        $this->assertEquals(['key' => '1', 'normal' => 'value'], $result);
    }

    public function testSignedUrl()
    {
        $this->skipDirtyBasePath();

        $this->mockRequest();
        $this->mockRouter();

        $this->router
            ->shouldReceive('getNamedRoutes')
            ->andReturn([
                'foo' => ['/foo'],
            ]);

        $urlGenerator = new UrlGenerator($this->container);
        $urlGenerator->setSignedKey('secret');

        $request = new Request();

        $this->mockRequest(
            $urlGenerator->signedRoute('foo')
        );

        $this->assertTrue($urlGenerator->hasValidSignature($request));

        $this->mockRequest(
            $urlGenerator->signedRoute('foo') . '&tampered=true'
        );

        $this->assertFalse($urlGenerator->hasValidSignature($request));
    }

    public function testSignedRelativeUrl()
    {
        $this->skipDirtyBasePath();

        $this->mockRequest();
        $this->mockRouter();

        $this->router
            ->shouldReceive('getNamedRoutes')
            ->andReturn([
                'foo' => ['/foo'],
            ]);

        $urlGenerator = new UrlGenerator($this->container);
        $urlGenerator->setSignedKey('secret');

        $request = new Request();

        $this->mockRequest(
            $urlGenerator->signedRoute('foo', [], null, false)
        );

        $this->assertTrue($urlGenerator->hasValidSignature($request));

        $this->mockRequest(
            $urlGenerator->signedRoute('foo', [], null, false) . '&tampered=true'
        );

        $this->assertFalse($urlGenerator->hasValidSignature($request));
    }

    public function testSignedUrlParameterCannotBeNamedSignature()
    {
        $this->skipDirtyBasePath();

        $this->mockRequest();
        $this->mockRouter();

        $this->router
            ->shouldReceive('getNamedRoutes')
            ->andReturn([
                'foo' => ['/foo/{signature}'],
            ]);

        $urlGenerator = new UrlGenerator($this->container);
        $urlGenerator->setSignedKey('secret');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');

        Request::create($urlGenerator->signedRoute('foo', ['signature' => 'bar']));
    }

    public function testSignedUrlParameterCannotBeNamedExpires()
    {
        $this->skipDirtyBasePath();

        $this->mockRequest();
        $this->mockRouter();

        $this->router
            ->shouldReceive('getNamedRoutes')
            ->andReturn([
                'foo' => ['/foo/{expires}'],
            ]);

        $urlGenerator = new UrlGenerator($this->container);
        $urlGenerator->setSignedKey('secret');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');

        Request::create($urlGenerator->signedRoute('foo', ['expires' => 253402300799]));
    }

    private function mockContainer()
    {
        /** @var ContainerInterface|MockInterface */
        $container = Mockery::mock(ContainerInterface::class);

        $container->shouldReceive('get')
            ->with(RequestInterface::class)
            ->andReturn(new Request());

        ApplicationContext::setContainer($container);

        $this->container = $container;
    }

    private function mockRouter(?RouteCollector $router = null)
    {
        /** @var DispatcherFactory|MockInterface */
        $factory = Mockery::mock(DispatcherFactory::class);

        /** @var MockInterface|RouteCollector */
        $router = $router ?: Mockery::mock(RouteCollector::class);

        $this->container
            ->shouldReceive('get')
            ->with(HyperfDispatcherFactory::class)
            ->andReturn($factory);

        $factory
            ->shouldReceive('getRouter')
            ->with('http')
            ->andReturn($router);

        $this->router = $router;
    }

    private function mockRequest(?string $uri = null)
    {
        $request = new ServerRequest('GET', $uri ?: 'http://example.com/foo?bar=baz#boom');
        parse_str($request->getUri()->getQuery(), $result);
        $request = $request->withQueryParams($result);

        RequestContext::set($request);
    }

    private function skipDirtyBasePath(): void
    {
        if (! defined('BASE_PATH')) {
            $this->markTestSkipped('skip it because DispatcherFactory in hyperf is dirty.');
        }
    }
}
