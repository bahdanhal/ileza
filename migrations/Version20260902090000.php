<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace price observation confidence with explicit availability';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE price_observations ADD availability VARCHAR(20) NOT NULL DEFAULT 'available'");
        $this->addSql('ALTER TABLE price_observations DROP COLUMN confidence');
        $this->addSql(
            "UPDATE price_observations AS current_observation
             SET availability = 'unavailable',
                 summary = COALESCE(current_observation.summary, '') ||
                     CASE WHEN COALESCE(current_observation.summary, '') = '' THEN '' ELSE ' ' END ||
                     'Audit status: unavailable; no current live exact usable listing was verified.'
             WHERE current_observation.product_slug IN (
                 'iphone-xs-512gb',
                 'iphone-xs-max-512gb',
                 'iphone-16-512gb',
                 'iphone-17-pro-256gb',
                 'iphone-17-pro-1tb',
                 'macbook-air-15-m2-16-gb-ram-2-tb-ssd',
                 'macbook-pro-16-m1-pro-16-gb-ram-2-tb-ssd',
                 'macbook-pro-16-m2-pro-32-gb-ram-512-gb-ssd'
             )
             AND current_observation.observed_at = (
                 SELECT MAX(previous_observation.observed_at)
                 FROM price_observations AS previous_observation
                 WHERE previous_observation.product_slug = current_observation.product_slug
             )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE price_observations ADD confidence VARCHAR(20) NOT NULL DEFAULT 'high'");
        $this->addSql('ALTER TABLE price_observations DROP COLUMN availability');
    }
}
