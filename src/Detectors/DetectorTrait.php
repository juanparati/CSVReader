<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Detectors;

use Juanparati\CsvReader\Exceptions\CsvFileException;
use Juanparati\CsvReader\Helpers\EncodingInfo;

/**
 * Common utility methods for CSV detectors.
 *
 * Provides shared functionality for reading sample lines, statistical
 * calculations, and score normalization.
 */
trait DetectorTrait
{
    /**
     * Read sample lines from the CSV file.
     *
     * @param string $filePath Path to the CSV file
     * @param int $maxLines Maximum number of lines to read
     * @param string|null $charset Optional charset for conversion
     * @return array<int, string> Array of non-empty lines
     * @throws CsvFileException If file cannot be opened or read
     */
    protected function readSampleLines(string $filePath, int $maxLines, ?string $charset = null): array
    {
        if (!file_exists($filePath)) {
            throw new CsvFileException("Unable to read CSV file: {$filePath}");
        }

        $fp = fopen($filePath, 'r');
        if ($fp === false) {
            throw new CsvFileException("Unable to open CSV file: {$filePath}");
        }

        try {
            // Skip BOM if present
            $this->skipBom($fp);

            $lines = [];
            $lineCount = 0;

            while ($lineCount < $maxLines && ($line = fgets($fp)) !== false) {
                // Convert charset if needed
                if ($charset !== null && $charset !== 'UTF-8') {
                    $converted = mb_convert_encoding($line, 'UTF-8', $charset);
                    $line = $converted !== false ? $converted : $line;
                }

                // Only include non-empty lines
                if (trim($line) !== '') {
                    $lines[] = rtrim($line, "\r\n");
                    $lineCount++;
                }
            }

            return $lines;
        } finally {
            fclose($fp);
        }
    }

    /**
     * Skip BOM (Byte Order Mark) at the beginning of the file.
     *
     * @param resource $fp File pointer
     * @return void
     */
    protected function skipBom($fp): void
    {
        $position = ftell($fp);
        if ($position === false) {
            return;
        }

        // Read first 4 bytes to detect BOM
        $bytes = fread($fp, 4);
        if ($bytes === false) {
            return;
        }

        $encodingInfo = EncodingInfo::getInfo($bytes);

        // If no BOM detected, rewind to original position
        if (!EncodingInfo::hasBom($bytes)) {
            fseek($fp, $position);
            return;
        }

        // Seek past the BOM
        fseek($fp, $encodingInfo['bom_length']);
    }

    /**
     * Calculate standard deviation of an array of values.
     *
     * @param array<int|float> $values Array of numeric values
     * @return float Standard deviation, or 0 if array is empty
     */
    protected function standardDeviation(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        if ($count === 1) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $squaredDiffs = array_map(fn($value) => ($value - $mean) ** 2, $values);
        $variance = array_sum($squaredDiffs) / $count;

        return sqrt($variance);
    }

    /**
     * Normalize a score to a 0-100 integer range.
     *
     * @param float $score Raw score (typically 0.0 to 1.0)
     * @return int Normalized score (0-100)
     */
    protected function normalizeScore(float $score): int
    {
        return (int) max(0, min(100, round($score * 100)));
    }

    /**
     * Calculate mean of an array of values.
     *
     * @param array<int|float> $values Array of numeric values
     * @return float Mean value, or 0 if array is empty
     */
    protected function mean(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        return array_sum($values) / $count;
    }

    /**
     * Check if a file appears to be binary data.
     *
     * @param string $filePath Path to the file
     * @param int $sampleSize Number of bytes to check
     * @return bool True if file appears to be binary
     */
    protected function isBinaryFile(string $filePath, int $sampleSize = 8192): bool
    {
        $fp = fopen($filePath, 'r');
        if ($fp === false) {
            return false;
        }

        $sample = fread($fp, $sampleSize);
        fclose($fp);

        if ($sample === false || $sample === '') {
            return false;
        }

        // Check for null bytes (common in binary files)
        if (strpos($sample, "\0") !== false) {
            return true;
        }

        // Check ratio of non-printable characters
        $printableCount = 0;
        $totalCount = strlen($sample);

        for ($i = 0; $i < $totalCount; $i++) {
            $ord = ord($sample[$i]);
            // Printable ASCII, tabs, newlines, carriage returns
            if (($ord >= 32 && $ord <= 126) || $ord === 9 || $ord === 10 || $ord === 13 || $ord >= 128) {
                $printableCount++;
            }
        }

        // If less than 85% printable, consider it binary
        return ($printableCount / $totalCount) < 0.85;
    }
}
