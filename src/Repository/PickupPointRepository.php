<?php

namespace App\Repository;

use App\Entity\PickupPoint;
use App\Enum\Carrier;
use App\Enum\PickupPointStatus;
use App\PickupPoint\Fetcher\PickupPointData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PickupPoint>
 */
class PickupPointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PickupPoint::class);
    }

    public function findIdsByCarrier(Carrier $carrier): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id, p.externalId')
            ->where('p.carrier = :carrier')
            ->setParameter('carrier', $carrier);

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param array<int> $pickupPointsToTerminate
     * @return void
     */
    public function terminatePickupPoints(array $pickupPointsToTerminate): void
    {
        if (empty($pickupPointsToTerminate)) {
            return;
        }

        $qb = $this->createQueryBuilder('p')
            ->update()
            ->set('p.status', ':status')
            ->where('p.id IN (:ids)')
            ->setParameter('status', PickupPointStatus::TERMINATED->value)
            ->setParameter('ids', $pickupPointsToTerminate);

        $qb->getQuery()->execute();
    }

    public function updatePickupPointFromData(PickupPointData $pickupPointData): void
    {
        $qb = $this->createQueryBuilder('p')
            ->update()
            ->set('p.name', ':name')
            ->set('p.address', ':address')
            ->set('p.city', ':city')
            ->set('p.postalCode', ':postalCode')
            ->set('p.country', ':country')
            ->where('p.id = :id')
            ->setParameter('name', $pickupPointData->name)
            ->setParameter('address', $pickupPointData->address)
            ->setParameter('city', $pickupPointData->city)
            ->setParameter('zipCode', $pickupPointData->zipCode)
            ->setParameter('country', $pickupPointData->country)
            ->setParameter('id', $pickupPointData->id);

        $qb->getQuery()->execute();
    }


}
