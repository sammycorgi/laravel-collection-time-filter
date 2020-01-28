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
        $divisors = [5, 6, 8, 9, 10, 12, 15, 16, 18, 20, 24, 30, 32, 36, 40, 45, 48, 60, 72, 80, 90, 96, 120, 144, 160, 180, 240, 288, 360, 480, 720];

        $closest = null;

        foreach ($divisors as $divisor) {
            if ($closest === null || abs($this->requiredIntervalInMinutes - $closest) > abs($divisor - $this->requiredIntervalInMinutes)) {
                $closest = $divisor;
            }
        }

        return $closest;
    }

    /**
     * Find the first item in the collection that is x minutes away from a given time
     * Where x is the current interval
     *
     * Returns false if no item is found
     *
     * @param Carbon $time
     * @return int | false
     */
    protected function findFirstItemThatIsWithinIntervalOfTime(Carbon $time)
    {
        $first = $this->collection->first();

        $index = false;

        //collection has been emptied
        if($first === null) {
            return $index;
        }

        //check in the first item in the collection exceeds the current time by too much
        //i.e. should be skipped
        if($first->getTime()->gt((clone $time)->addMinutes($this->requiredIntervalInMinutes / 2))) {
            return $index;
        }

        for($i = 1; $i <= 3; $i++) {
            //otherwise search the remaining collection for the first item that
            //lies an increasingly larger interval
            $index = $this->collection->search(function(HasTime $item) use ($time, $i) {
                $future = (clone $time)->addMinutes($this->existingIntervalInMinutes * $i);

                $past = (clone $time)->subMinutes($this->existingIntervalInMinutes * $i);

                return $item->getTime()->between($past, $future);
            });

            if($index !== false) {
                return $index;
            }
        }

        return $index;
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

        /** @var HasTime $firstItem */
        $firstItem = $this->collection->first();
        $firstTime = clone $firstItem->getTime();

        $currentTime = (clone $firstTime)->startOfDay();

        $maxIntervals = static::HOURS_PER_DAY * (static::MINUTES_PER_HOUR / $this->requiredIntervalInMinutes);

        for($intervals = 0; $intervals < $maxIntervals; $intervals++) {
            $key = $this->findFirstItemThatIsWithinIntervalOfTime($currentTime);

            $found = null;

            //key is false if none is found, otherwise is int
            if($key === false) {
                if($this->shouldWriteNullValues) {
                    $found = $this->getWriteNullValuesObject(clone $currentTime);
                }
            } else {
                $foundIndex = $this->findIndexOfPointWithImprovedAccuracy($currentTime, $key);

                //keep searching the array to see if there is a closer time to the one previously found
                while($key !== $foundIndex) {
                    $key = $foundIndex;

                    $foundIndex = $this->findIndexOfPointWithImprovedAccuracy($currentTime, $key);
                }

                $found = $this->collection[$foundIndex];

                $this->forgetKeys($foundIndex);
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
     * Checks if there is a closer point to be found than the one previously found
     *
     * @param Carbon $expectedTime
     * @param int $firstIndex
     * @return int
     */
    protected function findIndexOfPointWithImprovedAccuracy(Carbon $expectedTime, int $firstIndex) : int
    {
        $first = $this->collection[$firstIndex];

        //if this is the only item in the collection return it
        if($this->collection->count() === 1) {
            return 0;
        }

        $isInFuture = $first->getTime()->gt($expectedTime);

        //if the item in the collection is after the expected time
        if($isInFuture) {
            //if the item is the first in the collection return it
            if($firstIndex === 0) {
                return 0;
            }

            //check to see if the previous item in the collection is closer
            $secondIndex = $firstIndex - 1;
        } else {
            //otherwise check the next item
            $secondIndex = $firstIndex + 1;
        }

        if(!isset($this->collection[$secondIndex])) {
            return $firstIndex;
        }

        $second = $this->collection[$secondIndex];

        //check which of the 2 is closer to the actual time
        $firstDiff = $first->getTime()->diffInSeconds($expectedTime);
        $secondDiff = $second->getTime()->diffInSeconds($expectedTime);

        //if the values are identical (rare)
        if($firstDiff === $secondDiff) {
            return $secondIndex > $firstIndex ? $secondIndex : $firstIndex;
        }

        $firstIsCloser = $secondDiff > $firstDiff;
        return $firstIsCloser ? $firstIndex : $secondIndex;
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