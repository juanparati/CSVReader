<?php

declare(strict_types=1);

namespace Juanparati\CsvReader;

use Juanparati\CsvReader\Detectors\CharsetDetector;
use Juanparati\CsvReader\Detectors\DelimiterDetector;
use Juanparati\CsvReader\Detectors\DetectorTrait;
use Juanparati\CsvReader\Detectors\EnclosureDetector;
use Juanparati\CsvReader\Detectors\EscapeCharDetector;
use Juanparati\CsvReader\Exceptions\CsvFileException;

/**
 * Main CSV auto-detection orchestrator.
 *
 * Coordinates individual detectors to identify delimiter, enclosure,
 * escape character, and charset. Returns a CsvFormat object with
 * detected properties and confidence scores.
 */
class CsvAutoDetector
{
    use DetectorTrait;

    private string $filePath;
    private int $maxSampleLines;
    private int $minConfidence;

    /** @var array<string, int> */
    private array $confidenceScores = [];

    /**
     * Create a new CSV auto-detector.
     *
     * @param string $filePath Path to the CSV file
     * @param int $maxSampleLines Maximum number of lines to sample (default: 20)
     * @param int $minConfidence Minimum confidence threshold 0-100 (default: 70)
     */
    public function __construct(
        string $filePath,
        int    $maxSampleLines = 20,
        int    $minConfidence = 70
    )
    {
        $this->filePath       = $filePath;
        $this->maxSampleLines = $maxSampleLines;
        $this->minConfidence  = $minConfidence;
    }

    /**
     * Detect all CSV format properties.
     *
     * Detection order:
     * 1. Charset (affects how a file is read)
     * 2. Delimiter (needed for enclosure detection)
     * 3. Enclosure (needed for escape detection)
     * 4. Escape character
     *
     * @return array[delimiter:string, enclosure:string, escapeChar:string, charset:string, confidence:array] Detected CSV format properties and confidence scores
     * @throws CsvFileException If any detection fails below a confidence threshold
     */
    public function detect(): array
    {
        // Step 1: Detect charset first (affects all subsequent operations)
        $charset = $this->detectCharset();

        // Step 2: Detect delimiter (needed for enclosure detection)
        $delimiter = $this->detectDelimiter();

        // Step 3: Detect enclosure (needed for escape detection)
        $enclosure = $this->detectEnclosure();

        // Step 4: Detect escape character
        $escapeChar = $this->detectEscapeChar();

        return [
            'delimiter'  => $delimiter,
            'enclosure'  => $enclosure,
            'escapeChar' => $escapeChar,
            'charset'    => $charset,
            'confidence' => $this->confidenceScores
        ];
    }

    /**
     * Detect only the delimiter.
     *
     * @return string The detected delimiter
     * @throws CsvFileException If delimiter cannot be detected
     */
    public function detectDelimiter(): string
    {
        // Detect charset first if not already done
        $charset = $this->confidenceScores['charset'] ?? null
            ? null
            : $this->detectCharset();

        $detector = new DelimiterDetector(
            $this->filePath,
            $this->maxSampleLines,
            $this->minConfidence,
            $charset
        );

        $delimiter                           = $detector->detect();
        $this->confidenceScores['delimiter'] = $detector->getConfidence();

        return $delimiter;
    }

    /**
     * Detect only the enclosure character.
     *
     * @return string The detected enclosure
     * @throws CsvFileException
     */
    public function detectEnclosure(): string
    {
        // Ensure charset and delimiter are detected first
        $charset = $this->confidenceScores['charset'] ?? null
            ? null
            : $this->detectCharset();

        if (!isset($this->confidenceScores['delimiter'])) {
            $delimiter = $this->detectDelimiter();
        } else {
            // Delimiter already detected, need to get it again
            $delimiterDetector = new DelimiterDetector(
                $this->filePath,
                $this->maxSampleLines,
                0, // Don't throw on low confidence
                $charset
            );
            try {
                $delimiter = $delimiterDetector->detect();
            } catch (CsvFileException $e) {
                // Use a default if detection failed
                $delimiter = ',';
            }
        }

        $detector = new EnclosureDetector(
            $this->filePath,
            $delimiter,
            $this->maxSampleLines,
            $charset
        );

        $enclosure                           = $detector->detect();
        $this->confidenceScores['enclosure'] = $detector->getConfidence();

        return $enclosure;
    }

    /**
     * Detect only the escape character.
     *
     * @return string The detected escape character
     * @throws CsvFileException
     */
    public function detectEscapeChar(): string
    {
        // Ensure charset and enclosure are detected first
        $charset = $this->confidenceScores['charset'] ?? null
            ? null
            : $this->detectCharset();

        if (!isset($this->confidenceScores['enclosure'])) {
            $enclosure = $this->detectEnclosure();
        } else {
            // Enclosure already detected, need to get it again
            $enclosureDetector = new EnclosureDetector(
                $this->filePath,
                ',', // Placeholder will use a detected delimiter if available
                $this->maxSampleLines,
                $charset
            );
            $enclosure         = $enclosureDetector->detect();
        }

        $detector = new EscapeCharDetector(
            $this->filePath,
            $enclosure,
            $this->maxSampleLines,
            $charset
        );

        $escapeChar                           = $detector->detect();
        $this->confidenceScores['escapeChar'] = $detector->getConfidence();

        return $escapeChar;
    }

    /**
     * Detect only the charset.
     *
     * @return string The detected charset
     * @throws CsvFileException If charset cannot be detected (e.g., binary file)
     */
    public function detectCharset(): string
    {
        $detector                          = new CharsetDetector($this->filePath);
        $charset                           = $detector->detect();
        $this->confidenceScores['charset'] = $detector->getConfidence();

        return $charset;
    }

    /**
     * Get confidence scores for all detected properties.
     *
     * @return array<string, int> Confidence scores (0-100) keyed by property name
     */
    public function getConfidenceScores(): array
    {
        return $this->confidenceScores;
    }
}
