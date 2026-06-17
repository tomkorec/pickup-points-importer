<?php

namespace App\PickupPoint\Fetcher;

use App\Enum\Carrier;
use App\PickupPoint\Exception\FetcherNotFound;

class FetcherLocator
{
    /**
     * @throws FetcherNotFound
     */
    public function locate(Carrier $carrier): PickupPointFetcher
    {
        throw new FetcherNotFound();
    }

}
