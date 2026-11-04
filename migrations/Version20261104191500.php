<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104191500 extends AbstractMigration
{
    private const NAMES = [
        [
            'ram-ddr4-desktop-8-gb-3200-mhz',
            'RAM DDR4 8 GB 3200 MHz',
            'RAM DDR4 DIMM 8 GB 3200 MHz',
        ],
        [
            'ram-ddr4-desktop-16-gb-3200-mhz',
            'RAM DDR4 16 GB 3200 MHz',
            'RAM DDR4 DIMM 16 GB 3200 MHz',
        ],
        [
            'ram-ddr4-desktop-16-gb-3600-mhz',
            'RAM DDR4 16 GB 3600 MHz',
            'RAM DDR4 DIMM 16 GB 3600 MHz',
        ],
        [
            'ram-ddr4-desktop-32-gb-3600-mhz',
            'RAM DDR4 32 GB 3600 MHz',
            'RAM DDR4 DIMM 32 GB 3600 MHz',
        ],
        [
            'ram-ddr5-desktop-16-gb-5600-mhz',
            'RAM DDR5 16 GB 5600 MHz',
            'RAM DDR5 DIMM 16 GB 5600 MHz',
        ],
        [
            'ram-ddr5-desktop-16-gb-6000-mhz',
            'RAM DDR5 16 GB 6000 MHz',
            'RAM DDR5 DIMM 16 GB 6000 MHz',
        ],
        [
            'ram-ddr5-desktop-32-gb-6000-mhz',
            'RAM DDR5 32 GB 6000 MHz',
            'RAM DDR5 DIMM 32 GB 6000 MHz',
        ],
        [
            'ram-ddr4-laptop-8-gb-3200-mhz',
            'RAM DDR4 8 GB 3200 MHz',
            'RAM DDR4 SO-DIMM 8 GB 3200 MHz',
        ],
        [
            'ram-ddr4-laptop-16-gb-3200-mhz',
            'RAM DDR4 16 GB 3200 MHz',
            'RAM DDR4 SO-DIMM 16 GB 3200 MHz',
        ],
        [
            'ram-ddr4-laptop-32-gb-3200-mhz',
            'RAM DDR4 32 GB 3200 MHz',
            'RAM DDR4 SO-DIMM 32 GB 3200 MHz',
        ],
        [
            'ram-ddr5-laptop-8-gb-4800-mhz',
            'RAM DDR5 8 GB 4800 MHz',
            'RAM DDR5 SO-DIMM 8 GB 4800 MHz',
        ],
        [
            'ram-ddr5-laptop-16-gb-4800-mhz',
            'RAM DDR5 16 GB 4800 MHz',
            'RAM DDR5 SO-DIMM 16 GB 4800 MHz',
        ],
        [
            'ram-ddr5-laptop-16-gb-5600-mhz',
            'RAM DDR5 16 GB 5600 MHz',
            'RAM DDR5 SO-DIMM 16 GB 5600 MHz',
        ],
        [
            'ram-ddr5-laptop-32-gb-5600-mhz',
            'RAM DDR5 32 GB 5600 MHz',
            'RAM DDR5 SO-DIMM 32 GB 5600 MHz',
        ],
    ];

    public function getDescription(): string
    {
        return 'Distinguish DIMM and SO-DIMM in existing RAM product names';
    }

    public function up(Schema $schema): void
    {
        foreach (self::NAMES as [$slug, $oldName, $newName]) {
            $this->addSql(
                'UPDATE products SET name = :new_name WHERE slug = :slug AND category = :category AND name = :old_name',
                ['slug' => $slug, 'category' => 'ram', 'old_name' => $oldName, 'new_name' => $newName]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::NAMES as [$slug, $oldName, $newName]) {
            $this->addSql(
                'UPDATE products SET name = :old_name WHERE slug = :slug AND category = :category AND name = :new_name',
                ['slug' => $slug, 'category' => 'ram', 'old_name' => $oldName, 'new_name' => $newName]
            );
        }
    }
}
