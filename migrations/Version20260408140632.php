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
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, name_fr VARCHAR(255) NOT NULL, slug VARCHAR(280) NOT NULL, slug_fr VARCHAR(280) NOT NULL, description LONGTEXT NOT NULL, description_fr LONGTEXT NOT NULL, base_price NUMERIC(8, 2) NOT NULL, compare_at_price NUMERIC(8, 2) DEFAULT NULL, shipping_tier VARCHAR(20) NOT NULL, is_published TINYINT NOT NULL, is_featured TINYINT NOT NULL, is_sold_out TINYINT NOT NULL, available_in JSON NOT NULL, sold_at DATETIME DEFAULT NULL, thumbnail VARCHAR(255) DEFAULT NULL, worn_photo VARCHAR(255) DEFAULT NULL, context_photo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, category_id INT NOT NULL, UNIQUE INDEX UNIQ_D34A04AD989D9B62 (slug), UNIQUE INDEX UNIQ_D34A04AD4255CF50 (slug_fr), INDEX IDX_D34A04AD12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_related (product_source INT NOT NULL, product_target INT NOT NULL, INDEX IDX_B18E6B203DF63ED7 (product_source), INDEX IDX_B18E6B2024136E58 (product_target), PRIMARY KEY (product_source, product_target)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, name_fr VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, slug_fr VARCHAR(120) NOT NULL, position INT NOT NULL, UNIQUE INDEX UNIQ_CDFC7356989D9B62 (slug), UNIQUE INDEX UNIQ_CDFC73564255CF50 (slug_fr), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES product_category (id)');
        $this->addSql('ALTER TABLE product_related ADD CONSTRAINT FK_B18E6B203DF63ED7 FOREIGN KEY (product_source) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_related ADD CONSTRAINT FK_B18E6B2024136E58 FOREIGN KEY (product_target) REFERENCES product (id) ON DELETE CASCADE');

        // Shipping settings table
        $this->addSql('CREATE TABLE shipping_settings (id INT AUTO_INCREMENT NOT NULL, tier VARCHAR(20) NOT NULL, label VARCHAR(100) NOT NULL, shipping_cost_usd NUMERIC(8, 2) NOT NULL, max_weight_grams INT NOT NULL, UNIQUE INDEX UNIQ_SHIPPING_TIER (tier), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // Site settings table (single row)
        $this->addSql('CREATE TABLE site_settings (id INT AUTO_INCREMENT NOT NULL, active_collection VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // Admin table
        $this->addSql('CREATE TABLE admin (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, role VARCHAR(20) NOT NULL DEFAULT \'admin\', receives_admin_emails TINYINT NOT NULL DEFAULT 1, last_logged_in_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_880E0D76E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // Customer tables
        $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, roles JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_81398E09E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE customer_address (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, label VARCHAR(50) NOT NULL, recipient_name VARCHAR(255) DEFAULT NULL, address_line1 VARCHAR(255) NOT NULL, address_line2 VARCHAR(255) DEFAULT NULL, city VARCHAR(255) NOT NULL, state VARCHAR(100) DEFAULT NULL, postal_code VARCHAR(20) NOT NULL, country VARCHAR(2) NOT NULL, is_default TINYINT NOT NULL, INDEX IDX_1193CB3F9395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE customer_address ADD CONSTRAINT FK_1193CB3F9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE');

        // Reset password request table
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES customer (id)');

        // Contact message table
        $this->addSql('CREATE TABLE contact_message (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, subject VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // Order tables
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, customer_id INT DEFAULT NULL, reference VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, customer_email VARCHAR(255) NOT NULL, customer_name VARCHAR(255) NOT NULL, customer_locale VARCHAR(5) DEFAULT NULL, shipping_recipient_name VARCHAR(255) NOT NULL, shipping_address_line1 VARCHAR(255) NOT NULL, shipping_address_line2 VARCHAR(255) DEFAULT NULL, shipping_city VARCHAR(255) NOT NULL, shipping_state VARCHAR(100) DEFAULT NULL, shipping_postal_code VARCHAR(20) NOT NULL, shipping_country VARCHAR(2) NOT NULL, billing_recipient_name VARCHAR(255) DEFAULT NULL, billing_address_line1 VARCHAR(255) DEFAULT NULL, billing_address_line2 VARCHAR(255) DEFAULT NULL, billing_city VARCHAR(255) DEFAULT NULL, billing_state VARCHAR(100) DEFAULT NULL, billing_postal_code VARCHAR(20) DEFAULT NULL, billing_country VARCHAR(2) DEFAULT NULL, total_usd NUMERIC(10, 2) NOT NULL, stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, stripe_payment_status VARCHAR(50) DEFAULT NULL, tracking_number VARCHAR(255) DEFAULT NULL, internal_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_F5299398AEA34913 (reference), INDEX IDX_F52993989395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, product_id INT DEFAULT NULL, product_name VARCHAR(255) NOT NULL, product_price NUMERIC(8, 2) NOT NULL, shipping_cost NUMERIC(8, 2) NOT NULL, INDEX IDX_52EA1F098D9F6D38 (order_id), INDEX IDX_52EA1F094584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F094584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE SET NULL');

        // Cart tables
        $this->addSql('CREATE TABLE cart (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_BA388B79395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cart_item (id INT AUTO_INCREMENT NOT NULL, cart_id INT NOT NULL, product_id INT NOT NULL, added_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F0FE25271AD5CDBF (cart_id), INDEX IDX_F0FE25274584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_BA388B79395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25274584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');

        // Reservation table (product hold during checkout)
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, session_id VARCHAR(128) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_42C849554584665A (product_id), INDEX IDX_RESERVATION_EXPIRES (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849554584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE product_related DROP FOREIGN KEY FK_B18E6B203DF63ED7');
        $this->addSql('ALTER TABLE product_related DROP FOREIGN KEY FK_B18E6B2024136E58');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F094584665A');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('ALTER TABLE customer_address DROP FOREIGN KEY FK_1193CB3F9395C3F3');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25274584665A');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849554584665A');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('ALTER TABLE cart DROP FOREIGN KEY FK_BA388B79395C3F3');
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE cart');
        $this->addSql('DROP TABLE contact_message');
        $this->addSql('DROP TABLE site_settings');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE shipping_settings');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE customer_address');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_related');
        $this->addSql('DROP TABLE product_category');
    }
}
