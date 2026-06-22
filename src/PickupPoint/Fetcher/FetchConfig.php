<?php

namespace App\PickupPoint\Fetcher;

use App\Model\Country;

/**
 * Configuration for fetching pickup points.
 */
final readonly class FetchConfig
{
    public function __construct(
        public Country $country,
    ) {}

}
