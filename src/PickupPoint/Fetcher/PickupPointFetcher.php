<?php

namespace App\PickupPoint\Fetcher;

use App\Enum\Carrier;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;


#[AutoconfigureTag(PickupPointFetcher::FETCHER_TAG)]
interface PickupPointFetcher
{
    public const string FETCHER_TAG = 'app.pickup_point_fetcher';

    /**
     * @return iterable<PickupPointData>
     */
    public function fetch(?FetchConfig $config = null): iterable;

    public function carrier(): Carrier;

}
