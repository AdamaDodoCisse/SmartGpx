<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813204123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table conversion (historique des conversions Google Maps → GPX réussies).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversion (id INT AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, source_url VARCHAR(2048) NOT NULL, origin_label VARCHAR(500) NOT NULL, destination_label VARCHAR(500) NOT NULL, stops JSON NOT NULL, waypoints JSON NOT NULL, geometry JSON NOT NULL, travel_mode VARCHAR(20) NOT NULL, travel_mode_inferred TINYINT NOT NULL, distance_meters INT NOT NULL, duration_seconds INT NOT NULL, track_point_count INT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_conversion_user (user_id), UNIQUE INDEX uniq_conversion_public_id (public_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE conversion ADD CONSTRAINT FK_BD912744A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversion DROP FOREIGN KEY FK_BD912744A76ED395');
        $this->addSql('DROP TABLE conversion');
    }
}
