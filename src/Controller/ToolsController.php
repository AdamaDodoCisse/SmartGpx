<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages des outils gratuits — aucune logique métier côté serveur (traitement 100% navigateur,
 * voir documentation/decisions/ADR-003-browser-conversions.md) : chaque route ne fait que rendre
 * la page qui monte l'îlot React correspondant.
 */
final class ToolsController extends AbstractController
{
    #[Route(['en' => '/gpx-viewer', 'fr' => '/fr/visionneuse-gpx'], name: 'app_gpx_viewer')]
    public function gpxViewer(): Response
    {
        return $this->render('tools/gpx_viewer.html.twig');
    }

    #[Route(['en' => '/tools/gpx-to-google-maps', 'fr' => '/fr/outils/gpx-vers-google-maps'], name: 'app_tools_gpx_to_google_maps')]
    public function gpxToGoogleMaps(): Response
    {
        return $this->render('tools/gpx_to_google_maps.html.twig');
    }

    #[Route(['en' => '/tools/gpx-simplify', 'fr' => '/fr/outils/simplifier-gpx'], name: 'app_tools_gpx_simplify')]
    public function gpxSimplify(): Response
    {
        return $this->render('tools/gpx_simplify.html.twig');
    }

    #[Route(['en' => '/tools/gpx-merge', 'fr' => '/fr/outils/fusionner-gpx'], name: 'app_tools_gpx_merge')]
    public function gpxMerge(): Response
    {
        return $this->render('tools/gpx_merge.html.twig');
    }

    #[Route(['en' => '/tools/kml-to-gpx', 'fr' => '/fr/outils/kml-vers-gpx'], name: 'app_tools_kml_to_gpx')]
    public function kmlToGpx(): Response
    {
        return $this->render('tools/kml_to_gpx.html.twig');
    }

    #[Route(['en' => '/tools/gpx-to-kml', 'fr' => '/fr/outils/gpx-vers-kml'], name: 'app_tools_gpx_to_kml')]
    public function gpxToKml(): Response
    {
        return $this->render('tools/gpx_to_kml.html.twig');
    }

    #[Route(['en' => '/tools/kmz-to-gpx', 'fr' => '/fr/outils/kmz-vers-gpx'], name: 'app_tools_kmz_to_gpx')]
    public function kmzToGpx(): Response
    {
        return $this->render('tools/kmz_to_gpx.html.twig');
    }

    #[Route(['en' => '/tools/tcx-to-gpx', 'fr' => '/fr/outils/tcx-vers-gpx'], name: 'app_tools_tcx_to_gpx')]
    public function tcxToGpx(): Response
    {
        return $this->render('tools/tcx_to_gpx.html.twig');
    }

    #[Route(['en' => '/tools/gpx-to-tcx', 'fr' => '/fr/outils/gpx-vers-tcx'], name: 'app_tools_gpx_to_tcx')]
    public function gpxToTcx(): Response
    {
        return $this->render('tools/gpx_to_tcx.html.twig');
    }

    #[Route(['en' => '/tools/fit-to-gpx', 'fr' => '/fr/outils/fit-vers-gpx'], name: 'app_tools_fit_to_gpx')]
    public function fitToGpx(): Response
    {
        return $this->render('tools/fit_to_gpx.html.twig');
    }

    #[Route(['en' => '/tools/gpx-to-fit', 'fr' => '/fr/outils/gpx-vers-fit'], name: 'app_tools_gpx_to_fit')]
    public function gpxToFit(): Response
    {
        return $this->render('tools/gpx_to_fit.html.twig');
    }

    #[Route(['en' => '/tools/geojson-to-gpx', 'fr' => '/fr/outils/geojson-vers-gpx'], name: 'app_tools_geojson_to_gpx')]
    public function geojsonToGpx(): Response
    {
        return $this->render('tools/geojson_to_gpx.html.twig');
    }

    #[Route(['en' => '/tools/gpx-to-geojson', 'fr' => '/fr/outils/gpx-vers-geojson'], name: 'app_tools_gpx_to_geojson')]
    public function gpxToGeojson(): Response
    {
        return $this->render('tools/gpx_to_geojson.html.twig');
    }
}
