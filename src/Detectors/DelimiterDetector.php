<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Detectors;

use Juanparati\CsvReader\CsvReader;
use Juanparati\CsvReader\Exceptions\CsvFileException;

/**
 * Detects the delimiter character used in a CSV file.
 *
 * Uses frequency analysis and consistency checking to identify
 * the most likely delimiter from common candidates.
 */
class DelimiterDetector
{
    use DetectorTrait;

    /**
     * Candidate delimiters to test.
     */
    private const array CANDIDATES = [
        CsvReader::DELIMITER_SEMICOLON,
        CsvReader::DELIMITER_COMMA,
        CsvReader::DELIMITER_PIPE,
        CsvReader::DELIMITER_TAB,
        CsvReader::DELIMITER_CARET,
        CsvReader::DELIMITER_AMPERSAND,
    ];

    /**
     * Scoring weights for delimiter detection.
     */
    private const float CONSISTENCY_WEIGHT = 0.6;
    private const float FREQUENCY_WEIGHT = 0.3;
    private const float UNIVERSAL_WEIGHT = 0.1;

    private string $filePath;
    private int $maxSampleLines;
    private int $minConfidence;
    private ?string $charset;

    /** @var array<int, string> */
    private array $sampleLines = [];

    /** @var array<string, array{score: float, consistency: float, frequency: float, universal: float, confidence: int}> */
    private array $scores = [];

    /**
     * Create a new delimiter detector.
     *
     * @param string $filePath Path to the CSV file
     * @param int $maxSampleLines Maximum lines to sample
     * @param int $minConfidence Minimum confidence threshold (0-100)
     * @param string|null $charset Optional charset for reading the file
     */
    public function __construct(
        string $filePath,
        int $maxSampleLines = 20,
        int $minConfidence = 70,
        ?string $charset = null
    ) {
        $this->filePath = $filePath;
        $this->maxSampleLines = $maxSampleLines;
        $this->minConfidence = $minConfidence;
        $this->charset = $charset;
    }

    /**
     * Detect the delimiter character.
     *
     * @return string The detected delimiter
     * @throws CsvFileException If delimiter cannot be detected with sufficient confidence
     */
    public function detect(): string
    {
        $this->sampleLines = $this->readSampleLines($this->filePath, $this->maxSampleLines, $this->charset);

        if (empty($this->sampleLines)) {
            throw new CsvFileException("Unable to detect delimiter: File is empty or unreadable");
        }

        $this->scores = $this->analyzeDelimiters();

        if (empty($this->scores)) {
            // In case that delimiter is not found, default to semicolon
            $bestDelimiter = CsvReader::DELIMITER_SEMICOLON;
        } else {
            $bestDelimiter = array_key_first($this->scores);
        }

        return $bestDelimiter;
    }

    /**
     * Get the confidence score for the detected delimiter.
     *
     * @return int Confidence score (0-100)
     */
    public function getConfidence(): int
    {
        if (empty($this->scores)) {
            return 0;
        }

        return $this->scores[array_key_first($this->scores)]['confidence'];
    }

    /**
     * Get all scores for debugging purposes.
     *
     * @return array<string, array{score: float, consistency: float, frequency: float, universal: float, confidence: int}>
     */
    public function getAllScores(): array
    {
        return $this->scores;
    }

    /**
     * Analyze all candidate delimiters and calculate scores.
     *
     * @return array<string, array{score: float, consistency: float, frequency: float, universal: float, confidence: int}>
     */
    private function analyzeDelimiters(): array
    {
        $scores = [];

        foreach (self::CANDIDATES as $delimiter) {
            $delimiterScore = $this->analyzeDelimiter($delimiter);

            if ($delimiterScore !== null) {
                $scores[$delimiter] = $delimiterScore;
            }
        }

        // Sort by score descending
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scores;
    }

    /**
     * Analyze a specific delimiter candidate.
     *
     * @param string $delimiter The delimiter to analyze
     * @return array{score: float, consistency: float, frequency: float, universal: float, confidence: int}|null
     */
    private function analyzeDelimiter(string $delimiter): ?array
    {
        $counts = [];

        foreach ($this->sampleLines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $counts[] = substr_count($line, $delimiter);
        }

        if (empty($counts)) {
            return null;
        }

        // Calculate consistency score (low standard deviation is good)
        $avgCount = $this->mean($counts);

        // If delimiter never appears, it could be a single-column CSV
        // Return a neutral score for this case
        if ($avgCount === 0.0) {
            return [
                'score' => 0.5,
                'consistency' => 1.0,
                'frequency' => 0.0,
                'universal' => 1.0,
                'confidence' => 50,
            ];
        }

        $stdDev = $this->standardDeviation($counts);
        $consistency = 1.0 - min(1.0, $stdDev / max($avgCount, 1.0));

        // Calculate frequency score (normalized by line length)
        $avgLineLength = $this->mean(array_map('strlen', $this->sampleLines));
        $frequency = min(1.0, $avgCount / max($avgLineLength, 1.0));

        // Calculate universal score (appears in all lines)
        $linesWithDelimiter = count(array_filter($counts, fn($c) => $c > 0));
        $universal = $linesWithDelimiter / count($counts);

        // Combined score with weights
        $score = ($consistency * self::CONSISTENCY_WEIGHT)
            + ($frequency * self::FREQUENCY_WEIGHT)
            + ($universal * self::UNIVERSAL_WEIGHT);

        return [
            'score' => $score,
            'consistency' => $consistency,
            'frequency' => $frequency,
            'universal' => $universal,
            'confidence' => $this->normalizeScore($score),
        ];
    }

    /**
     * Get a human-readable name for a delimiter.
     *
     * @param string $delimiter The delimiter character
     * @return string Human-readable name
     */
    private function getDelimiterName(string $delimiter): string
    {
        return match ($delimiter) {
            CsvReader::DELIMITER_SEMICOLON => 'semicolon',
            CsvReader::DELIMITER_COMMA => 'comma',
            CsvReader::DELIMITER_PIPE => 'pipe',
            CsvReader::DELIMITER_TAB => 'tab',
            CsvReader::DELIMITER_CARET => 'caret',
            CsvReader::DELIMITER_AMPERSAND => 'ampersand',
            default => "'{$delimiter}'",
        };
    }
}
