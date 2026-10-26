<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Market\Application\ProductCatalog;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026180000 extends AbstractMigration
{
    /**
     * Old groups that already held exactly one chip model and a trustworthy price: their history moves to the new slug.
     */
    private const MOVED = [
        'gpu-geforce-gtx-10-entry-level-4gb' => 'gpu-geforce-gtx-1050-ti-4gb',
        'gpu-geforce-gtx-10-mid-range-3gb' => 'gpu-geforce-gtx-1060-3gb',
        'gpu-geforce-gtx-10-mid-range-6gb' => 'gpu-geforce-gtx-1060-6gb',
        'gpu-geforce-gtx-10-high-end-8gb' => 'gpu-geforce-gtx-1080-8gb',
        'gpu-geforce-gtx-10-high-end-11gb' => 'gpu-geforce-gtx-1080-ti-11gb',
        'gpu-geforce-rtx-20-mid-range-6gb' => 'gpu-geforce-rtx-2060-6gb',
        'gpu-geforce-rtx-20-mid-range-8gb' => 'gpu-geforce-rtx-2060-super-8gb',
        'gpu-geforce-rtx-20-mid-range-12gb' => 'gpu-geforce-rtx-2060-12gb',
        'gpu-geforce-rtx-20-flagship-11gb' => 'gpu-geforce-rtx-2080-ti-11gb',
        'gpu-geforce-rtx-30-entry-level-6gb' => 'gpu-geforce-rtx-3050-6gb',
        'gpu-geforce-rtx-30-entry-level-8gb' => 'gpu-geforce-rtx-3050-8gb',
        'gpu-geforce-rtx-30-mid-range-12gb' => 'gpu-geforce-rtx-3060-12gb',
        'gpu-geforce-rtx-30-high-end-10gb' => 'gpu-geforce-rtx-3080-10gb',
        'gpu-geforce-rtx-40-mid-range-16gb' => 'gpu-geforce-rtx-4060-ti-16gb',
        'gpu-geforce-rtx-40-upper-mid-range-16gb' => 'gpu-geforce-rtx-4070-ti-super-16gb',
        'gpu-geforce-rtx-40-flagship-24gb' => 'gpu-geforce-rtx-4090-24gb',
        'gpu-geforce-rtx-50-entry-level-8gb' => 'gpu-geforce-rtx-5050-8gb',
        'gpu-geforce-rtx-50-mid-range-16gb' => 'gpu-geforce-rtx-5060-ti-16gb',
        'gpu-geforce-rtx-50-upper-mid-range-12gb' => 'gpu-geforce-rtx-5070-12gb',
        'gpu-geforce-rtx-50-high-end-16gb' => 'gpu-geforce-rtx-5080-16gb',
        'gpu-geforce-rtx-50-flagship-32gb' => 'gpu-geforce-rtx-5090-32gb',
        'gpu-radeon-rx-5000-entry-level-4gb' => 'gpu-radeon-rx-5500-xt-4gb',
        'gpu-radeon-rx-5000-entry-level-8gb' => 'gpu-radeon-rx-5500-xt-8gb',
        'gpu-radeon-rx-5000-mid-range-6gb' => 'gpu-radeon-rx-5600-xt-6gb',
        'gpu-radeon-rx-6000-upper-mid-range-10gb' => 'gpu-radeon-rx-6700-10gb',
        'gpu-radeon-rx-7000-mid-range-8gb' => 'gpu-radeon-rx-7600-8gb',
        'gpu-radeon-rx-7000-mid-range-16gb' => 'gpu-radeon-rx-7600-xt-16gb',
        'gpu-radeon-rx-7000-upper-mid-range-12gb' => 'gpu-radeon-rx-7700-xt-12gb',
        'gpu-radeon-rx-7000-flagship-20gb' => 'gpu-radeon-rx-7900-xt-20gb',
        'gpu-radeon-rx-9000-mid-range-16gb' => 'gpu-radeon-rx-9060-xt-16gb',
        'gpu-intel-arc-a-entry-level-6gb' => 'gpu-intel-arc-a380-6gb',
        'gpu-intel-arc-a-mid-range-16gb' => 'gpu-intel-arc-a770-16gb',
        'gpu-intel-arc-b-mid-range-10gb' => 'gpu-intel-arc-b570-10gb',
        'gpu-intel-arc-b-mid-range-12gb' => 'gpu-intel-arc-b580-12gb',
    ];

    /**
     * Old groups that mixed several chip models, plus single-model groups without a usable price
     * (one implausible RTX 5070 Ti estimate and two "unavailable"-only entries). Their data is dropped.
     */
    private const DROPPED = [
        'gpu-geforce-gtx-10-upper-mid-range-8gb',
        'gpu-geforce-gtx-16-entry-level-4gb',
        'gpu-geforce-gtx-16-mid-range-6gb',
        'gpu-geforce-rtx-20-upper-mid-range-8gb',
        'gpu-geforce-rtx-20-high-end-8gb',
        'gpu-geforce-rtx-30-mid-range-8gb',
        'gpu-geforce-rtx-30-upper-mid-range-8gb',
        'gpu-geforce-rtx-30-high-end-12gb',
        'gpu-geforce-rtx-30-flagship-24gb',
        'gpu-geforce-rtx-40-mid-range-8gb',
        'gpu-geforce-rtx-40-upper-mid-range-12gb',
        'gpu-geforce-rtx-40-high-end-16gb',
        'gpu-geforce-rtx-50-mid-range-8gb',
        'gpu-geforce-rtx-50-upper-mid-range-16gb',
        'gpu-radeon-rx-500-entry-level-4gb',
        'gpu-radeon-rx-500-mid-range-4gb',
        'gpu-radeon-rx-500-mid-range-8gb',
        'gpu-radeon-rx-5000-upper-mid-range-8gb',
        'gpu-radeon-rx-6000-entry-level-4gb',
        'gpu-radeon-rx-6000-mid-range-8gb',
        'gpu-radeon-rx-6000-upper-mid-range-12gb',
        'gpu-radeon-rx-6000-high-end-16gb',
        'gpu-radeon-rx-6000-flagship-16gb',
        'gpu-radeon-rx-7000-high-end-16gb',
        'gpu-radeon-rx-7000-flagship-24gb',
        'gpu-radeon-rx-9000-mid-range-8gb',
        'gpu-radeon-rx-9000-upper-mid-range-16gb',
        'gpu-intel-arc-a-mid-range-8gb',
    ];

    private const SLUG_TABLES = ['price_observations', 'price_alerts', 'price_tips'];

    public function getDescription(): string
    {
        return 'Replace graphics card class groups with one product per chip model and VRAM size';
    }

    public function up(Schema $schema): void
    {
        foreach (self::MOVED as $old => $new) {
            foreach (self::SLUG_TABLES as $table) {
                $this->addSql(
                    sprintf('UPDATE %s SET product_slug = :new WHERE product_slug = :old', $table),
                    ['new' => $new, 'old' => $old]
                );
            }
        }

        foreach (self::DROPPED as $old) {
            foreach (self::SLUG_TABLES as $table) {
                $this->addSql(sprintf('DELETE FROM %s WHERE product_slug = :old', $table), ['old' => $old]);
            }
        }

        // Recreate every graphics card row so the new products keep the catalog order.
        $this->addSql("DELETE FROM products WHERE category = 'gpus'");

        $catalog = new ProductCatalog();
        $now = '2026-10-26 18:00:00+00:00';
        foreach ($catalog->seedProducts() as $product) {
            if ($product->category !== 'gpus') {
                continue;
            }

            $familySlug = $catalog->familySlug($product);
            [$image, $credit, $source] = $catalog->familyImage($familySlug, $product->category, $product);

            $this->addSql(
                'INSERT INTO products ('
                . 'slug, name, definition, category, family_slug, family_name, '
                . 'image, image_credit, image_source, specifications, created_at, updated_at) '
                . 'VALUES ('
                . ':slug, :name, :definition, :category, :family_slug, :family_name, '
                . ':image, :image_credit, :image_source, :specifications, :created_at, :updated_at)',
                [
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'definition' => $product->definition,
                    'category' => $product->category,
                    'family_slug' => $familySlug,
                    'family_name' => (string) $product->familyName,
                    'image' => $image,
                    'image_credit' => $credit,
                    'image_source' => $source,
                    'specifications' => json_encode($product->specifications, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Dropped graphics card groups cannot be restored.');
    }
}
