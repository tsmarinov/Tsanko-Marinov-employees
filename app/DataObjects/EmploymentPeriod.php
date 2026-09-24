<?php

namespace App\DataObjects;

use DateTimeImmutable;

final class EmploymentPeriod
{
    public function __construct(
        public readonly int $empId,
        public readonly int $projectId,
        public readonly DateTimeImmutable $dateFrom,
        public readonly DateTimeImmutable $dateTo,
    ) {
    }
}
