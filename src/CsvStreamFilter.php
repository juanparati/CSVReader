<?php

declare(strict_types=1);

namespace Juanparati\CSVReader;

final class CsvStreamFilter
{
    protected mixed $fp;

    /**
     * Set filters used by the CSV reader.
     *
     * @param string $filter
     * @param mixed|null $params
     */
    public function __construct(protected string $filter, protected mixed $params = null)
    {
    }


    /**
     * Set file pointer.
     *
     * @param mixed $fp
     * @return CsvStreamFilter
     */
    public function setFp(mixed $fp)
    {
        if ($fp === false) {
            throw new \RuntimeException('Cannot set filter on a closed stream');
        }

        $this->fp = $fp;

        return $this;
    }


    /**
     * Apply stream filter.
     *
     * @return false|resource
     */
    public function apply()
    {
        return stream_filter_append($this->fp, $this->filter, STREAM_FILTER_READ, $this->params);
    }

}
