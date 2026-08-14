<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\UuidV7;

final class Version20260813225437 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table credit_pack et la peuple avec la grille de lancement (voir documentation/fonctionnel/pricing.md).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE credit_pack (id INT AUTO_INCREMENT NOT NULL, public_id BINARY(16) NOT NULL, credits INT NOT NULL, price_cents INT NOT NULL, currency VARCHAR(3) NOT NULL, badge VARCHAR(20) DEFAULT NULL, display_order INT NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_credit_pack_public_id (public_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // UUID générés ici en PHP (UuidV7, comme partout ailleurs dans l'application) plutôt que
        // via la fonction UUID() de MySQL, qui produit un UUIDv1 incompatible avec le typage
        // strict CreditPack::$publicId (Symfony\Component\Uid\UuidV7).
        $now = date('Y-m-d H:i:s');
        $packs = [
            [10, 499, null, 1],
            [100, 999, 'most_popular', 2],
            [200, 1699, null, 3],
            [500, 3999, 'best_value', 4],
            [1000, 7999, null, 5],
            [10000, 69999, null, 6],
        ];

        foreach ($packs as [$credits, $priceCents, $badge, $displayOrder]) {
            $this->addSql(
                'INSERT INTO credit_pack (public_id, credits, price_cents, currency, badge, display_order, active, created_at, updated_at) '
                .'VALUES (:publicId, :credits, :priceCents, \'usd\', :badge, :displayOrder, 1, :now, :now)',
                [
                    'publicId' => (new UuidV7())->toBinary(),
                    'credits' => $credits,
                    'priceCents' => $priceCents,
                    'badge' => $badge,
                    'displayOrder' => $displayOrder,
                    'now' => $now,
                ],
                ['publicId' => ParameterType::BINARY],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE credit_pack');
    }
}
