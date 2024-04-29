<?php

namespace App\Http\Controllers;

use App\Helpers\UserLikeStatusHelper;
use App\Helpers\UserPointsHelper;
use App\Helpers\UserResponseHelper;
use App\Http\Requests\LikesIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\Dislike;
use App\Models\Like;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Photo;

class LikeController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(LikesIndexRequest $request)
    {
        $request->validated();
        $userId = Auth::user()->id;
        $predicate = $request->input('predicate');

        $users = User::orderBy('username');

        if ($predicate == 'liked') {
            $users->whereHas('likedByUsers', function ($query) use ($userId) {
                $query->where('source_user_id', $userId);
            });
        }

        if ($predicate == 'likedBy') {
            $users->whereHas('likes', function ($query) use ($userId) {
                $query->where('target_user_id', $userId);
            });
        }

        if ($predicate == 'matches') {
            $users->whereHas('likes', function ($query) use ($userId) {
                $query->where('target_user_id', $userId)
                    ->where('is_mutual', true);
            });
        }

        $users = $users->get();
        if (Auth::check()) {
            $users = UserLikeStatusHelper::getLikeStatuses($users,$userId);
        }

        return response()->json(UserResource::collection($users));
    }

    public function store($username)
    {
        $sourceUser = Auth::user();
        $likedUser = User::where('username', $username)->first();

        $sourceUser = User::find($sourceUser->id);
        if (!$likedUser) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        if (strtolower($username) === strtolower($sourceUser->username)) {
            return response()->json(['error' => 'Вы не можете ставить лайк себе'], 400);
        }

        $userLike = Like::where('source_user_id', $sourceUser->id)
            ->where('target_user_id', $likedUser->id)
            ->first();

        if ($userLike) {
            return response('Вам уже нравится этот пользователь', 400);
        }

        $userLike = new Like();
        $userLike->source_user_id = $sourceUser->id;
        $userLike->target_user_id = $likedUser->id;

        $mutualLike = Like::where('target_user_id',$sourceUser->id)->where('source_user_id',$likedUser->id)->first();
        if($mutualLike) {
            $mutualLike->is_mutual=true;
            $userLike->is_mutual=true;
            $mutualLike->save();
        } else $userLike->is_mutual=false;

        Like::create($userLike->toArray());

        if ($sourceUser->save()) {
            UserPointsHelper::calculateAndUpdateUserPoints($likedUser);
            return response()->json(['message' => 'Пользователю успешно понравилось','isMatch'=>$userLike->is_mutual], 200);
        }

        return response('Не удалось лайкнуть пользователя', 400);
    }


    public function destroy($username)
    {
        $sourceUser = Auth::user();
        $likedUser = User::where('username', $username)->firstOrFail();


        $userLike = Like::where('source_user_id', $sourceUser->id)
            ->where('target_user_id', $likedUser->id)
            ->first();

        if (!$userLike) {
            return response()->json(['error' => 'Пользователь не нравиться'], 400);
        }

        $isMutual = $userLike->is_mutual;

        if ($isMutual) {
            $mutualLike = Like::where('target_user_id', $sourceUser->id)
                ->where('source_user_id', $likedUser->id)
                ->first();

            if ($mutualLike) {
                $mutualLike->is_mutual = false;
                $mutualLike->save();
            }
        }

        if ($userLike->delete()) {
            UserPointsHelper::calculateAndUpdateUserPoints($likedUser);
            return response()->json(['message' => 'Пользователь больше не нравиться', 'wasMatch' => $isMutual], 200);
        }
        return response()->json(['error' => 'Не удалось убрать лайк с пользователя'], 400);
    }
}
