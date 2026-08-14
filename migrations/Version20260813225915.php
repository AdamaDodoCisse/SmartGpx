<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813225915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table credit_purchase (suivi des sessions Stripe Checkout) et ajoute credit_transaction.credit_purchase_id.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE credit_purchase (id INT AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, credits INT NOT NULL, amount_cents INT NOT NULL, currency VARCHAR(3) NOT NULL, stripe_checkout_session_id VARCHAR(255) NOT NULL, stripe_payment_intent_id VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, credit_pack_id INT NOT NULL, INDEX IDX_CAFC6805239DD538 (credit_pack_id), INDEX idx_credit_purchase_user (user_id), UNIQUE INDEX uniq_credit_purchase_public_id (public_id), UNIQUE INDEX uniq_credit_purchase_stripe_checkout_session_id (stripe_checkout_session_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE credit_purchase ADD CONSTRAINT FK_CAFC6805A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE credit_purchase ADD CONSTRAINT FK_CAFC6805239DD538 FOREIGN KEY (credit_pack_id) REFERENCES credit_pack (id)');
        $this->addSql('ALTER TABLE credit_transaction ADD credit_purchase_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE credit_purchase DROP FOREIGN KEY FK_CAFC6805A76ED395');
        $this->addSql('ALTER TABLE credit_purchase DROP FOREIGN KEY FK_CAFC6805239DD538');
        $this->addSql('DROP TABLE credit_purchase');
        $this->addSql('ALTER TABLE credit_transaction DROP credit_purchase_id');
    }
}
