<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Social\Actions\ReceiveTelegramWebhook;
use App\Domain\Social\Exceptions\InvalidSocialWebhookException;
use App\Domain\Social\Models\SocialChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        SocialChannel $channel,
        ReceiveTelegramWebhook $action,
    ): JsonResponse {
        try {
            $result = $action->execute(
                $channel,
                $request->getContent(),
                $request->header('X-Telegram-Bot-Api-Secret-Token'),
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
