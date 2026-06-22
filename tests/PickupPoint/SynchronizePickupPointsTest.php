<?php

declare(strict_types=1);

namespace App\Tests\PickupPoint;

use App\Enum\Carrier;
use App\Enum\PickupPointStatus;
use App\Enum\PickupPointType;
use App\Model\Country;
use App\PickupPoint\Fetcher\FetchConfig;
use App\PickupPoint\Fetcher\FetcherLocator;
use App\PickupPoint\Fetcher\PickupPointData;
use App\PickupPoint\Fetcher\PickupPointFetcher;
use App\PickupPoint\SynchronizePickupPoints;
use App\Repository\PickupPointRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests the reconciliation logic of the generic synchronization flow, independently of any carrier
 * or database. The fetcher is a hand-written fake yielding preset data, wrapped in a real
 * FetcherLocator; the repository is a mock used as a spy on the upsert/terminate calls.
 */
final class SynchronizePickupPointsTest extends TestCase
{
    public function testUpsertsNewlyFetchedPoints(): void
    {
        $repository = $this->createMock(PickupPointRepository::class);
        $repository->method('findIdsByCarrierAndCountry')->willReturn([]);
        $repository->expects($this->once())
            ->method('upsert')
            ->with(self::callback(static fn(array $batch): bool => count($batch) === 1 && $batch[0]->id === 'A'));
        $repository->expects($this->once())->method('terminatePickupPoints')->with([]);

        $synchronizer = $this->synchronizer($repository, $this->fetcherYielding([$this->point('A')]));

        $synchronizer(Carrier::GLS, new Country('CZ'));
    }

    public function testUpdatesExistingPointsWithoutTerminatingThem(): void
    {
        $repository = $this->createMock(PickupPointRepository::class);
        $repository->method('findIdsByCarrierAndCountry')->willReturn([
            ['id' => 1, 'externalId' => 'A'],
        ]);
        $repository->expects($this->once())
            ->method('upsert')
            ->with(self::callback(static fn(array $batch): bool => count($batch) === 1 && $batch[0]->id === 'A'));
        // 'A' is present in the feed, so nothing is terminated.
        $repository->expects($this->once())->method('terminatePickupPoints')->with([]);

        $synchronizer = $this->synchronizer($repository, $this->fetcherYielding([$this->point('A')]));

        $synchronizer(Carrier::GLS, new Country('CZ'));
    }

    public function testTerminatesPointsMissingFromTheFeed(): void
    {
        $repository = $this->createMock(PickupPointRepository::class);
        $repository->method('findIdsByCarrierAndCountry')->willReturn([
            ['id' => 1, 'externalId' => 'A'],
            ['id' => 2, 'externalId' => 'B'],
        ]);
        $repository->expects($this->once())->method('upsert');
        // 'B' exists in the database but is absent from the feed, so its id is terminated.
        $repository->expects($this->once())->method('terminatePickupPoints')->with([2]);

        $synchronizer = $this->synchronizer($repository, $this->fetcherYielding([$this->point('A')]));

        $synchronizer(Carrier::GLS, new Country('CZ'));
    }

    public function testScopesLookupAndFetchToTheRequestedCarrierAndCountry(): void
    {
        $country = new Country('CZ');

        $repository = $this->createMock(PickupPointRepository::class);
        $repository->expects($this->once())
            ->method('findIdsByCarrierAndCountry')
            ->with(Carrier::GLS, self::identicalTo($country))
            ->willReturn([]);

        $fetcher = $this->fetcherYielding([$this->point('A')]);
        $synchronizer = $this->synchronizer($repository, $fetcher);

        $synchronizer(Carrier::GLS, $country);

        self::assertSame($country, $fetcher->receivedConfig?->country);
    }

    public function testUpsertsInBatches(): void
    {
        // BATCH_COUNT is 50, so 120 fetched points => upsert runs for 50, 50 and the remaining 20.
        $points = [];
        for ($i = 0; $i < 120; $i++) {
            $points[] = $this->point('P' . $i);
        }

        $repository = $this->createMock(PickupPointRepository::class);
        $repository->method('findIdsByCarrierAndCountry')->willReturn([]);
        $repository->expects($this->exactly(3))->method('upsert');

        $synchronizer = $this->synchronizer($repository, $this->fetcherYielding($points));

        $synchronizer(Carrier::GLS, new Country('CZ'));
    }

    private function synchronizer(PickupPointRepository $repository, PickupPointFetcher $fetcher): SynchronizePickupPoints
    {
        return new SynchronizePickupPoints($repository, new FetcherLocator([$fetcher]));
    }

    /**
     * A fake fetcher that records the config it was called with and yields the given points.
     *
     * @param list<PickupPointData> $points
     */
    private function fetcherYielding(array $points): PickupPointFetcher
    {
        return new class($points) implements PickupPointFetcher {
            public ?FetchConfig $receivedConfig = null;

            /** @param list<PickupPointData> $points */
            public function __construct(private array $points) {}

            public function fetch(FetchConfig $config): iterable
            {
                $this->receivedConfig = $config;

                yield from $this->points;
            }

            public function carrier(): Carrier
            {
                return Carrier::GLS;
            }
        };
    }

    private function point(string $id): PickupPointData
    {
        return new PickupPointData(
            id: $id,
            carrier: Carrier::GLS,
            type: PickupPointType::POINT,
            status: PickupPointStatus::AVAILABLE,
            city: 'City',
            name: 'Name',
            address: 'Address',
            zipCode: '10000',
            country: 'CZ',
            latitude: 50.0,
            longitude: 14.0,
        );
    }
}
