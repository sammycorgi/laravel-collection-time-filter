# laravel-collection-time-filter
Filters laravel collections with time to have a certain interval

Currently can filter down a collection from 24 hours worth of points to any required interval in minutes of 12 hours or less, as long as the interval is a divisor of 24 hours.

# Installation

`composer require sammycorgi/laravel-collection-time-filter`

# Usage

Pass a collection of `LaravelCollectionTimeFilter\HasTime` objects to a new instance of a `LaravelCollectionTimeFilter\CollectionTimeFilterMinutes`, along with the required interval in minutes and the existing interval. The existing interval isn't too important it just ensures that the required interval is larger.

Please note, the input collection **must** be sorted by time ascending.

Call `getFilteredCollection()` on this object to filter down the results to the required interval.

This will find the closest value to the given interval, starting at 00:00 and ending at 00:00 the following day minus your required interval, for a total of `24 * intervalsPerHour` intervals.

## Return Type

By default, the returned collection type will be the same as the one that the filter was instantiated with. To change this behaviour, simply extend the class and amend the `getNewCollection()` method.

## 'Null' values

If the filter cannot find a suitable time for a given interval, it will by default insert a null value into the returned array.

This can be changed to insert the interval time at this point by passing `true` as the final argument in the constructor. 

By default, this will be an instance of a `LaravelCollectionTimeFilter\DateWrapper` object. Extend the class and amend the `getWriteNullValuesObject()` method to change this behaviour. It must still return a class that implements the `LaravelCollectionTimeFilter\Contracts\HasTime` interface.
