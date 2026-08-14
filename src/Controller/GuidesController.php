<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages de contenu SEO — prose statique uniquement, aucun îlot React (voir ADR-004).
 * Chaque route ne fait que rendre un gabarit Twig ; le corps EN/FR vit dans le template.
 */
final class GuidesController extends AbstractController
{
    #[Route(['en' => '/guides', 'fr' => '/fr/guides'], name: 'app_guides_index')]
    public function index(): Response
    {
        return $this->render('guides/index.html.twig');
    }

    #[Route(['en' => '/guides/gpx-vs-kml', 'fr' => '/fr/guides/gpx-ou-kml'], name: 'app_guides_gpx_vs_kml')]
    public function gpxVsKml(): Response
    {
        return $this->render('guides/gpx_vs_kml.html.twig');
    }

    #[Route(['en' => '/guides/gpx-vs-tcx', 'fr' => '/fr/guides/gpx-ou-tcx'], name: 'app_guides_gpx_vs_tcx')]
    public function gpxVsTcx(): Response
    {
        return $this->render('guides/gpx_vs_tcx.html.twig');
    }

    #[Route(['en' => '/guides/gpx-vs-fit', 'fr' => '/fr/guides/gpx-ou-fit'], name: 'app_guides_gpx_vs_fit')]
    public function gpxVsFit(): Response
    {
        return $this->render('guides/gpx_vs_fit.html.twig');
    }

    #[Route(['en' => '/guides/gpx-vs-geojson', 'fr' => '/fr/guides/gpx-ou-geojson'], name: 'app_guides_gpx_vs_geojson')]
    public function gpxVsGeojson(): Response
    {
        return $this->render('guides/gpx_vs_geojson.html.twig');
    }

    #[Route(['en' => '/guides/google-maps-to-gpx', 'fr' => '/fr/guides/convertir-google-maps-en-gpx'], name: 'app_guides_google_maps_to_gpx')]
    public function googleMapsToGpx(): Response
    {
        return $this->render('guides/google_maps_to_gpx.html.twig');
    }

    #[Route(['en' => '/guides/what-is-kmz', 'fr' => '/fr/guides/fichier-kmz'], name: 'app_guides_kmz')]
    public function kmz(): Response
    {
        return $this->render('guides/kmz.html.twig');
    }

    #[Route(['en' => '/guides/simplify-gps-track', 'fr' => '/fr/guides/simplifier-une-trace-gps'], name: 'app_guides_simplify_track')]
    public function simplifyTrack(): Response
    {
        return $this->render('guides/simplify_track.html.twig');
    }

    #[Route(['en' => '/guides/merge-gpx-tracks', 'fr' => '/fr/guides/fusionner-plusieurs-gpx'], name: 'app_guides_merge_tracks')]
    public function mergeTracks(): Response
    {
        return $this->render('guides/merge_tracks.html.twig');
    }
}
