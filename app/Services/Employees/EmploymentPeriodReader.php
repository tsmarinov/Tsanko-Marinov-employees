<?php

namespace App\Services\Employees;

use App\DataObjects\EmploymentPeriod;
use App\Services\Csv\CsvReader;
use App\Services\DateFormatDetector;
use Generator;

class EmploymentPeriodReader
{
    public function __construct(
        private readonly CsvReader $csv,
        private readonly DateFormatDetector $dates,
        private readonly int $sampleSize = 200,
    ) {
    }

    /**
     * Stream valid employment periods from a CSV file. The date format is
     * detected once from a small sample, then reused for every row; rows
     * that fail to parse under any known format are skipped.
     *
     * @return Generator<int, EmploymentPeriod>
     */
    public function read(string $path): Generator
    {
        $format = $this->dates->detect($this->dateSamples($path));
        $parser = new EmploymentPeriodParser($this->dates, $format);

        foreach ($this->csv->read($path) as $row) {
            $period = $parser->parse($row);

            if ($period !== null) {
                yield $period;
            }
        }
    }

    /**
     * @return Generator<int, string|null>
     */
    private function dateSamples(string $path): Generator
    {
        $count = 0;

        foreach ($this->csv->read($path) as $row) {
            if ($count >= $this->sampleSize) {
                break;
            }

            yield $row[2] ?? null;
            yield $row[3] ?? null;

            $count++;
        }
    }
}
