<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cap reasonable price range at the market median';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE price_observations SET high_grosz = median_grosz WHERE high_grosz > median_grosz'
        );
    }

    public function down(Schema $schema): void
    {
        // The previous high values cannot be reconstructed after normalization.
    }
}
