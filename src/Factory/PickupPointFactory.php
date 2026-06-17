<?php

namespace App\Factory;

use App\Entity\PickupPoint;
use App\PickupPoint\Fetcher\PickupPointData;

final class PickupPointFactory
{
    public function createFromData(PickupPointData $data): PickupPoint
    {

        $pickupPoint = new PickupPoint();

        $this->updateFromData($pickupPoint, $data);

        return $pickupPoint;
    }

    public function updateFromData(PickupPoint $point, PickupPointData $data): void
    {
        $point->setExternalId($data->id);
        $point->setCarrier($data->carrier->value);
        $point->setType($data->type);
        $point->setStatus($data->status);
        $point->setCity($data->city);
        $point->setName($data->name);
        $point->setAddress($data->address);
        $point->setZipCode($data->zipCode);
        $point->setCountry($data->country);
        $point->setLatitude($data->latitude);
        $point->setLongitude($data->longitude);
        $point->setOpeningHours($data->openingHours);
    }
}
