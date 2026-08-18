<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818164500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the out-of-zone shipping surcharge column on orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` ADD shipping_surcharge_eur NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP shipping_surcharge_eur');
    }
}
