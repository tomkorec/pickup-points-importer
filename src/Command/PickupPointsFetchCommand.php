<?php

namespace App\Command;

use App\Enum\Carrier;
use App\Model\Country;
use App\PickupPoint\SynchronizePickupPoints;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:pickup-points:fetch')]
class PickupPointsFetchCommand extends Command
{
    public function __construct(
        private readonly SynchronizePickupPoints $synchronizePickupPoints,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $carriers = [];

        $supportedCarriers = array_filter(Carrier::cases(), static fn(Carrier $carrier) => $carrier->supported());

        $choices = [
            'All',
            ...array_map(
                static fn(Carrier $carrier) => $carrier->value,
                $supportedCarriers
            ),
        ];

        $carrier = $io->choice(
            'Which carrier do you want to fetch pickup points for?',
            $choices,
            0,
        );

        if ($carrier === 'All') {
            $carriers = Carrier::cases();
        } else {
            $carriers[] = Carrier::from($carrier);
        }

        foreach ($carriers as $currentCarrier) {
            $countryCode = $io->choice(
                "Choose country",
                $currentCarrier->countries(),
            );

            $country = new Country($countryCode);

            try {
                ($this->synchronizePickupPoints)($currentCarrier, $country);
            } catch (\Throwable $e) {
                $io->error(sprintf('An error occurred while fetching pickup points for carrier %s: %s', $currentCarrier->value, $e->getMessage()));
            }
        }

        return Command::SUCCESS;
    }
}
