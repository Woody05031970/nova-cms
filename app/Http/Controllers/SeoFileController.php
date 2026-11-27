<?php

namespace App\Http\Controllers;

use App\Support\Seo\SiteSeo;
use Illuminate\Http\Response;

class SeoFileController extends Controller
{
    public function robots(SiteSeo $seo): Response
    {
        return response($seo->robots(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(SiteSeo $seo): Response
    {
        abort_unless(SiteSeo::indexingAllowed(), 404);

        return response($seo->sitemap(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
