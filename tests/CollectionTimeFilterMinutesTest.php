<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use LaravelCollectionTimeFilter\CollectionTimeFilterMinutes;
use LaravelCollectionTimeFilter\Contracts\HasTime;
use LaravelCollectionTimeFilter\DateWrapper;
use PHPUnit\Framework\TestCase;

class CollectionTimeFilterMinutesTest extends TestCase
{
    private function getFilter(Collection $collection, int $requiredInterval = 30, int $existingInterval = 5, bool $withNulls = false)
    {
        return new CollectionTimeFilterMinutes($collection, $requiredInterval, $existingInterval, $withNulls);
    }

    public function test_an_empty_collection_will_not_be_processed()
    {
        $collection = new Collection();

        $filter = $this->getFilter($collection);

        $this->assertCount($collection->count(), $filter->getFilteredCollection());
    }

    public function test_a_collection_with_x_minute_intervals_can_be_transformed_into_a_collection_of_y_minute_intervals_when_the_time_is_exact()
    {
        //this test also proves that exact values are selected over values further away even if they are found
        //this is also tested in a separate test below
        //e.g. 0320, 0325, 0330, 0335 will select 0330 if the interval is set to 30 minutes

        $allowedIntervals = [5, 10, 30, 60, 120, 240, 480];

        foreach ($allowedIntervals as $index => $existingInterval) {
            if (isset($allowedIntervals[$index + 1])) {
                $requiredInterval = $allowedIntervals[$index + 1];

                $items = new Collection(array_map(function ($count) use ($existingInterval) {
                    return new DateWrapper(Carbon::now()->startOfDay()->addMinutes($count * $existingInterval));
                }, range(0, (24 * (60 / $existingInterval)) - 1)));

                $filter = $this->getFilter($items, $requiredInterval, $existingInterval);

                $filtered = $filter->getFilteredCollection();

                $this->assertCount(24 * 60 / $requiredInterval, $filtered);

                $filtered->each(function (HasTime $time, int $index) use ($requiredInterval) {
                    $this->assertTrue($time->getTime()->eq(Carbon::now()->startOfDay()->addMinutes($index * $requiredInterval)));
                });
            }
        }
    }

    public function test_if_a_collection_is_incomplete_the_items_will_be_inserted_into_their_correct_positions_based_on_the_required_interval()
    {
        $existingInterval = 5;
        $requiredInterval = 30;

        //remove the last half-interval as these would never be processed
        //e.g. 23:45 is ok as it is only 15 mins away from 23:30 but 23:50 is not
        $maxIntervals = (60 * 24) / 5;

        for ($i = 0; $i < $maxIntervals; $i++) {
            $minutes = $existingInterval * $i;

            $time = Carbon::now()->startOfDay()->addMinutes($minutes);

            $collection = new Collection([new DateWrapper($time)]);

            $filter = $this->getFilter($collection, $requiredInterval, $existingInterval);

            $filtered = $filter->getFilteredCollection()->filter();

            $this->assertCount(1, $filtered);

            //assert that the set value is stored in the right place in the array
            /* @var Carbon $time */
            $time = $filtered->first()->getTime();

            $this->assertEquals(floor($time->diffInMinutes($time->clone()->startOfDay()) / $requiredInterval), $filtered->keys()->first());
        }
    }

    public function test_dates_can_be_written_if_there_is_no_acceptable_value_for_a_given_interval()
    {
        $requiredInterval = 30;

        $collection = new Collection([new DateWrapper(Carbon::now()->startOfDay())]);

        $filter = $this->getFilter($collection, $requiredInterval, 5, true);

        $filtered = $filter->getFilteredCollection();

        $this->assertCount(24 * (60 / $requiredInterval), $filtered);

        $filtered->each(function (HasTime $time, int $index) use ($requiredInterval) {
            $this->assertTrue($time->getTime()->eq(Carbon::now()->startOfDay()->addMinutes($requiredInterval * $index)));
        });
    }

    public function test_if_the_interval_is_1440_the_collection_will_return_the_first_item()
    {
        $interval = 1440;

        $first = new DateWrapper(Carbon::now()->setTime(23, 30, 0));

        $collection = new Collection([
            $first,
            new DateWrapper((clone $first->getTime())->addMinute()),
            new DateWrapper((clone $first->getTime())->addMinutes(2)),
        ]);

        $filter = $this->getFilter($collection, $interval, 5, true);

        $filtered = $filter->getFilteredCollection();

        $this->assertCount(1, $filtered);
        $this->assertSame($first, $filtered->first());
    }

    public function test_the_filter_will_filter_records_correctly()
    {
        $collection = new Collection([
            new DateWrapper(Carbon::now()->setTime(0, 2, 28)),
            new DateWrapper(Carbon::now()->setTime(0, 7, 28)),
            new DateWrapper(Carbon::now()->setTime(0, 12, 28)),
            new DateWrapper(Carbon::now()->setTime(0, 17, 28)),
            new DateWrapper(Carbon::now()->setTime(0, 22, 28))
        ]);

        $filter = $this->getFilter($collection, 5, 5, false);
        $filtered = $filter->getFilteredCollection()->filter();

        $this->assertCount(5, $filtered);
    }
}
