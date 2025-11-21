<?php

namespace Juanparati\CsvReader\Contracts;

use Juanparati\CsvReader\FieldMaps\CsvFieldAuto;

interface CsvFieldMap extends \JsonSerializable
{
    /**
     * Set replacement rule.
     *
     * @param string|int|float $replace
     * @param string|int|float $by
     * @return $this
     */
    public function setReplaceRule(string|int|float $replace, string|int|float $by): static;


    /**
     * Set custom transformations.
     *
     * @param mixed[] $transforms
     * @return $this
     */
    public function setTransforms(array $transforms): static;


    /**
     * Set removal rule.
     *
     * @param string|int|float|callable|array $remove
     * @return $this
     */
    public function setRemoveRule(string|int|float|callable|array $remove): static;


    /**
     * Set an exclusion rule.
     *
     * @param mixed[] $exclusions
     * @return $this
     */
    public function setExclusionRule(mixed $exclusions): static;


    /**
     * Set filter rule.
     *
     * @param mixed $filter
     * @return $this
     */
    public function setFilterRule(mixed $filter): static;


    /**
     * Check if the value is filtered.
     *
     * @param mixed $value
     * @return bool
     */
    public function shouldBeFiltered(mixed $value): bool;


    /**
     * Check if the value must be flagged as excluded.
     *
     * @param mixed $value
     * @return bool
     */
    public function shouldBeExclude(mixed $value): bool;


    /**
     * Transform the value.
     *
     * @param mixed $value
     * @return mixed
     */
    public function transform(mixed $value): mixed;


    /**
     * Factory method that creates a filed map from array settings.
     *
     * @param array $settings
     * @return CsvFieldAuto
     */
    public static function make(array $settings): CsvFieldMap;
}
