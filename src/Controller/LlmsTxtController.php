<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Convention llms.txt (llmstxt.org) : un point d'entrée texte listant les pages clés du site
 * avec une description courte, pensé pour être lu par un système IA/LLM qui explore le web —
 * même esprit que robots.txt/sitemap.xml mais pour un lecteur différent. Contrôleur plutôt que
 * fichier statique, même raison que RobotsController : UrlGeneratorInterface résout toujours le
 * bon hôte absolu. Toujours en anglais (locale forcée dans le template), qu'importe la locale de
 * la requête — même choix que le reste du contenu orienté "crawler" (robots.txt) plutôt qu'une
 * variante par langue que rien dans la convention llms.txt ne prévoit.
 */
final class LlmsTxtController extends AbstractController
{
    /** @var list<array{route: string, key: string}> */
    private const array TOOLS = [
        ['route' => 'app_gpx_viewer', 'key' => 'gpx_viewer'],
        ['route' => 'app_tools_gpx_to_google_maps', 'key' => 'gpx_to_google_maps'],
        ['route' => 'app_tools_gpx_simplify', 'key' => 'gpx_simplify'],
        ['route' => 'app_tools_gpx_merge', 'key' => 'gpx_merge'],
        ['route' => 'app_tools_kml_to_gpx', 'key' => 'kml_to_gpx'],
        ['route' => 'app_tools_gpx_to_kml', 'key' => 'gpx_to_kml'],
        ['route' => 'app_tools_kmz_to_gpx', 'key' => 'kmz_to_gpx'],
        ['route' => 'app_tools_tcx_to_gpx', 'key' => 'tcx_to_gpx'],
        ['route' => 'app_tools_gpx_to_tcx', 'key' => 'gpx_to_tcx'],
        ['route' => 'app_tools_fit_to_gpx', 'key' => 'fit_to_gpx'],
        ['route' => 'app_tools_gpx_to_fit', 'key' => 'gpx_to_fit'],
        ['route' => 'app_tools_geojson_to_gpx', 'key' => 'geojson_to_gpx'],
        ['route' => 'app_tools_gpx_to_geojson', 'key' => 'gpx_to_geojson'],
    ];

    /** @var list<array{route: string, key: string}> */
    private const array GUIDES = [
        ['route' => 'app_guides_index', 'key' => 'index'],
        ['route' => 'app_guides_gpx_vs_kml', 'key' => 'gpx_vs_kml'],
        ['route' => 'app_guides_gpx_vs_tcx', 'key' => 'gpx_vs_tcx'],
        ['route' => 'app_guides_gpx_vs_fit', 'key' => 'gpx_vs_fit'],
        ['route' => 'app_guides_gpx_vs_geojson', 'key' => 'gpx_vs_geojson'],
        ['route' => 'app_guides_google_maps_to_gpx', 'key' => 'google_maps_to_gpx'],
        ['route' => 'app_guides_kmz', 'key' => 'kmz'],
        ['route' => 'app_guides_simplify_track', 'key' => 'simplify_track'],
        ['route' => 'app_guides_merge_tracks', 'key' => 'merge_tracks'],
        ['route' => 'app_guides_google_maps_to_garmin', 'key' => 'google_maps_to_garmin'],
        ['route' => 'app_guides_google_maps_to_wahoo', 'key' => 'google_maps_to_wahoo'],
        ['route' => 'app_guides_google_maps_to_osmand', 'key' => 'google_maps_to_osmand'],
    ];

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[Route('/llms.txt', name: 'app_llms_txt')]
    public function __invoke(): Response
    {
        return $this->render(
            'llms_txt/llms.txt.twig',
            [
                'homeUrl' => $this->absoluteUrl('app_home'),
                'pricingUrl' => $this->absoluteUrl('app_pricing'),
                'chromeExtensionUrl' => $this->absoluteUrl('app_home').'#chrome-extension',
                'privacyUrl' => $this->absoluteUrl('app_privacy'),
                'termsUrl' => $this->absoluteUrl('app_terms'),
                'contactUrl' => $this->absoluteUrl('app_contact'),
                'tools' => $this->withAbsoluteUrls(self::TOOLS),
                'guides' => $this->withAbsoluteUrls(self::GUIDES),
            ],
            new Response('', Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']),
        );
    }

    /**
     * @param list<array{route: string, key: string}> $entries
     *
     * @return list<array{url: string, key: string}>
     */
    private function withAbsoluteUrls(array $entries): array
    {
        return array_map(
            fn (array $entry): array => ['url' => $this->absoluteUrl($entry['route']), 'key' => $entry['key']],
            $entries,
        );
    }

    private function absoluteUrl(string $route): string
    {
        return $this->urlGenerator->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
