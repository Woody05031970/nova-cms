<?php

namespace App\Http\Controllers;

use App\Support\Geo\Geo;
use App\Support\Seo\SiteSeo;
use Illuminate\Http\Response;

class SeoFileController extends Controller
{
    public function robots(SiteSeo $seo): Response
    {
        return response($seo->robots(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Übersicht der Website für KI-Systeme (Modul KI-Sichtbarkeit) */
    public function llms(Geo $geo): Response
    {
        abort_unless(Geo::llmsEnabled(), 404);

        return response($geo->llmsTxt(), 200, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    public function sitemap(SiteSeo $seo): Response
    {
        abort_unless(SiteSeo::indexingAllowed(), 404);

        return response($seo->sitemap(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
