<?php

namespace App\Services\Employees;

use App\DataObjects\EmploymentPeriod;
use App\Services\DateFormatDetector;
use DateTimeImmutable;

class EmploymentPeriodParser
{
    public function __construct(
        private readonly DateFormatDetector $dates,
        private readonly string $dateFormat,
    ) {
    }

    /**
     * Turn a raw CSV row into an EmploymentPeriod, or null if the row must
     * be skipped: missing columns, a NULL DateFrom, a DateFrom/DateTo that
     * doesn't parse as a date at all, or a DateTo before DateFrom.
     *
     * @param  array<int, string|null>  $row
     */
    public function parse(array $row): ?EmploymentPeriod
    {
        [$empId, $projectId, $dateFrom, $dateTo] = array_pad($row, 4, null);

        if ($empId === null || $empId === '' || $projectId === null || $projectId === '') {
            return null;
        }

        if ($this->dates->isNullToken($dateFrom)) {
            return null;
        }

        $from = $this->dates->tryParse($dateFrom, $this->dateFormat);

        if ($from === null) {
            return null;
        }

        $to = $this->dates->isNullToken($dateTo)
            ? new DateTimeImmutable('today')
            : $this->dates->tryParse($dateTo, $this->dateFormat);

        if ($to === null || $to < $from) {
            return null;
        }

        return new EmploymentPeriod((int) $empId, (int) $projectId, $from, $to);
    }
}
