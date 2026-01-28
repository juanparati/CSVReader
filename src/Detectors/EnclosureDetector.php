<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\Detectors;

use Juanparati\CsvReader\CsvReader;

/**
 * Detects the enclosure character used in a CSV file.
 *
 * Uses paired character analysis to identify quote characters
 * or determine if no enclosure is used.
 */
class EnclosureDetector
{
    use DetectorTrait;

    /**
     * Candidate enclosure characters to test.
     */
    private const array CANDIDATES = [
        CsvReader::ENCLOSURE_QUOTES,
        CsvReader::ENCLOSURE_TILDES,
        CsvReader::ENCLOSURE_NONE,
    ];

    /**
     * Scoring weights for enclosure detection.
     */
    private const float BALANCE_WEIGHT = 0.5;
    private const float POSITIONAL_WEIGHT = 0.3;
    private const float FREQUENCY_WEIGHT = 0.2;

    private string $filePath;
    private int $maxSampleLines;
    private ?string $charset;
    private string $delimiter;

    /** @var array<int, string> */
    private array $sampleLines = [];

    /** @var array<string, array{score: float, balance: float, positional: float, frequency: float, confidence: int}> */
    private array $scores = [];

    /**
     * Create a new enclosure detector.
     *
     * @param string $filePath Path to the CSV file
     * @param string $delimiter The detected delimiter (needed for positional analysis)
     * @param int $maxSampleLines Maximum lines to sample
     * @param string|null $charset Optional charset for reading the file
     */
    public function __construct(
        string $filePath,
        string $delimiter,
        int $maxSampleLines = 20,
        ?string $charset = null
    ) {
        $this->filePath = $filePath;
        $this->delimiter = $delimiter;
        $this->maxSampleLines = $maxSampleLines;
        $this->charset = $charset;
    }

    /**
     * Detect the enclosure character.
     *
     * @return string The detected enclosure (defaults to ENCLOSURE_NONE if uncertain)
     */
    public function detect(): string
    {
        $this->sampleLines = $this->readSampleLines($this->filePath, $this->maxSampleLines, $this->charset);

        if (empty($this->sampleLines)) {
            return CsvReader::ENCLOSURE_NONE;
        }

        $this->scores = $this->analyzeEnclosures();

        if (empty($this->scores)) {
            return CsvReader::ENCLOSURE_NONE;
        }

        // Get the highest scoring enclosure
        $bestEnclosure = array_key_first($this->scores);

        // Default to no enclosure if confidence is very low
        if ($this->scores[$bestEnclosure]['confidence'] < 30) {
            return CsvReader::ENCLOSURE_NONE;
        }

        return $bestEnclosure;
    }

    /**
     * Get the confidence score for the detected enclosure.
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
     * @return array<string, array{score: float, balance: float, positional: float, frequency: float, confidence: int}>
     */
    public function getAllScores(): array
    {
        return $this->scores;
    }

    /**
     * Analyze all candidate enclosures and calculate scores.
     *
     * @return array<string, array{score: float, balance: float, positional: float, frequency: float, confidence: int}>
     */
    private function analyzeEnclosures(): array
    {
        $scores = [];

        foreach (self::CANDIDATES as $enclosure) {
            $enclosureScore = $this->analyzeEnclosure($enclosure);

            if ($enclosureScore !== null) {
                $scores[$enclosure] = $enclosureScore;
            }
        }

        // Sort by score descending
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scores;
    }

    /**
     * Analyze a specific enclosure candidate.
     *
     * @param string $enclosure The enclosure to analyze
     * @return array{score: float, balance: float, positional: float, frequency: float, confidence: int}|null
     */
    private function analyzeEnclosure(string $enclosure): ?array
    {
        // Special case: no enclosure
        if ($enclosure === CsvReader::ENCLOSURE_NONE) {
            return $this->analyzeNoEnclosure();
        }

        $balanceScores = [];
        $positionalScores = [];
        $totalEnclosures = 0;

        foreach ($this->sampleLines as $line) {
            if (trim($line) === '') {
                continue;
            }

            // Count occurrences
            $count = substr_count($line, $enclosure);
            $totalEnclosures += $count;

            // Check balance (even number indicates paired enclosures)
            $isBalanced = ($count % 2) === 0;
            $balanceScores[] = $isBalanced ? 1.0 : 0.0;

            // Check positional correctness
            $positionalScore = $this->checkPositionalCorrectness($line, $enclosure);
            $positionalScores[] = $positionalScore;
        }

        if (empty($balanceScores)) {
            return null;
        }

        // Calculate balance score (percentage of balanced lines)
        $balance = $this->mean($balanceScores);

        // Calculate positional score
        $positional = $this->mean($positionalScores);

        // Calculate frequency score (normalized)
        $avgLineLength = $this->mean(array_map('strlen', $this->sampleLines));
        $avgEnclosuresPerLine = $totalEnclosures / count($this->sampleLines);
        $frequency = min(1.0, $avgEnclosuresPerLine / max($avgLineLength, 1.0) * 10);

        // Combined score with weights
        $score = ($balance * self::BALANCE_WEIGHT)
            + ($positional * self::POSITIONAL_WEIGHT)
            + ($frequency * self::FREQUENCY_WEIGHT);

        return [
            'score' => $score,
            'balance' => $balance,
            'positional' => $positional,
            'frequency' => $frequency,
            'confidence' => $this->normalizeScore($score),
        ];
    }

    /**
     * Analyze the case where no enclosure is used.
     *
     * @return array{score: float, balance: float, positional: float, frequency: float, confidence: int}
     */
    private function analyzeNoEnclosure(): array
    {
        // Check if delimiters appear unenclosed (suggesting no enclosure is needed)
        $unenclosedDelimiters = 0;
        $totalLines = 0;

        foreach ($this->sampleLines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $totalLines++;

            // If line contains delimiter and no quotes/tildes, it's likely unenclosed
            if (
                strpos($line, $this->delimiter) !== false &&
                strpos($line, '"') === false &&
                strpos($line, '~') === false
            ) {
                $unenclosedDelimiters++;
            }
        }

        $score = $totalLines > 0 ? $unenclosedDelimiters / $totalLines : 0.5;

        return [
            'score' => $score,
            'balance' => 1.0,
            'positional' => $score,
            'frequency' => 0.0,
            'confidence' => $this->normalizeScore($score),
        ];
    }

    /**
     * Check if enclosures appear in expected positions.
     *
     * Enclosures should typically appear:
     * - At the start of a line
     * - Immediately after a delimiter
     * - In balanced pairs
     *
     * @param string $line The line to check
     * @param string $enclosure The enclosure character
     * @return float Score between 0.0 and 1.0
     */
    private function checkPositionalCorrectness(string $line, string $enclosure): float
    {
        $positions = [];
        $offset = 0;

        // Find all positions of the enclosure
        while (($pos = strpos($line, $enclosure, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + 1;
        }

        if (empty($positions)) {
            return 0.5; // Neutral score if no enclosures
        }

        $correctPositions = 0;

        foreach ($positions as $pos) {
            // Check if at start of line
            if ($pos === 0) {
                $correctPositions++;
                continue;
            }

            // Check if immediately after delimiter
            if ($pos > 0 && $line[$pos - 1] === $this->delimiter) {
                $correctPositions++;
                continue;
            }

            // Check if immediately before delimiter
            if ($pos < strlen($line) - 1 && $line[$pos + 1] === $this->delimiter) {
                $correctPositions++;
                continue;
            }
        }

        return count($positions) > 0 ? $correctPositions / count($positions) : 0.0;
    }
}
