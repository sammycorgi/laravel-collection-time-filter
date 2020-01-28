<?php

namespace LaravelCollectionTimeFilter\Contracts;

use Carbon\Carbon;

interface HasTime
{
    /**
     * Get the carbon instance associated with this object
     *
     * @return Carbon
     */
    public function getTime() : Carbon;
}