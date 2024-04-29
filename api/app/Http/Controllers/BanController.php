<?php

namespace App\Http\Controllers;

use App\Helpers\UserPointsHelper;
use App\Http\Requests\BanRequest;
use App\Models\Blockade;
use App\Models\Blockage;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BanController extends Controller
{
    public function index()
    {
        if (Auth::payload()->get('role') !== 'admin' && Auth::payload()->get('role') !== 'moderator') return response('Only admin has access', 403);
        $reponse = Blockade::all();
        return response()->json($reponse);
    }

    public function ban(BanRequest $request, $username)
    {
        $request->validated();
        $user = User::where('username', $username)->first();
        if (!$user) return response()->json(['Пользователя с таким именем пользователя не существует'], 404);
        if ($user->role->name === 'admin' || $user->role->name === 'moderator') {
            return response()->json(['Запрещенный'], 403);
        }
        $ban = new Blockade();
        $ban->user_id = $user->id;
        $ban->admin_id = Auth::user()->id;
        $ban->reason = $request->reason;
        $ban->until = $request->until;
        if ($ban->save()) {
            UserPointsHelper::CalculateAndUpdateUserPoints($user);
            return response()->json(['Пользователь успешно заблокирован'], 204);
        }
        return response()->json(['Что-то пошло не так'], 400);
    }

    public function unban($username)
    {
        $role = Auth::payload()->get('role');
        if ($role !== 'admin' && $role !== 'moderator') return response('Доступ есть только у администраторов и модераторов', 403);
        $user = User::where('username', $username)->firstOrFail();
        $currentDate = Carbon::now();
        Blockade::where('user_id', $user->id)
            ->where('until', '>=', $currentDate)
            ->delete();

        return response()->json(['Пользователь успешно разблокирован'], 204);
    }
}
