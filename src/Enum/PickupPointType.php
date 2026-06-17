<?php

namespace App\Enum;

/**
 * Distinguishes between a pickup point venue (that will most likely have opening hours)
 * and a pickup box expected to be available at any time but also limited in terms of package sizes
 */
enum PickupPointType: string
{
    case BOX = 'box';
    case POINT = 'point';
}
