<?php

namespace App\Http\Controllers;

use App\Helpers\UserPointsHelper;
use App\Models\Dislike;
use App\Models\Like;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DislikeController extends Controller
{
    public function store($username)
    {
        $sourceUser = Auth::user();
        $targetUser = User::where('username', $username)->first();

        $sourceUser = User::find($sourceUser->id);
        if (!$targetUser) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        if (strtolower($username) === strtolower($sourceUser->username)) {
            return response()->json(['error' => 'Вы не можете нравиться себе'], 400);
        }

        $userLike = Dislike::where('source_user_id', $sourceUser->id)
            ->where('target_user_id', $targetUser->id)
            ->first();

        if ($userLike) {
            return response('Вам уже нравится этот пользователь', 400);
        }

        $userLike = new Dislike();
        $userLike->source_user_id = $sourceUser->id;
        $userLike->target_user_id = $targetUser->id;

        Dislike::create($userLike->toArray());

        if ($sourceUser->save()) {
            return response()->json(['message' => 'Пользователь понравился'], 200);
        }

        return response('Не удалось лайкнуть пользователя', 400);
    }

    public function resetDislikes()
    {
        $sourceUser = Auth::user();
        if(Dislike::where('source_user_id', $sourceUser->id)->delete())
            return response()->json(['message' => 'Диз лайки успешно сброшены'], 200);

        return response()->json(['У вас нет никаких диз лайков, которые можно было бы сбросить'], 400);
    }
}
