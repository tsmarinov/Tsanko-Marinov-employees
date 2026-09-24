<?php

namespace App\Services\Csv;

use Generator;
use RuntimeException;

class CsvReader
{
    public function __construct(
        private readonly string $delimiter = ',',
        private readonly string $enclosure = '"',
        private readonly string $escape = '\\',
    ) {
    }

    /**
     * Stream a CSV file row by row without loading it into memory.
     *
     * @return Generator<int, array<int, string>>
     */
    public function read(string $path): Generator
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Unable to open CSV file: {$path}");
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: {$path}");
        }

        try {
            while (($row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
                if ($row === [null]) {
                    continue;
                }

                yield array_map(static fn ($field) => trim((string) $field), $row);
            }
        } finally {
            fclose($handle);
        }
    }
}
