<?php

namespace App\PickupPoint\Fetcher;

use App\Enum\Carrier;
use App\PickupPoint\Exception\FetcherNotFound;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class FetcherLocator
{

    public function __construct(
        #[AutowireIterator(tag: PickupPointFetcher::FETCHER_TAG)]
        private iterable $fetchers,
    ) {}

    /**
     * @throws FetcherNotFound
     */
    public function locate(Carrier $carrier): PickupPointFetcher
    {
        foreach ($this->fetchers as $fetcher) {

            if ($fetcher->carrier() === $carrier) {
                return $fetcher;
            }
        }

        throw new FetcherNotFound(sprintf('No fetcher found for carrier %s', $carrier->value));
    }

}
