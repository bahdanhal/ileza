<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep reasonable price ranges wider than a single market floor';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE price_observations '
            . 'SET high_grosz = median_grosz, '
            . 'low_grosz = CAST(ROUND(median_grosz * 0.88) AS INTEGER) '
            . 'WHERE high_grosz > median_grosz OR low_grosz >= median_grosz'
        );
    }

    public function down(Schema $schema): void
    {
        // The previous range values cannot be reconstructed after normalization.
    }
}
