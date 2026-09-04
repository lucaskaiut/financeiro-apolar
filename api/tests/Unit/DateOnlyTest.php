<?php

namespace Tests\Unit;

use App\Modules\Shared\Support\DateOnly;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DateOnlyTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function test_normalize_preserves_calendar_day(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, DateOnly::normalize($input));
    }

    /**
     * @return array<string, array{0: mixed, 1: ?string}>
     */
    public static function normalizeProvider(): array
    {
        return [
            'null' => [null, null],
            'empty' => ['', null],
            'date only' => ['2026-09-04', '2026-09-04'],
            'iso utc midnight' => ['2026-09-04T00:00:00.000Z', '2026-09-04'],
            'iso with offset' => ['2026-09-04T00:00:00+00:00', '2026-09-04'],
            'datetime local' => ['2026-09-04 00:00:00', '2026-09-04'],
        ];
    }

    public function test_parse_does_not_shift_utc_midnight_to_previous_day(): void
    {
        $date = DateOnly::parse('2026-09-04T00:00:00.000Z');

        $this->assertSame('2026-09-04', $date->toDateString());
    }

    public function test_normalize_rejects_invalid_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateOnly::normalize('04/09/2026');
    }
}
