<?php

namespace App\PickupPoint\Fetcher;

use App\Enum\Carrier;
use App\Enum\PickupPointStatus;
use App\Enum\PickupPointType;

final readonly class PickupPointData
{
    public function __construct(
        public string            $id,
        public Carrier           $carrier,
        public PickupPointType   $type,
        public PickupPointStatus $status,
        public string            $city,
        public string            $name,
        public string            $address,
        public string            $zipCode,
        public string            $country,
        public float             $latitude,
        public float             $longitude,
        public ?string           $openingHours = null,
    ) {}
}
