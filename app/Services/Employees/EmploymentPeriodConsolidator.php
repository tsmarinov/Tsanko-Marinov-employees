<?php

namespace App\Services\Employees;

use Illuminate\Support\Facades\DB;

class EmploymentPeriodConsolidator
{
    /**
     * Collapse overlapping/touching employment periods for the same
     * employee and project into single continuous periods, entirely in
     * SQL via a "gaps and islands" window-function query, and store the
     * result in `merged_employment_periods`.
     *
     * Uses a running MAX(date_to) over all preceding rows in the partition
     * (not just the immediately previous row) so a nested period, e.g.
     * [1-20] followed by [5-8] followed by [10-12], correctly collapses
     * into one period instead of being split by the shorter row in between.
     *
     * @return int number of merged periods written
     */
    public function consolidate(): int
    {
        DB::table('merged_employment_periods')->delete();

        DB::statement(<<<'SQL'
            INSERT INTO merged_employment_periods (emp_id, project_id, date_from, date_to)
            WITH bounded AS (
                SELECT
                    emp_id,
                    project_id,
                    date_from,
                    date_to,
                    MAX(date_to) OVER (
                        PARTITION BY emp_id, project_id
                        ORDER BY date_from, date_to
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ) AS running_max_to
                FROM employment_periods
            ),
            flagged AS (
                SELECT
                    emp_id, project_id, date_from, date_to,
                    SUM(CASE WHEN running_max_to IS NULL OR date_from > running_max_to THEN 1 ELSE 0 END)
                        OVER (
                            PARTITION BY emp_id, project_id
                            ORDER BY date_from, date_to
                            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) AS island
                FROM bounded
            )
            SELECT emp_id, project_id, MIN(date_from), MAX(date_to)
            FROM flagged
            GROUP BY emp_id, project_id, island
        SQL);

        return DB::table('merged_employment_periods')->count();
    }
}
