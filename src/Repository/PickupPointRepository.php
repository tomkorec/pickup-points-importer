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

    public function updateExistingPickupPoint(int $existingId, PickupPointData $data): void
    {
        $this->createQueryBuilder('p')
            ->update()
            ->set('p.externalId', ':externalId')
            ->set('p.carrier', ':carrier')
            ->set('p.type', ':type')
            ->set('p.status', ':status')
            ->set('p.city', ':city')
            ->set('p.name', ':name')
            ->set('p.address', ':address')
            ->set('p.zipCode', ':zipCode')
            ->set('p.country', ':country')
            ->set('p.latitude', ':latitude')
            ->set('p.longitude', ':longitude')
            ->set('p.openingHours', ':openingHours')
            ->where('p.id = :id')
            ->setParameter('id', $existingId)
            ->setParameter('externalId', $data->id)
            ->setParameter('carrier', $data->carrier->value)
            ->setParameter('type', $data->type)
            ->setParameter('status', $data->status)
            ->setParameter('city', $data->city)
            ->setParameter('name', $data->name)
            ->setParameter('address', $data->address)
            ->setParameter('zipCode', $data->zipCode)
            ->setParameter('country', $data->country)
            ->setParameter('latitude', $data->latitude)
            ->setParameter('longitude', $data->longitude)
            ->setParameter('openingHours', $data->openingHours)
            ->getQuery()
            ->execute();
    }

}
