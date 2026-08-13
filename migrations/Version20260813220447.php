<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813220447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table extension_authorization (jetons révocables pour l\'extension Chrome).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE extension_authorization (id INT AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, token_hash VARCHAR(64) NOT NULL, label VARCHAR(255) DEFAULT NULL, last_used_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_extension_authorization_user (user_id), UNIQUE INDEX uniq_extension_authorization_public_id (public_id), UNIQUE INDEX uniq_extension_authorization_token_hash (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE extension_authorization ADD CONSTRAINT FK_5053EAECA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE extension_authorization DROP FOREIGN KEY FK_5053EAECA76ED395');
        $this->addSql('DROP TABLE extension_authorization');
    }
}
