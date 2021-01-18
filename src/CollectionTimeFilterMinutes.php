<?php

namespace LaravelCollectionTimeFilter;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use LaravelCollectionTimeFilter\Contracts\HasTime;

class CollectionTimeFilterMinutes
{
    public const MINUTES_PER_DAY = 1440;
    public const MINUTES_PER_HOUR = 60;
    public const HOURS_PER_DAY = 24;

    /**
     * @var Collection
     */
    protected Collection $collection;

    /**
     * The required interval between
     * the collection items in minutes
     *
     * @var int
     */
    protected int $requiredIntervalInMinutes;

    /**
     * The actual expected interval between
     * the collection items in minutes
     *
     * @var int
     */
    protected int $existingIntervalInMinutes;

    /**
     * Whether the filter should write the expected time
     * whenever it doesn't exist in the collection
     *
     * @var bool
     */
    protected bool $shouldWriteNullValues = false;

    /**
     * The collection of filtered objects
     *
     * @var Collection
     */
    protected Collection $filtered;

    /**
     * CollectionTimeFilter constructor.
     * @param Collection $collection
     * @param int $requiredIntervalInMinutes
     * @param int $existingIntervalInMinutes
     * @param bool $shouldWriteNullValues
     */
    public function __construct(Collection $collection, int $requiredIntervalInMinutes, int $existingIntervalInMinutes, bool $shouldWriteNullValues = false)
    {
        //clone the collection so the values are not changed if needed elsewhere
        //this resets the keys but this is needed for faster searching
        $this->collection = $collection->values();

        $this->requiredIntervalInMinutes = $requiredIntervalInMinutes;
        $this->existingIntervalInMinutes = $existingIntervalInMinutes;
        $this->shouldWriteNullValues = $shouldWriteNullValues;

        $this->filtered = $this->getNewCollection();
    }

    /**
     * Return a new collection of the same type
     * this class was instantiated with
     *
     * @param array $items
     * @return Collection
     */
    protected function getNewCollection(array $items = []) : Collection
    {
        $class = get_class($this->collection);

        return new $class($items);
    }

    /**
     * Determine the closest divisor of minutes per day
     *
     * Used if the input interval is not a divisor of minutes per day
     *
     * @return int
     */
    protected function determineClosestInterval(): int
    {
        $divisors = [5, 6, 8, 9, 10, 12, 15, 16, 18, 20, 24, 30, 32, 36, 40, 45, 48, 60, 72, 80, 90, 96, 120, 144, 160, 180, 240, 288, 360, 480, 720, 1440];

        $closest = null;

        foreach ($divisors as $divisor) {
            if ($closest === null || abs($this->requiredIntervalInMinutes - $closest) > abs($divisor - $this->requiredIntervalInMinutes)) {
                $closest = $divisor;
            }
        }

        return $closest;
    }

    /**
     * Find the item in the collection that is closest to a given time
     *
     * @param Carbon $currentTime
     * @param Carbon $nextTime
     * @return int | false
     */
    protected function findFirstItemThatIsWithinIntervalOfTime(Carbon $currentTime, Carbon $nextTime)
    {
        /* @var DateWrapper $first */
        $first = $this->collection->first();

        $c = $currentTime->clone();
        $n = $nextTime->clone();

        //collection has been emptied
        if($first === null) {
            return false;
        }

        //check in the first item in the collection exceeds the current time by too much
        //i.e. should be skipped
        if($first->getTime()->gte($n)) {
            return false;
        }

        /* @var DateWrapper $item */
        foreach($this->collection as $key => $item) {
            if($item->getTime()->betweenIncluded($c, $n)) {
                return $key;
            }

            if($item->getTime()->gte($n)) {
                return false;
            }
        }

        return false;
    }

    /**
     * @return Collection
     */
    public function getFilteredCollection(): Collection
    {
        if($this->filtered->count() === 0) {
            $this->run();
        }

        return $this->filtered;
    }

    /**
     * Filters the collection down to x minute intervals
     */
    protected function run() : void
    {
        //no date is present in the collection
        //so we are unable to process it
        if($this->collection->count() === 0) {
            return;
        }

        //if the required interval is shorter than the actual interval, this can't be processed
        //so set the required interval to the existing interval
        if ($this->requiredIntervalInMinutes < $this->existingIntervalInMinutes) {
            $this->requiredIntervalInMinutes = $this->existingIntervalInMinutes;
        }

        //determine the closest workable interval from the interval specified if the specified interval is unworkable
        if (static::MINUTES_PER_DAY % $this->requiredIntervalInMinutes) {
            $this->requiredIntervalInMinutes = $this->determineClosestInterval();
        }

        //if only need 1 item, grab the first item and finish processing
        if($this->requiredIntervalInMinutes === static::MINUTES_PER_DAY) {
            $this->filtered->push($this->collection->first());

            return;
        }

        /** @var HasTime $firstItem */
        $firstItem = $this->collection->first();

        //midnight of first time
        $currentTime = $firstItem->getTime()->clone()->startOfDay();

        $maxIntervals = static::HOURS_PER_DAY * (static::MINUTES_PER_HOUR / $this->requiredIntervalInMinutes);

        for($intervals = 0; $intervals < $maxIntervals; $intervals++) {
            $key = $this->findFirstItemThatIsWithinIntervalOfTime($currentTime, $currentTime->clone()->addMinutes($this->requiredIntervalInMinutes));

            $found = null;

            //key is false if none is found, otherwise is int
            if($key === false) {
                if($this->shouldWriteNullValues) {
                    $found = $this->getWriteNullValuesObject($currentTime->clone());
                }
            } else {
                $found = $this->collection[$key];

                $this->forgetKeys($key);
            }

            $this->filtered->push($found);

            $currentTime->addMinutes($this->requiredIntervalInMinutes);
        }
    }

    /**
     * Forget all items from the array before a given key
     *
     * Will forget all keys from 0...n
     *
     * Will also reset the keys
     *
     * @param int $max
     */
    protected function forgetKeys(int $max) : void
    {
        $this->collection = $this->collection->forget(range(0, $max))->values();
    }

    /**
     * Get the value to add to the array if no suitable item was found
     *
     * @param Carbon $time
     * @return HasTime
     */
    protected function getWriteNullValuesObject(Carbon $time) : HasTime
    {
        return new DateWrapper($time);
    }
}