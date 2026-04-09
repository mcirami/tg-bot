<?php

namespace App\Http\Controllers;
use App\Models\TelegramAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelegramController extends Controller
{
    public function showConnectForm()
    {
        return view('telegram.connect');
    }

    public function sendCode(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        $user = Auth::user();

        TelegramAccount::updateOrCreate(
            ['user_id' => $user->id],
            ['phone_number' => $request->phone_number]
        );

        // 🔥 next step later: call Python API

        return redirect()->route('dashboard')->with('success', 'Code sent (mock for now)');
    }

}
