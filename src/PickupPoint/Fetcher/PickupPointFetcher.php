<?php

namespace App\PickupPoint\Fetcher;

interface PickupPointFetcher
{
    /**
     * @return iterable<PickupPointData>
     */
    public function fetch(): iterable;
}
