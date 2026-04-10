<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408140632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, name_fr VARCHAR(255) NOT NULL, slug VARCHAR(280) NOT NULL, slug_fr VARCHAR(280) NOT NULL, description LONGTEXT NOT NULL, description_fr LONGTEXT NOT NULL, base_price NUMERIC(8, 2) NOT NULL, shipping_tier VARCHAR(20) NOT NULL, is_published TINYINT NOT NULL, is_featured TINYINT NOT NULL, is_sold_out TINYINT NOT NULL DEFAULT 0, sold_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INT NOT NULL, UNIQUE INDEX UNIQ_D34A04AD989D9B62 (slug), UNIQUE INDEX UNIQ_D34A04AD_SLUG_FR (slug_fr), INDEX IDX_D34A04AD12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_related (product_source INT NOT NULL, product_target INT NOT NULL, INDEX IDX_B18E6B203DF63ED7 (product_source), INDEX IDX_B18E6B2024136E58 (product_target), PRIMARY KEY (product_source, product_target)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, name_fr VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, slug_fr VARCHAR(120) NOT NULL, position INT NOT NULL, UNIQUE INDEX UNIQ_CDFC7356989D9B62 (slug), UNIQUE INDEX UNIQ_CDFC7356_SLUG_FR (slug_fr), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_image (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, position INT NOT NULL, product_id INT NOT NULL, INDEX IDX_64617F034584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES product_category (id)');
        $this->addSql('ALTER TABLE product_related ADD CONSTRAINT FK_B18E6B203DF63ED7 FOREIGN KEY (product_source) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_related ADD CONSTRAINT FK_B18E6B2024136E58 FOREIGN KEY (product_target) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_image ADD CONSTRAINT FK_64617F034584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        // Admin table
        $this->addSql('CREATE TABLE admin (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, last_logged_in_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_880E0D76E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // Order tables
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, customer_email VARCHAR(255) NOT NULL, customer_name VARCHAR(255) NOT NULL, shipping_address_line1 VARCHAR(255) NOT NULL, shipping_address_line2 VARCHAR(255) DEFAULT NULL, shipping_city VARCHAR(255) NOT NULL, shipping_state VARCHAR(100) DEFAULT NULL, shipping_postal_code VARCHAR(20) NOT NULL, shipping_country VARCHAR(2) NOT NULL, origin_country VARCHAR(2) DEFAULT NULL, total_usd NUMERIC(10, 2) NOT NULL, stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, stripe_payment_status VARCHAR(50) DEFAULT NULL, tracking_number VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_F5299398AEA34913 (reference), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, product_id INT DEFAULT NULL, product_name VARCHAR(255) NOT NULL, product_price NUMERIC(8, 2) NOT NULL, shipping_cost NUMERIC(8, 2) NOT NULL, INDEX IDX_52EA1F098D9F6D38 (order_id), INDEX IDX_52EA1F094584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F094584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE product_related DROP FOREIGN KEY FK_B18E6B203DF63ED7');
        $this->addSql('ALTER TABLE product_related DROP FOREIGN KEY FK_B18E6B2024136E58');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F034584665A');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F094584665A');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_related');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE product_image');
    }
}
