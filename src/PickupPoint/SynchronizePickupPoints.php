<?php

namespace App\PickupPoint;

use App\Enum\Carrier;
use App\Model\Country;
use App\PickupPoint\Fetcher\FetchConfig;
use App\PickupPoint\Fetcher\FetcherLocator;
use App\Repository\PickupPointRepository;

/**
 * Synchronizes fetched pickup point data with the database for a given carrier and country.
 * - Inserts new pickup points and updates existing ones in a single upsert, matched on the
 *   carrier + externalId + country unique key.
 * - Marks pickup points that are no longer present in the fetched data as terminated.
 */
readonly class SynchronizePickupPoints
{
    private const int BATCH_COUNT = 50;

    public function __construct(
        private PickupPointRepository $pickupPointRepository,
        private FetcherLocator        $fetcherLocator,
    ) {}

    public function __invoke(Carrier $carrier, Country $country): void
    {
        $fetcher = $this->fetcherLocator->locate($carrier);

        $existingPickupPoints = $this->pickupPointRepository->findIdsByCarrierAndCountry($carrier, $country);

        $fetchedExternalIds = [];
        $batch = [];

        foreach ($fetcher->fetch(new FetchConfig($country)) as $pickupPointData) {
            $fetchedExternalIds[] = $pickupPointData->id;
            $batch[] = $pickupPointData;

            if (count($batch) >= self::BATCH_COUNT) {
                $this->pickupPointRepository->upsert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->pickupPointRepository->upsert($batch);
        }

        // Any pickup points that exist in the database but are no longer present in the
        // fetched data are marked as terminated.
        $idsToTerminate = [];

        foreach ($existingPickupPoints as $existingPickupPoint) {
            if (!in_array($existingPickupPoint['externalId'], $fetchedExternalIds, true)) {
                $idsToTerminate[] = $existingPickupPoint['id'];
            }
        }

        $this->pickupPointRepository->terminatePickupPoints($idsToTerminate);
    }
}
