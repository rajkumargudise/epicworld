<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\SiteSettings;
use Illuminate\Http\Response;

/**
 * ads.txt for Google AdSense, generated from the publisher ID saved in
 * Admin > Site & ads. 404 until an ID is configured, so nothing is
 * advertised that the site doesn't actually have.
 */
class AdsTxtController extends Controller
{
    public function __invoke(SiteSettings $site): Response
    {
        $id = $site->adsTxtId();

        abort_if($id === null, 404);

        return response("google.com, {$id}, DIRECT, f08c47fec0942fa0\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
