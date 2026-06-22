<?php

namespace App\Enum;

/**
 * The status of a pickup point, which can be used to filter out unavailable pickup points.
 */
enum PickupPointStatus: string
{
    case AVAILABLE = 'available';
    case TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';
    case CLOSED = 'closed';
    case TERMINATED = 'terminated';
}
