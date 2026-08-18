<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818113854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the page_view_stat table for anonymous audience counters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE page_view_stat (id INT AUTO_INCREMENT NOT NULL, day DATE NOT NULL, dimension VARCHAR(20) NOT NULL, value VARCHAR(220) NOT NULL COLLATE `utf8mb4_bin`, views INT NOT NULL, UNIQUE INDEX uniq_page_view_stat_key (day, dimension, value), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE page_view_stat');
    }
}
