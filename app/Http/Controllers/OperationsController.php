<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Operations\Services\OperationalHealthService;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function __invoke(OperationalHealthService $health): View
    {
        return view('operations.index', [
            'health' => $health->summary(),
            'failedJobs' => $health->failedJobs(),
        ]);
    }
}
