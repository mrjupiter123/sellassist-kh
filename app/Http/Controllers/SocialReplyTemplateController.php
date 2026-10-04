<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\CreateSocialReplyTemplate;
use App\Domain\Social\Actions\UpdateSocialReplyTemplate;
use App\Domain\Social\Models\SocialReplyTemplate;
use App\Http\Requests\Social\StoreSocialReplyTemplateRequest;
use App\Http\Requests\Social\UpdateSocialReplyTemplateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SocialReplyTemplateController extends Controller
{
    public function index(): View
    {
        return view('social.reply-templates.index', [
            'templates' => SocialReplyTemplate::query()->with('updater:id,name')->orderBy('title')->get(),
        ]);
    }

    public function store(
        StoreSocialReplyTemplateRequest $request,
        CreateSocialReplyTemplate $action,
    ): RedirectResponse {
        $action->execute($request->validated(), $request->user());

        return back()->with('success', 'Reply template created.');
    }

    public function update(
        UpdateSocialReplyTemplateRequest $request,
        SocialReplyTemplate $template,
        UpdateSocialReplyTemplate $action,
    ): RedirectResponse {
        $action->execute($template, $request->validated(), $request->user());

        return back()->with('success', 'Reply template updated.');
    }
}
