<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Correct initial used-price observations by reducing all values through 2026-08-22 by 15 percent';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE price_observations SET '
            . 'median_grosz = ROUND(median_grosz * 0.85)::INT, '
            . 'low_grosz = ROUND(low_grosz * 0.85)::INT, '
            . 'high_grosz = ROUND(high_grosz * 0.85)::INT '
            . "WHERE observed_at < TIMESTAMPTZ '2026-08-23 00:00:00+00'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'UPDATE price_observations SET '
            . 'median_grosz = ROUND(median_grosz / 0.85)::INT, '
            . 'low_grosz = ROUND(low_grosz / 0.85)::INT, '
            . 'high_grosz = ROUND(high_grosz / 0.85)::INT '
            . "WHERE observed_at < TIMESTAMPTZ '2026-08-23 00:00:00+00'"
        );
    }
}
