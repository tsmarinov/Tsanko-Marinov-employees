<?php

namespace App\Services;

use DateTimeImmutable;
use RuntimeException;

class DateFormatDetector
{
    public const NULL_TOKEN = 'NULL';

    /**
     * Candidate formats, ordered by tie-break preference (day-first wins
     * ambiguous numeric dates, e.g. "05/03/2020").
     *
     * @var array<int, string>
     */
    private const CANDIDATE_FORMATS = [
        'Y-m-d',
        'd/m/Y',
        'd-m-Y',
        'd.m.Y',
        'Y/m/d',
        'Y.m.d',
        'm/d/Y',
        'm-d-Y',
        'd M Y',
        'd F Y',
        'M d, Y',
        'Ymd',
    ];

    /**
     * Detect the most likely date format from a sample of raw CSV values.
     *
     * @param  iterable<string|null>  $samples
     */
    public function detect(iterable $samples): string
    {
        $scores = array_fill_keys(self::CANDIDATE_FORMATS, 0);
        $total = 0;

        foreach ($samples as $value) {
            $value = $this->normalize($value);

            if ($value === null) {
                continue;
            }

            $total++;

            foreach (self::CANDIDATE_FORMATS as $format) {
                if ($this->matchesFormat($value, $format)) {
                    $scores[$format]++;
                }
            }
        }

        if ($total === 0) {
            throw new RuntimeException('Cannot detect date format: no usable sample values were provided.');
        }

        arsort($scores);
        $format = array_key_first($scores);

        if ($scores[$format] === 0) {
            throw new RuntimeException('Cannot detect date format: none of the known formats match the sample values.');
        }

        return $format;
    }

    /**
     * Parse a raw value against the detected format, falling back to any
     * other known format for rows that don't follow the file's dominant
     * format. Returns null when the value is empty, "NULL", or unparseable.
     */
    public function tryParse(?string $value, string $format): ?DateTimeImmutable
    {
        $value = $this->normalize($value);

        if ($value === null) {
            return null;
        }

        foreach ([$format, ...self::CANDIDATE_FORMATS] as $candidate) {
            if ($this->matchesFormat($value, $candidate)) {
                $parsed = DateTimeImmutable::createFromFormat('!'.$candidate, $value);

                return $parsed !== false ? $parsed : null;
            }
        }

        return null;
    }

    public function isNullToken(?string $value): bool
    {
        return $value !== null && strtoupper(trim($value)) === self::NULL_TOKEN;
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || $this->isNullToken($value)) {
            return null;
        }

        return $value;
    }

    private function matchesFormat(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        if ($date === false) {
            return false;
        }

        return $date->format($format) === $value;
    }
}
