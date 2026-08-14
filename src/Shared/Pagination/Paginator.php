<?php

declare(strict_types=1);

namespace App\Shared\Pagination;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as OrmPaginator;

/**
 * Fine enveloppe autour du Paginator natif de Doctrine ORM (déjà présent via doctrine/orm,
 * aucune dépendance supplémentaire) — pas de framework de pagination générique, seulement ce que
 * les quatre listes admin (utilisateurs, achats, ledger, conversions échouées) ont besoin.
 */
final readonly class Paginator
{
    public const int PER_PAGE = 25;

    private function __construct(
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRequestedPage(int $requestedPage, int $perPage = self::PER_PAGE): self
    {
        return new self(max(1, $requestedPage), max(1, $perPage));
    }

    /**
     * @param QueryBuilder $queryBuilder porte déjà WHERE/ORDER BY, jamais LIMIT/OFFSET
     *
     * @return PaginatedResult<object>
     *
     * Le type générique exact de l'entité paginée n'est pas déductible ici (QueryBuilder n'est
     * pas lui-même générique) — chaque méthode de repository appelante restreint le type via un
     * `@var PaginatedResult<Entité>` sur la valeur de retour
     */
    public function paginate(QueryBuilder $queryBuilder): PaginatedResult
    {
        $queryBuilder
            ->setFirstResult(($this->page - 1) * $this->perPage)
            ->setMaxResults($this->perPage);

        $ormPaginator = new OrmPaginator($queryBuilder, fetchJoinCollection: false);

        return new PaginatedResult(
            iterator_to_array($ormPaginator, preserve_keys: false),
            $this->page,
            $this->perPage,
            \count($ormPaginator),
        );
    }
}
