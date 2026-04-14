<?php

declare(strict_types=1);

namespace DigiSecure\Tests\Unit\Shared\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use DigiSecure\Shared\Infrastructure\Clock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::unfreeze();
    }

    #[Test]
    public function now_returns_utc(): void
    {
        $now = Clock::now();
        self::assertSame('UTC', $now->getTimezone()->getName());
    }

    #[Test]
    public function freeze_locks_time(): void
    {
        $fixed = new DateTimeImmutable('2024-06-15 14:30:00', new DateTimeZone('UTC'));
        Clock::freeze($fixed);

        self::assertSame('2024-06-15 14:30:00', Clock::now()->format('Y-m-d H:i:s'));
        self::assertStringStartsWith('2024-06-15 14:30:00', Clock::nowString());
    }

    #[Test]
    public function unfreeze_resumes_real_time(): void
    {
        $fixed = new DateTimeImmutable('2000-01-01 00:00:00', new DateTimeZone('UTC'));
        Clock::freeze($fixed);
        Clock::unfreeze();

        self::assertNotSame('2000-01-01', Clock::now()->format('Y-m-d'));
    }

    #[Test]
    public function nowString_includes_microseconds(): void
    {
        $str = Clock::nowString();
        // Formato: Y-m-d H:i:s.u  → la parte .u tiene 6 dígitos
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/', $str);
    }
}
