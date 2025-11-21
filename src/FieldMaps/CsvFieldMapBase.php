<?php

declare(strict_types=1);

namespace Juanparati\CsvReader\FieldMaps;

use Juanparati\CsvReader\Contracts\CsvFieldMap;

abstract class CsvFieldMapBase implements CsvFieldMap
{
    /**
     * Replacement rules.
     *
     * @var array
     */
    protected array $replacements = [];

    /**
     * Transformation rules.
     *
     * @var array
     */
    protected array $transforms = [];

    /**
     * Removal rules.
     *
     * @var array
     */
    protected array $removals = [];

    /**
     * Exclusion rules.
     *
     * @var array
     */
    protected array $exclusions = [];

    /**
     * Filter rules.
     * @var array
     */
    protected array $filters = [];


    /**
     * Constructor.
     *
     * @param string|int $srcField
     */
    public function __construct(
        public string|int $srcField,
    ) {
    }


    /**
     * Create a new instance from settings.
     *
     * @param array $settings
     * @return CsvFieldMap
     * @throws \ReflectionException
     */
    public static function make(array $settings): CsvFieldMap
    {
        unset($settings['class']);

        $instance = new static($settings['srcField']);
        unset($settings['srcField']);

        $refClass = new \ReflectionClass($instance);

        foreach ($settings as $key => $value) {
            $prop = $refClass->getProperty($key);
            $prop->setAccessible(true);
            $prop->setValue($instance, $value);
        }

        return $instance;
    }


    /**
     * Set replacement rule.
     *
     * @param string|int|float $replace
     * @param string|int|float $by
     * @return $this
     */
    public function setReplaceRule(string|int|float $replace, string|int|float $by): static
    {
        $this->replacements[$replace] = $by;
        return $this;
    }


    /**
     * Set custom transformations.
     *
     * @param mixed[] $transforms
     * @return $this
     */
    public function setTransforms(array $transforms): static
    {
        $this->transforms = $transforms;
        return $this;
    }


    /**
     * Set removal rule.
     *
     * @param string|int|float|callable|array $remove
     * @return $this
     */
    public function setRemoveRule(string|int|float|callable|array $remove): static
    {
        $this->removals[] = array_merge($this->removals, is_array($remove) ? $remove : [$remove]);
        return $this;
    }

    /**
     * Set an exclusion rule.
     *
     * @param mixed[] $exclusions
     * @return $this
     */
    public function setExclusionRule(mixed $exclusions): static
    {
        $this->exclusions[] = $exclusions;
        return $this;
    }

    /**
     * Set filter rule.
     *
     * @param mixed $filter
     * @return $this
     */
    public function setFilterRule(mixed $filter): static
    {
        $this->filters[] = $filter;
        return $this;
    }


    /**
     * Check if the value is filtered.
     *
     * @param mixed $value
     * @return bool
     */
    public function shouldBeFiltered(mixed $value): bool
    {
        foreach ($this->filters as $filter) {
            if (is_callable($filter) && ($filter($value))) {
                return true;
            }

            if (is_array($filter) && in_array($value, $filter)) {
                return true;
            }

            if ($value === $filter) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the value must be flagged as excluded.
     *
     * @param mixed $value
     * @return bool
     */
    public function shouldBeExclude(mixed $value): bool
    {
        foreach ($this->exclusions as $exclusion) {
            if (is_callable($exclusion) && ($exclusion($value))) {
                return true;
            }

            if (is_array($exclusion) && in_array($value, $exclusion)) {
                return true;
            }

            if ($value === $exclusion) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transform the value.
     *
     * @param mixed $value
     * @return mixed
     */
    public function transform(mixed $value): mixed
    {
        // Note: Do not use array_reduce, it seems it's slower than foreach.
        foreach ($this->replacements as $replace => $by) {
            $value = str_replace($replace, $by, $value);
        }

        foreach ($this->removals as $remove) {
            $value = str_replace($remove, '', $value);
        }

        foreach ($this->transforms as $transform) {
            if (is_callable($transform)) {
                $value = $transform($value);
            } else {
                $value = $transform;
            }
        }

        return $value;
    }


    /**
     * Serialize the object for export.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'class'        => static::class,
            'srcField'     => $this->srcField,
            'replacements' => $this->replacements,
            'transforms'   => $this->transforms,
            'removals'     => $this->removals,
            'exclusions'   => $this->exclusions,
            'filters'      => $this->filters,
        ];
    }

}
