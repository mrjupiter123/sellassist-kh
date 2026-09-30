<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\ReceiveMetaWebhook;
use App\Domain\Social\Exceptions\InvalidSocialWebhookException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) ($request->query('hub_mode') ?? $request->query('hub.mode', ''));
        $token = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token', ''));
        $challenge = (string) ($request->query('hub_challenge') ?? $request->query('hub.challenge', ''));
        $expected = (string) config('social.meta.verify_token');

        if ($mode !== 'subscribe' || $expected === '' || ! hash_equals($expected, $token)) {
            abort(403, 'Webhook verification failed.');
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, ReceiveMetaWebhook $action): JsonResponse
    {
        try {
            $result = $action->execute(
                $request->getContent(),
                $request->header('X-Hub-Signature-256'),
            );
        } catch (InvalidSocialWebhookException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        return response()->json([
            'accepted' => true,
            'duplicate' => $result['duplicate'],
        ]);
    }
}
