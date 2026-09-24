<?php

namespace Tests\Unit\Services;

use App\Services\Csv\CsvReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CsvReaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'csv_reader_test_');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_streams_rows_as_trimmed_arrays(): void
    {
        file_put_contents($this->path, "143, 12, 2013-11-01, 2014-01-05\n218, 10, 2012-05-16, NULL\n");

        $rows = iterator_to_array((new CsvReader())->read($this->path), false);

        $this->assertSame([
            ['143', '12', '2013-11-01', '2014-01-05'],
            ['218', '10', '2012-05-16', 'NULL'],
        ], $rows);
    }

    public function test_skips_blank_lines(): void
    {
        file_put_contents($this->path, "143,12,2013-11-01,2014-01-05\n\n218,10,2012-05-16,NULL\n");

        $rows = iterator_to_array((new CsvReader())->read($this->path), false);

        $this->assertCount(2, $rows);
    }

    public function test_does_not_load_the_whole_file_at_once(): void
    {
        file_put_contents($this->path, "1,2,3,4\n5,6,7,8\n9,10,11,12\n");

        $seen = 0;
        foreach ((new CsvReader())->read($this->path) as $row) {
            $seen++;
            if ($seen === 1) {
                $this->assertSame(['1', '2', '3', '4'], $row);
                break;
            }
        }

        $this->assertSame(1, $seen);
    }

    public function test_throws_when_file_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);

        iterator_to_array((new CsvReader())->read('/nonexistent/path.csv'));
    }
}
