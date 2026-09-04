<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function index(Request $request)
    {
        $current_token = $request->user()->currentAccessToken();
        $tokens = $request->user()->tokens->where('id', '!=', $current_token->id)->select('id','name')->values();

        // Convert $current_token to an array and filter unwanted keys
        $filteredCurrentTokenArray = collect($current_token)->only('id','name');

        return response()->json([
            'current_token' => $filteredCurrentTokenArray,
            'tokens' => $tokens,
        ]);
    }

    public function destroy(PersonalAccessToken $token)
    {
        $token->delete();

        return \response()->json([
            'message' => 'توکن با موفقت حذف شد',
        ]);
    }
}
