<?php

namespace App\Factory;

use App\Entity\PickupPoint;
use App\PickupPoint\Fetcher\PickupPointData;

final class PickupPointFactory
{
    public function createFromData(PickupPointData $data): PickupPoint
    {

        $pickupPoint = new PickupPoint();

        $pickupPoint->setExternalId($data->id);
        $pickupPoint->setCarrier($data->carrier->value);
        $pickupPoint->setType($data->type);
        $pickupPoint->setStatus($data->status);
        $pickupPoint->setCity($data->city);
        $pickupPoint->setName($data->name);
        $pickupPoint->setAddress($data->address);
        $pickupPoint->setZipCode($data->zipCode);
        $pickupPoint->setCountry($data->country);
        $pickupPoint->setLatitude($data->latitude);
        $pickupPoint->setLongitude($data->longitude);
        $pickupPoint->setOpeningHours($data->openingHours);

        return $pickupPoint;
    }

}
