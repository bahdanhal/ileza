<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029120000 extends AbstractMigration
{
    /**
     * Product rows keep their own copy of the photo credit. These two photos were replaced,
     * so rows that still carry the old author and source get the new ones.
     */
    private const CREDITS = [
        [
            'image' => '/images/market/macbook-air-13-m3.jpg',
            'old_credit' => 'Thomas Amberg',
            'old_source' => 'https://commons.wikimedia.org/wiki/File:Hardware_PXL_20240701_181416002_(53829190029).jpg',
            'new_credit' => 'KKPCW (Kyu3)',
            'new_source' => 'https://commons.wikimedia.org/wiki/File:M2_Macbook_Air_Starlight_model.jpg',
        ],
        [
            'image' => '/images/market/ram-ddr5-laptop.jpg',
            'old_credit' => '4300streetcar',
            'old_source' => 'https://commons.wikimedia.org/wiki/File:SK_Hynix_DDR5_form_factors.jpg',
            'new_credit' => 'Sebastiaan Broekhoven (ITEagle Europe)',
            'new_source' => 'https://commons.wikimedia.org/wiki/'
                . 'File:Kingston_FURY_Impact_DDR5_SO-DIMM_Laptop_Memory_32GB_Module_-_front_view.jpg',
        ],
    ];

    public function getDescription(): string
    {
        return 'Credit the new MacBook Air M3 and DDR5 laptop memory photos on existing products';
    }

    public function up(Schema $schema): void
    {
        foreach (self::CREDITS as $credit) {
            $this->addSql(
                'UPDATE products SET image_credit = :new_credit, image_source = :new_source '
                . 'WHERE image = :image AND image_credit = :old_credit AND image_source = :old_source',
                $credit
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::CREDITS as $credit) {
            $this->addSql(
                'UPDATE products SET image_credit = :old_credit, image_source = :old_source '
                . 'WHERE image = :image AND image_credit = :new_credit AND image_source = :new_source',
                $credit
            );
        }
    }
}
