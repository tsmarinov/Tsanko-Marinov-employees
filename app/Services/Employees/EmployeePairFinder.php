<?php

namespace App\Services\Employees;

use Illuminate\Support\Facades\DB;

class EmployeePairFinder
{
    /**
     * Find the pair of employees who worked together the longest overall,
     * unioning their overlapping periods across all shared projects (not
     * summing — a pair overlapping simultaneously on two projects would
     * otherwise double-count those days), plus a per-project breakdown for
     * that pair. Returns null if no two employees ever shared an
     * overlapping period on any project.
     *
     * @return array{empA: int, empB: int, totalDays: int, breakdown: array<int, array{projectId: int, days: int}>}|null
     */
    public function findWinningPair(): ?array
    {
        $winner = DB::selectOne(<<<'SQL'
            WITH pairwise_overlaps AS (
                SELECT
                    a.emp_id AS emp_a,
                    b.emp_id AS emp_b,
                    max(a.date_from, b.date_from) AS overlap_from,
                    min(a.date_to, b.date_to) AS overlap_to
                FROM merged_employment_periods a
                JOIN merged_employment_periods b
                    ON a.project_id = b.project_id
                    AND a.emp_id < b.emp_id
                    AND a.date_from <= b.date_to
                    AND b.date_from <= a.date_to
            ),
            -- UNION, not sum: this pair's overlap windows are merged across
            -- ALL shared projects here (partitioned by emp_a/emp_b only, no
            -- project_id). If they overlapped simultaneously on two projects,
            -- summing each project's days would double-count those days;
            -- unioning first means each calendar day counts once no matter
            -- how many projects it came from.
            bounded AS (
                SELECT emp_a, emp_b, overlap_from, overlap_to,
                    MAX(overlap_to) OVER (
                        PARTITION BY emp_a, emp_b
                        ORDER BY overlap_from, overlap_to
                        ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                    ) AS running_max_to
                FROM pairwise_overlaps
            ),
            flagged AS (
                SELECT emp_a, emp_b, overlap_from, overlap_to,
                    SUM(CASE WHEN running_max_to IS NULL OR overlap_from > running_max_to THEN 1 ELSE 0 END)
                        OVER (
                            PARTITION BY emp_a, emp_b
                            ORDER BY overlap_from, overlap_to
                            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                        ) AS island
                FROM bounded
            ),
            unioned_islands AS (
                SELECT emp_a, emp_b, MIN(overlap_from) AS date_from, MAX(overlap_to) AS date_to
                FROM flagged
                GROUP BY emp_a, emp_b, island
            )
            SELECT emp_a, emp_b,
                -- Day-count is SQLite's julianday(); MySQL: DATEDIFF(date_to, date_from) + 1;
                -- Postgres: (date_to - date_from) + 1 (native date subtraction already yields days).
                CAST(SUM(julianday(date_to) - julianday(date_from) + 1) AS INTEGER) AS total_days
            FROM unioned_islands
            GROUP BY emp_a, emp_b
            ORDER BY total_days DESC
            LIMIT 1
        SQL);

        if ($winner === null) {
            return null;
        }

        $breakdown = DB::select(<<<'SQL'
            SELECT
                a.project_id AS project_id,
                -- SUM here is safe (unlike the winner query above): this is
                -- scoped to ONE project, and this pair's overlap windows
                -- within a single project can never overlap each other
                -- (each side's own periods are already non-overlapping), so
                -- there's nothing to double-count.
                -- Same portability note as above: julianday() is SQLite-specific.
                CAST(SUM(julianday(min(a.date_to, b.date_to)) - julianday(max(a.date_from, b.date_from)) + 1) AS INTEGER) AS days
            FROM merged_employment_periods a
            JOIN merged_employment_periods b
                ON a.project_id = b.project_id
                AND a.emp_id = ? AND b.emp_id = ?
                AND a.date_from <= b.date_to
                AND b.date_from <= a.date_to
            GROUP BY a.project_id
            ORDER BY a.project_id
        SQL, [$winner->emp_a, $winner->emp_b]);

        return [
            'empA' => (int) $winner->emp_a,
            'empB' => (int) $winner->emp_b,
            'totalDays' => (int) $winner->total_days,
            'breakdown' => array_map(
                static fn (object $row): array => [
                    'projectId' => (int) $row->project_id,
                    'days' => (int) $row->days,
                ],
                $breakdown
            ),
        ];
    }
}
