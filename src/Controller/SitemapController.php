<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Aucun indicateur natif Symfony ne distingue les routes publiques des routes privées
 * (auth, API, admin…) : la liste est donc tenue à la main, comme pour
 * ToolsControllerTest::routes() et la carte des outils de la page d'accueil.
 */
final class SitemapController extends AbstractController
{
    /** @var list<string> */
    private const array PUBLIC_ROUTES = [
        'app_home',
        'app_pricing',
        'app_privacy',
        'app_terms',
        'app_contact',
        'app_gpx_viewer',
        'app_tools_gpx_to_google_maps',
        'app_tools_gpx_simplify',
        'app_tools_gpx_merge',
        'app_tools_kml_to_gpx',
        'app_tools_gpx_to_kml',
        'app_tools_kmz_to_gpx',
        'app_tools_tcx_to_gpx',
        'app_tools_gpx_to_tcx',
        'app_tools_fit_to_gpx',
        'app_tools_gpx_to_fit',
        'app_tools_geojson_to_gpx',
        'app_tools_gpx_to_geojson',
        'app_guides_index',
        'app_guides_gpx_vs_kml',
        'app_guides_gpx_vs_tcx',
        'app_guides_gpx_vs_fit',
        'app_guides_gpx_vs_geojson',
        'app_guides_google_maps_to_gpx',
        'app_guides_kmz',
        'app_guides_simplify_track',
        'app_guides_merge_tracks',
    ];

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[Route('/sitemap.xml', name: 'app_sitemap')]
    public function __invoke(): Response
    {
        $urls = [];
        foreach (self::PUBLIC_ROUTES as $route) {
            foreach (['en', 'fr'] as $locale) {
                $urls[] = $this->urlGenerator->generate($route, ['_locale' => $locale], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        return $this->render(
            'sitemap/sitemap.xml.twig',
            ['urls' => $urls],
            new Response('', Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']),
        );
    }
}
