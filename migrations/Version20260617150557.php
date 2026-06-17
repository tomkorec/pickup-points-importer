<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617150557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pickup_points table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pickup_points (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, externalId VARCHAR(255) NOT NULL, carrier VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, zipCode VARCHAR(255) NOT NULL, country VARCHAR(2) NOT NULL, latitude NUMERIC(10, 8) NOT NULL, longitude NUMERIC(11, 8) NOT NULL, openingHours LONGTEXT DEFAULT NULL, created DATETIME NOT NULL, UNIQUE INDEX carrier_externalId_country (carrier, externalId, country), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pickup_points');
    }
}
