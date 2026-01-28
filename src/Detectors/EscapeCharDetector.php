<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Detectors;

use Juanparati\CsvReader\CsvReader;

/**
 * Detects the escape character used in a CSV file.
 *
 * Uses pattern recognition to identify backslash or double-quote
 * escape sequences. Defaults to backslash if no clear pattern.
 */
class EscapeCharDetector
{
    use DetectorTrait;

    /**
     * Candidate escape characters to test.
     */
    private const array CANDIDATES = [
        '\\',      // Backslash (most common)
        '"',       // Double quote (for escaping quotes as "")
    ];

    private string $filePath;
    private int $maxSampleLines;
    private ?string $charset;
    private string $enclosure;

    /** @var array<int, string> */
    private array $sampleLines = [];

    /** @var array<string, array{score: float, patterns: int, confidence: int}> */
    private array $scores = [];

    /**
     * Create a new escape character detector.
     *
     * @param string $filePath Path to the CSV file
     * @param string $enclosure The detected enclosure character
     * @param int $maxSampleLines Maximum lines to sample
     * @param string|null $charset Optional charset for reading the file
     */
    public function __construct(
        string $filePath,
        string $enclosure,
        int $maxSampleLines = 20,
        ?string $charset = null
    ) {
        $this->filePath = $filePath;
        $this->enclosure = $enclosure;
        $this->maxSampleLines = $maxSampleLines;
        $this->charset = $charset;
    }

    /**
     * Detect the escape character.
     *
     * @return string The detected escape character (defaults to backslash)
     */
    public function detect(): string
    {
        // If no enclosure is used, escape characters are typically not relevant
        if ($this->enclosure === CsvReader::ENCLOSURE_NONE) {
            return '\\';
        }

        $this->sampleLines = $this->readSampleLines($this->filePath, $this->maxSampleLines, $this->charset);

        if (empty($this->sampleLines)) {
            return '\\';
        }

        $this->scores = $this->analyzeEscapeChars();

        if (empty($this->scores)) {
            return '\\';
        }

        // Get the highest scoring escape character
        $bestEscape = array_key_first($this->scores);

        // Default to backslash if confidence is very low
        if ($this->scores[$bestEscape]['confidence'] < 30) {
            return '\\';
        }

        return $bestEscape;
    }

    /**
     * Get the confidence score for the detected escape character.
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
     * @return array<string, array{score: float, patterns: int, confidence: int}>
     */
    public function getAllScores(): array
    {
        return $this->scores;
    }

    /**
     * Analyze all candidate escape characters and calculate scores.
     *
     * @return array<string, array{score: float, patterns: int, confidence: int}>
     */
    private function analyzeEscapeChars(): array
    {
        $scores = [];

        foreach (self::CANDIDATES as $escapeChar) {
            $escapeScore = $this->analyzeEscapeChar($escapeChar);

            if ($escapeScore !== null) {
                $scores[$escapeChar] = $escapeScore;
            }
        }

        // Sort by score descending
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scores;
    }

    /**
     * Analyze a specific escape character candidate.
     *
     * @param string $escapeChar The escape character to analyze
     * @return array{score: float, patterns: int, confidence: int}|null
     */
    private function analyzeEscapeChar(string $escapeChar): ?array
    {
        $patternCount = 0;
        $totalChars = 0;

        foreach ($this->sampleLines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $totalChars += strlen($line);

            // Look for escape patterns
            $patterns = $this->findEscapePatterns($line, $escapeChar);
            $patternCount += $patterns;
        }

        if ($totalChars === 0) {
            return null;
        }

        // Calculate score based on pattern frequency
        $score = min(1.0, ($patternCount / $totalChars) * 100);

        // Boost score for backslash as it's the most common
        if ($escapeChar === '\\') {
            $score *= 1.2; // 20% boost for backslash
        }

        // Boost score for double quote if enclosure is also double quote
        if ($escapeChar === '"' && $this->enclosure === CsvReader::ENCLOSURE_QUOTES) {
            $score *= 1.1; // 10% boost for matching quote
        }

        // Cap at 1.0
        $score = min(1.0, $score);

        return [
            'score' => $score,
            'patterns' => $patternCount,
            'confidence' => $this->normalizeScore($score),
        ];
    }

    /**
     * Find escape patterns in a line.
     *
     * @param string $line The line to analyze
     * @param string $escapeChar The escape character to look for
     * @return int Number of valid escape patterns found
     */
    private function findEscapePatterns(string $line, string $escapeChar): int
    {
        $count = 0;
        $length = strlen($line);

        if ($escapeChar === '\\') {
            // Look for backslash escape patterns: \", \n, \r, \t, \\
            $patterns = [
                '\\' . $this->enclosure,
                '\\n',
                '\\r',
                '\\t',
                '\\\\',
            ];

            foreach ($patterns as $pattern) {
                $count += substr_count($line, $pattern);
            }
        } elseif ($escapeChar === '"' && $this->enclosure === CsvReader::ENCLOSURE_QUOTES) {
            // Look for doubled quote pattern: ""
            for ($i = 0; $i < $length - 1; $i++) {
                if ($line[$i] === '"' && $line[$i + 1] === '"') {
                    $count++;
                    $i++; // Skip the next quote
                }
            }
        }

        return $count;
    }
}
