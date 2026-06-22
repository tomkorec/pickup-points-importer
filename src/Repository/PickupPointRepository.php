<?php

namespace App\Repository;

use App\Entity\PickupPoint;
use App\Enum\Carrier;
use App\Enum\PickupPointStatus;
use App\Model\Country;
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

    public function findIdsByCarrierAndCountry(Carrier $carrier, Country $country): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id, p.externalId')
            ->where('p.carrier = :carrier')
            ->andWhere('p.country = :country')
            ->setParameter('country', $country->getCode())
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

    /**
     * Inserts the given pickup points, updating any that already exist (matched on the
     * carrier + externalId + country unique key) in a single batched statement.
     *
     * @param list<PickupPointData> $points
     */
    public function upsert(array $points): void
    {
        if ($points === []) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Columns written on INSERT. `id` is auto-increment; `created` is preserved on update.
        $columns = [
            'externalId', 'carrier', 'type', 'status', 'city', 'name',
            'address', 'zipCode', 'country', 'latitude', 'longitude', 'openingHours', 'created',
        ];

        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $rowPlaceholders = [];
        $params = [];

        foreach ($points as $point) {
            $rowPlaceholders[] = $rowPlaceholder;

            $params[] = $point->id;
            $params[] = $point->carrier->value;
            $params[] = $point->type->value;
            $params[] = $point->status->value;
            $params[] = $point->city;
            $params[] = $point->name;
            $params[] = $point->address;
            $params[] = $point->zipCode;
            $params[] = $point->country;
            $params[] = (string) $point->latitude;
            $params[] = (string) $point->longitude;
            $params[] = $point->openingHours;
            $params[] = $now;
        }

        // carrier/externalId/country form the unique key (unchanged on conflict) and `created`
        // must keep its original value, so none of them is updated. VALUES() is the MariaDB
        // idiom for referencing the would-be-inserted value (deprecated on MySQL >= 8.0.20).
        $updatableColumns = ['type', 'status', 'city', 'name', 'address', 'zipCode', 'latitude', 'longitude', 'openingHours'];

        $updateAssignments = implode(', ', array_map(
            static fn (string $column) => sprintf('`%1$s` = VALUES(`%1$s`)', $column),
            $updatableColumns,
        ));

        $quotedColumns = implode(', ', array_map(
            static fn (string $column) => sprintf('`%s`', $column),
            $columns,
        ));

        // Table name as declared on the PickupPoint entity (#[ORM\Table(name: 'pickup_points')]).
        $sql = sprintf(
            'INSERT INTO pickup_points (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $quotedColumns,
            implode(', ', $rowPlaceholders),
            $updateAssignments,
        );

        $this->getEntityManager()->getConnection()->executeStatement($sql, $params);
    }

}
