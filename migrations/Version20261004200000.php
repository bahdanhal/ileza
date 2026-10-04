<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen reasonable price ranges narrower than 1% of the market floor';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE price_observations '
            . 'SET high_grosz = CAST(ROUND(low_grosz * 1.12) AS INTEGER) '
            . 'WHERE low_grosz > 0 AND high_grosz - low_grosz < low_grosz / 100.0'
        );
    }

    public function down(Schema $schema): void
    {
        // The previous range values cannot be reconstructed after normalization.
    }
}
