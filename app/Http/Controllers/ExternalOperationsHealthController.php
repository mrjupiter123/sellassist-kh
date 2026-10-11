<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Operations\Services\ExternalOperationsHealth;
use Illuminate\Http\Response;

class ExternalOperationsHealthController extends Controller
{
    public function __invoke(ExternalOperationsHealth $health): Response
    {
        return response('', $health->isHealthy() ? 204 : 503)
            ->header('Cache-Control', 'no-store, private');
    }
}
