<?php

declare(strict_types=1);

namespace Hypervel\Tests\Sentry\Features;

use DateTimeZone;
use Hypervel\Bus\Queueable;
use Hypervel\Console\Scheduling\Event;
use Hypervel\Console\Scheduling\Schedule;
use Hypervel\Foundation\Testing\Concerns\RunTestsInCoroutine;
use Hypervel\Queue\Contracts\ShouldQueue;
use Hypervel\Sentry\Features\ConsoleSchedulingFeature;
use Hypervel\Tests\Sentry\SentryTestCase;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
class ConsoleSchedulingFeatureTest extends SentryTestCase
{
    use RunTestsInCoroutine;

    protected array $defaultSetupConfig = [
        'sentry.features' => [
            ConsoleSchedulingFeature::class,
        ],
    ];

    public function testScheduleMacro(): void
    {
        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()
            ->call(function () {
            })
            ->sentryMonitor('test-monitor');

        $scheduledEvent->run($this->app);

        // We expect a total of 2 events to be sent to Sentry:
        // 1. The start check-in event
        // 2. The finish check-in event
        $this->assertSentryCheckInCount(2);

        $finishCheckInEvent = $this->getLastSentryEvent();

        $this->assertNotNull($finishCheckInEvent->getCheckIn());
        $this->assertEquals('test-monitor', $finishCheckInEvent->getCheckIn()->getMonitorSlug());
    }

    /**
     * When a timezone was defined on a command this would fail with:
     * Sentry\MonitorConfig::__construct(): Argument #4 ($timezone) must be of type ?string, DateTimeZone given
     * This test ensures that the timezone is properly converted to a string as expected.
     */
    public function testScheduleMacroWithTimeZone(): void
    {
        $expectedTimezone = 'UTC';

        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()
            ->call(function () {
            })
            ->timezone(new DateTimeZone($expectedTimezone))
            ->sentryMonitor('test-timezone-monitor');

        $scheduledEvent->run($this->app);

        // We expect a total of 2 events to be sent to Sentry:
        // 1. The start check-in event
        // 2. The finish check-in event
        $this->assertSentryCheckInCount(2);

        $finishCheckInEvent = $this->getLastSentryEvent();

        $this->assertNotNull($finishCheckInEvent->getCheckIn());
        $this->assertEquals($expectedTimezone, $finishCheckInEvent->getCheckIn()->getMonitorConfig()->getTimezone());
    }

    public function testScheduleMacroAutomaticSlugForCommand(): void
    {
        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()->command('migrate')->sentryMonitor();

        $scheduledEvent->run($this->app);

        // We expect a total of 2 events to be sent to Sentry:
        // 1. The start check-in event
        // 2. The finish check-in event
        $this->assertSentryCheckInCount(2);

        $finishCheckInEvent = $this->getLastSentryEvent();

        $this->assertNotNull($finishCheckInEvent->getCheckIn());
        $this->assertEquals('scheduled_migrate', $finishCheckInEvent->getCheckIn()->getMonitorSlug());
    }

    public function testScheduleMacroAutomaticSlugForJob(): void
    {
        /** @var Event $scheduledEvent */
        $scheduledEvent = $this->getScheduler()->job(ScheduledQueuedJob::class)->sentryMonitor();

        $scheduledEvent->run($this->app);

        // We expect a total of 2 events to be sent to Sentry:
        // 1. The start check-in event
        // 2. The finish check-in event
        $this->assertSentryCheckInCount(2);

        $finishCheckInEvent = $this->getLastSentryEvent();

        $this->assertNotNull($finishCheckInEvent->getCheckIn());
        // Scheduled is duplicated here because of the class name of the queued job, this is not a bug just unfortunate naming for the test class
        $this->assertEquals(
            'scheduled_scheduledqueuedjob-features-sentry-tests-hypervel',
            $finishCheckInEvent->getCheckIn()->getMonitorSlug()
        );
    }

    public function testScheduleMacroWithoutSlugCommandOrDescriptionOrName(): void
    {
        $this->expectException(RuntimeException::class);

        $this->getScheduler()->call(function () {
        })->sentryMonitor();
    }

    public function testScheduledClosureCreatesTransaction(): void
    {
        $this->getScheduler()->call(function () {
        })->everySecond();

        $this->artisan('schedule:run --once');

        $this->assertSentryTransactionCount(1);

        $transaction = $this->getLastSentryEvent();

        $this->assertEquals('Closure', $transaction->getTransaction());
    }

    /** @define-env envSamplingAllTransactions */
    public function testScheduledJobCreatesTransaction(): void
    {
        $this->getScheduler()->job(ScheduledQueuedJob::class)->everyMinute();

        $this->artisan('schedule:run --once');

        $this->assertSentryTransactionCount(1);

        $transaction = $this->getLastSentryEvent();

        $this->assertEquals(ScheduledQueuedJob::class, $transaction->getTransaction());
    }

    protected function getScheduler(): Schedule
    {
        return $this->app->get(Schedule::class);
    }
}

class ScheduledQueuedJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
    }
}
