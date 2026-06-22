<?php

declare(strict_types=1);

namespace App\PickupPoint\Fetcher;

use App\Enum\Carrier;
use App\Enum\PickupPointStatus;
use App\Enum\PickupPointType;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GlsPickupPointFetcher implements PickupPointFetcher
{
    private const string API_URL = 'https://ps-maps.gls-czech.com/getDropoffPoints.php';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly SerializerInterface $serializer,
    ) {}

    public function fetch(FetchConfig $config): iterable
    {
        $country = $config->country;

        $response = $this->client->request('GET', self::API_URL, [
            'query' => [
                'ctrcode' => $country->getCode(),
            ],
        ]);

        $content = $response->getContent();

        $dataArray = $this->serializer->decode($content, 'xml');

        // assert that the country code in the response matches the requested country code
        if ($dataArray['CtrCode'] !== $country->getCode()) {
            throw new \RuntimeException(sprintf('The country code in the response (%s) does not match the requested country code (%s).', $dataArray['CtrCode'], $country->getCode()));
        }

        $items = $dataArray['Data']['DropoffPoint'] ?? [];


        foreach ($items as $item) {
            $type = (int)$item['@IsParcelLocker'] === 1 ? PickupPointType::BOX : PickupPointType::POINT;

            $id = $item['@Id'] ?? $item['@ID'] ?? null;

            if (!isset($id)) {
                continue; // skip pickup points with missing ID
            }

            // in case address is an int, we will assume it is a house number in the same city
            $address = $item['@Address'];

            if (is_int($item['@Address'])) {
                $address = sprintf("%s %s", $item['@CityName'], $item['@Address']);
            }

            yield new PickupPointData(
                id: $id,
                carrier: Carrier::GLS,
                type: $type,
                status: PickupPointStatus::AVAILABLE, // no way to check if the pickup point is temporarily unavailable, so we assume it's always available
                city: $item['@CityName'],
                name: $item['@Name'],
                address: $address,
                zipCode: (string)$item['@ZipCode'],
                country: $country->getCode(),
                latitude: (float)$item['@GeoLat'],
                longitude: (float)$item['@GeoLng'],
                openingHours: $this->parseOpeningHours($item['Openings'] ?? null),
            );
        }
    }

    public function carrier(): Carrier
    {
        return Carrier::GLS;
    }

    private function parseOpeningHours(array $openings): string|null
    {
        $days = $openings['Openings'] ?? null;


        if (empty($days)) {
            return null;
        }

        $openingHours = [];

        foreach ($days as $day) {
            if (!$day['@OpenHours']) {
                continue;
            }

            $openingHours[] = sprintf(
                '%s %s',
                $day['@Day'],
                $day['@OpenHours'],
            );
        }

        return implode("\n", $openingHours);
    }

}
