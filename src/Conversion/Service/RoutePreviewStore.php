<?php

declare(strict_types=1);

namespace App\Conversion\Service;

use App\Conversion\Exception\RoutePreviewNotFoundException;
use App\Conversion\ValueObject\CachedRoutePreview;
use App\Identity\Entity\User;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Cache court terme (Redis, pool cache.app) des itinéraires candidats calculés par
 * PreviewGoogleMapsRoutesAction, le temps que l'utilisateur choisisse celui à exporter — voir
 * documentation/technique/routing-options.md pour le flux complet en deux temps. Aucun crédit
 * n'est réservé tant qu'un aperçu n'a pas été exporté ; un aperçu jamais exporté expire
 * simplement sans coût.
 */
final class RoutePreviewStore
{
    private const int TTL_SECONDS = 600;
    private const string KEY_PREFIX = 'route_preview.';

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function store(CachedRoutePreview $preview): string
    {
        $previewId = Uuid::v4()->toRfc4122();

        $item = $this->cache->getItem(self::KEY_PREFIX.$previewId);
        $item->set($preview);
        $item->expiresAfter(self::TTL_SECONDS);
        $this->cache->save($item);

        return $previewId;
    }

    /**
     * @throws RoutePreviewNotFoundException expired, unknown, or owned by a different user
     */
    public function retrieve(string $previewId, User $user): CachedRoutePreview
    {
        $item = $this->cache->getItem(self::KEY_PREFIX.$previewId);

        if (!$item->isHit()) {
            throw new RoutePreviewNotFoundException('This route preview has expired.');
        }

        $preview = $item->get();

        if (!$preview instanceof CachedRoutePreview || $preview->userId !== $user->getId()) {
            throw new RoutePreviewNotFoundException('This route preview does not belong to the current user.');
        }

        return $preview;
    }

    public function forget(string $previewId): void
    {
        $this->cache->deleteItem(self::KEY_PREFIX.$previewId);
    }
}
