<?php

namespace App\PickupPoint;

use App\Enum\Carrier;
use App\Factory\PickupPointFactory;
use App\Model\Country;
use App\PickupPoint\Fetcher\FetchConfig;
use App\PickupPoint\Fetcher\FetcherLocator;
use App\PickupPoint\Fetcher\PickupPointData;
use App\Repository\PickupPointRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service is responsible for synchronizing fetched pickup point data with the database.
 * - It updates existing pickup points if they already exist in the database.
 * - It creates new pickup points if they do not exist in the database.
 * - It marks pickup points as terminated if they are no longer present in the fetched data.
 */
readonly class SynchronizePickupPoints
{
    private const int BATCH_COUNT = 50;

    public function __construct(
        private PickupPointRepository  $pickupPointRepository,
        private FetcherLocator         $fetcherLocator,
        private PickupPointFactory     $factory,
        private EntityManagerInterface $em
    ) {}

    public function __invoke(Carrier $carrier, Country $country): void
    {
        $fetcher = $this->fetcherLocator->locate($carrier);

        $data = $fetcher->fetch(
            new FetchConfig($country)
        );

        $existingPickupPoints = $this->pickupPointRepository->findIdsByCarrierAndCountry($carrier, $country);

        $existingByExternalId = [];

        foreach ($existingPickupPoints as $point) {
            $existingByExternalId[$point['externalId']] = $point['id'];
        }

        /**
         * @var array<int> $fetchedPickupPointIds
         */
        $fetchedPickupPointIds = [];

        $i = 0;

        /**
         * @var PickupPointData $pickupPointData
         */
        foreach ($data as $pickupPointData) {
            $fetchedPickupPointIds[] = $pickupPointData->id;

            if (!array_key_exists($pickupPointData->id, $existingByExternalId)) {
                $pickupPoint = $this->factory->createFromData($pickupPointData);

                $this->em->persist($pickupPoint);

                if (++$i % self::BATCH_COUNT === 0) {
                    $this->em->flush();
                    $this->em->clear();
                }

                continue;
            }

            // find the existing pickup point's entity ID using the external ID
            $existingId = $existingByExternalId[$pickupPointData->id];

            $this->pickupPointRepository->updateExistingPickupPoint($existingId, $pickupPointData);
        }

        $this->em->flush();

        $pickupPointsToTerminate = [];

        // Any pickup points that exist in the database but are not present in the fetched data should be marked as terminated.
        foreach ($existingPickupPoints as $existingPickupPoint) {
            if (!in_array($existingPickupPoint['externalId'], $fetchedPickupPointIds, true)) {
                $pickupPointsToTerminate[] = $existingPickupPoint['id'];
            }
        }

        $this->pickupPointRepository->terminatePickupPoints($pickupPointsToTerminate);
    }
}
