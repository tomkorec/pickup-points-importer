<?php

namespace App\PickupPoint;

use App\Entity\PickupPoint;
use App\Enum\Carrier;
use App\Factory\PickupPointFactory;
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
    public function __construct(
        private PickupPointRepository  $pickupPointRepository,
        private FetcherLocator         $fetcherLocator,
        private PickupPointFactory     $factory,
        private EntityManagerInterface $em
    ) {}

    public function __invoke(Carrier $carrier): void
    {
        $fetcher = $this->fetcherLocator->locate($carrier);

        $data = $fetcher->fetch();

        $existingPickupPointIds = $this->pickupPointRepository->findIdsByCarrier($carrier);

        /**
         * @var array<int> $fetchedPickupPointIds
         */
        $fetchedPickupPointIds = [];

        /**
         * @var PickupPointData $pickupPointData
         */
        foreach ($data as $pickupPointData) {
            $fetchedPickupPointIds[] = $pickupPointData->id;

            if (!in_array($pickupPointData->id, $existingPickupPointIds['externalId'], true)) {
                $pickupPoint = $this->factory->createFromData($pickupPointData);

                $this->em->persist($pickupPoint);

                continue;
            }

            $existingId = array_search(
                $pickupPointData->id,
                array_column($existingPickupPointIds, 'externalId'),
                true,
            );

            $existingPickupPoint = $this->em->getReference(PickupPoint::class, $existingId);

            $this->factory->updateFromData($existingPickupPoint, $pickupPointData);
        }

        // Mark pickup points as terminated if they are no longer present in the fetched data
        $pickupPointsToTerminate = array_diff($existingPickupPointIds, $fetchedPickupPointIds);

        $this->pickupPointRepository->terminatePickupPoints($pickupPointsToTerminate);
    }
}
