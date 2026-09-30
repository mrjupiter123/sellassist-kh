<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\CreateSocialChannel;
use App\Domain\Social\Actions\UpdateSocialChannel;
use App\Domain\Social\Models\SocialChannel;
use App\Http\Requests\Social\StoreSocialChannelRequest;
use App\Http\Requests\Social\UpdateSocialChannelRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SocialChannelController extends Controller
{
    public function index(): View
    {
        return view('social.channels.index', [
            'channels' => SocialChannel::query()->withCount(['contacts', 'conversations'])->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSocialChannelRequest $request, CreateSocialChannel $action): RedirectResponse
    {
        $action->execute($request->validated(), $request->user());

        return back()->with('success', 'Messenger channel created successfully.');
    }

    public function update(
        UpdateSocialChannelRequest $request,
        SocialChannel $channel,
        UpdateSocialChannel $action,
    ): RedirectResponse {
        $action->execute($channel, $request->validated());

        return back()->with('success', 'Messenger channel updated successfully.');
    }
}
