<?php

namespace LaravelCollectionTimeFilter;

use Carbon\Carbon;
use LaravelCollectionTimeFilter\Contracts\HasTime;

/**
 * A wrapper for the Carbon class that implements the HasCarbon interface
 *
 * Used for when no value is found in the filter and null
 * values need to be written for whatever reason
 *
 * Class DateWrapper
 * @package LaravelCollectionTimeFilter
 */
class DateWrapper implements HasTime
{
    /**
     * @var Carbon
     */
    private Carbon $date;

    /**
     * DateWrapper constructor.
     * @param Carbon $date
     */
    public function __construct(Carbon $date)
    {
        $this->date = $date;
    }

    /**
     * @inheritDoc
     */
    public function getTime(): Carbon
    {
        return $this->date;
    }
}