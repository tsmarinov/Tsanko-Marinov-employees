<?php

namespace Tests\Unit\Services;

use App\Services\DateFormatDetector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DateFormatDetectorTest extends TestCase
{
    private DateFormatDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new DateFormatDetector();
    }

    public function test_detects_iso_format(): void
    {
        $format = $this->detector->detect([
            '2013-11-01', '2014-01-05', '2009-01-01', 'NULL',
        ]);

        $this->assertSame('Y-m-d', $format);
    }

    public function test_defaults_to_day_first_when_ambiguous(): void
    {
        // every day component is <= 12, so d/m/Y and m/d/Y both fit
        $format = $this->detector->detect(['05/03/2020', '01/02/2021', '11/07/2019']);

        $this->assertSame('d/m/Y', $format);
    }

    public function test_detects_month_first_when_unambiguous(): void
    {
        // 25 can only be a day, and here it sits in the second slot
        $format = $this->detector->detect(['03/25/2020', '11/13/2019', '07/28/2021']);

        $this->assertSame('m/d/Y', $format);
    }

    public function test_ignores_invalid_overflow_dates_when_scoring(): void
    {
        // 31/02 is not a real day-first date; only m/d/Y-style parsing... but
        // month 31 doesn't exist either, so this sample matches nothing and
        // must fall through to a different, valid sample to decide the format.
        $format = $this->detector->detect(['31/02/2020', '15/06/2021']);

        $this->assertSame('d/m/Y', $format);
    }

    public function test_throws_when_no_sample_values_are_usable(): void
    {
        $this->expectException(RuntimeException::class);

        $this->detector->detect(['NULL', '', null]);
    }

    public function test_try_parse_returns_correct_date(): void
    {
        $date = $this->detector->tryParse('2013-11-01', 'Y-m-d');

        $this->assertNotNull($date);
        $this->assertSame('2013-11-01', $date->format('Y-m-d'));
    }

    public function test_try_parse_falls_back_to_another_known_format(): void
    {
        // detected format is Y-m-d, but this particular row is dirty/mixed
        $date = $this->detector->tryParse('05/03/2020', 'Y-m-d');

        $this->assertNotNull($date);
        $this->assertSame('2020-03-05', $date->format('Y-m-d'));
    }

    public function test_try_parse_returns_null_for_garbage(): void
    {
        $this->assertNull($this->detector->tryParse('not-a-date', 'Y-m-d'));
        $this->assertNull($this->detector->tryParse('NULL', 'Y-m-d'));
        $this->assertNull($this->detector->tryParse(null, 'Y-m-d'));
        $this->assertNull($this->detector->tryParse('', 'Y-m-d'));
    }

    public function test_is_null_token_is_case_and_whitespace_insensitive(): void
    {
        $this->assertTrue($this->detector->isNullToken('NULL'));
        $this->assertTrue($this->detector->isNullToken(' null '));
        $this->assertFalse($this->detector->isNullToken('2020-01-01'));
        $this->assertFalse($this->detector->isNullToken(null));
    }
}
