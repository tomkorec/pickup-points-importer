<?php

namespace App\PickupPoint\Exception;

use Exception;

class FetcherNotFound extends Exception
{
    protected $message = 'No fetcher found for the given carrier.';
}
