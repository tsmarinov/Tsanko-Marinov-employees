<?php

namespace Tests\Unit\Services\Employees;

use App\Services\DateFormatDetector;
use App\Services\Employees\EmploymentPeriodParser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EmploymentPeriodParserTest extends TestCase
{
    private EmploymentPeriodParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new EmploymentPeriodParser(new DateFormatDetector(), 'Y-m-d');
    }

    public function test_parses_a_valid_row(): void
    {
        $period = $this->parser->parse(['143', '12', '2013-11-01', '2014-01-05']);

        $this->assertNotNull($period);
        $this->assertSame(143, $period->empId);
        $this->assertSame(12, $period->projectId);
        $this->assertSame('2013-11-01', $period->dateFrom->format('Y-m-d'));
        $this->assertSame('2014-01-05', $period->dateTo->format('Y-m-d'));
    }

    public function test_resolves_null_date_to_as_today(): void
    {
        $period = $this->parser->parse(['218', '10', '2012-05-16', 'NULL']);

        $this->assertNotNull($period);
        $this->assertSame((new DateTimeImmutable('today'))->format('Y-m-d'), $period->dateTo->format('Y-m-d'));
    }

    public function test_skips_row_when_date_from_is_null(): void
    {
        $period = $this->parser->parse(['143', '10', 'NULL', '2014-01-05']);

        $this->assertNull($period);
    }

    public function test_skips_row_when_date_from_is_unparseable(): void
    {
        $period = $this->parser->parse(['143', '10', 'not-a-date', '2014-01-05']);

        $this->assertNull($period);
    }

    public function test_skips_row_when_date_to_is_unparseable(): void
    {
        $period = $this->parser->parse(['143', '10', '2013-11-01', 'not-a-date']);

        $this->assertNull($period);
    }

    public function test_skips_row_with_missing_columns(): void
    {
        $this->assertNull($this->parser->parse(['143', '10']));
        $this->assertNull($this->parser->parse(['', '10', '2013-11-01', 'NULL']));
    }

    public function test_skips_row_when_date_to_is_before_date_from(): void
    {
        $period = $this->parser->parse(['143', '10', '2020-05-10', '2020-01-01']);

        $this->assertNull($period);
    }

    public function test_allows_date_to_equal_to_date_from(): void
    {
        $period = $this->parser->parse(['143', '10', '2020-05-10', '2020-05-10']);

        $this->assertNotNull($period);
        $this->assertSame('2020-05-10', $period->dateFrom->format('Y-m-d'));
        $this->assertSame('2020-05-10', $period->dateTo->format('Y-m-d'));
    }

    public function test_falls_back_to_another_format_for_a_dirty_row(): void
    {
        // dominant format is Y-m-d, but this row uses day-first slashes
        $period = $this->parser->parse(['143', '10', '05/03/2020', 'NULL']);

        $this->assertNotNull($period);
        $this->assertSame('2020-03-05', $period->dateFrom->format('Y-m-d'));
    }
}
