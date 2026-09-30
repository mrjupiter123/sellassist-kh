<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Operations\Actions\RetryFailedJob;
use Illuminate\Http\RedirectResponse;

class FailedJobController extends Controller
{
    public function retry(string $uuid, RetryFailedJob $action): RedirectResponse
    {
        $action->execute($uuid);

        return back()->with('success', 'Failed job queued for retry.');
    }
}
