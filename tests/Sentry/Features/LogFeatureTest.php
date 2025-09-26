<?php

declare(strict_types=1);

namespace Hypervel\Tests\Sentry\Features;

use Hyperf\Contract\ConfigInterface;
use Hypervel\Foundation\Testing\Concerns\RunTestsInCoroutine;
use Hypervel\Sentry\Features\LogFeature;
use Hypervel\Support\Facades\Log;
use Hypervel\Tests\Sentry\SentryTestCase;
use Psr\Log\LoggerInterface;
use Sentry\Severity;

/**
 * @internal
 * @coversNothing
 */
class LogFeatureTest extends SentryTestCase
{
    use RunTestsInCoroutine;

    protected array $defaultSetupConfig = [
        'sentry.features' => [
            LogFeature::class,
        ],
    ];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        tap($app->get(ConfigInterface::class), static function (ConfigInterface $config) {
            $config->set('logging.channels.sentry', [
                'driver' => 'sentry',
            ]);

            $config->set('logging.channels.sentry_error_level', [
                'driver' => 'sentry',
                'level' => 'error',
            ]);
        });
    }

    public function testLogChannelIsRegistered(): void
    {
        $this->expectNotToPerformAssertions();
        Log::channel('sentry');
    }

    public function testLogChannelGeneratesEvents(): void
    {
        $logger = $this->app->get(LoggerInterface::class)->channel('sentry');

        $logger->info('Sentry Laravel info log message');

        $this->assertSentryEventCount(1);

        $event = $this->getLastSentryEvent();

        $this->assertEquals(Severity::info(), $event->getLevel());
        $this->assertEquals('Sentry Laravel info log message', $event->getMessage());
    }

    public function testLogChannelGeneratesEventsOnlyForConfiguredLevel(): void
    {
        $logger = $this->app->get(LoggerInterface::class)->channel('sentry_error_level');

        $logger->info('Sentry Laravel info log message');
        $logger->warning('Sentry Laravel warning log message');
        $logger->error('Sentry Laravel error log message');

        $this->assertSentryEventCount(1);

        $event = $this->getLastSentryEvent();

        $this->assertEquals(Severity::error(), $event->getLevel());
        $this->assertEquals('Sentry Laravel error log message', $event->getMessage());
    }
}
