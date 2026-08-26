<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Market\Application\ProductCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the obsolete price-observation sample size field';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE price_observations DROP sample_size');

        foreach ((new ProductCatalog())->seedProducts() as $product) {
            $this->addSql(
                'UPDATE products SET definition = :definition, updated_at = :updated_at WHERE slug = :slug',
                [
                    'definition' => $product->definition,
                    'updated_at' => '2026-08-26 18:30:00+02:00',
                    'slug' => $product->slug,
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE price_observations ADD sample_size INT DEFAULT 3 NOT NULL');
    }
}
