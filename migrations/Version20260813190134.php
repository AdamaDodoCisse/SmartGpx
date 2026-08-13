<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813190134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée les tables credit_account et credit_transaction (Usage).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE credit_account (id INT AUTO_INCREMENT NOT NULL, balance INT NOT NULL, reserved INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_credit_account_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE credit_transaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, amount INT NOT NULL, balance_after INT NOT NULL, conversion_id INT DEFAULT NULL, created_at DATETIME NOT NULL, credit_account_id INT NOT NULL, INDEX idx_credit_transaction_account (credit_account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE credit_account ADD CONSTRAINT FK_47B2318BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE credit_transaction ADD CONSTRAINT FK_5E1DE3E16813E404 FOREIGN KEY (credit_account_id) REFERENCES credit_account (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE credit_account DROP FOREIGN KEY FK_47B2318BA76ED395');
        $this->addSql('ALTER TABLE credit_transaction DROP FOREIGN KEY FK_5E1DE3E16813E404');
        $this->addSql('DROP TABLE credit_account');
        $this->addSql('DROP TABLE credit_transaction');
    }
}
