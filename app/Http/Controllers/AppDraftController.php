<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AppDraftController extends Controller
{
    private function key(Request $request)
    {
        $scope = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', (string) $request->input('scope', 'default'));
        return 'dekatpos:server-draft:' . Auth::id() . ':' . $scope;
    }

    public function show(Request $request)
    {
        return response()->json([
            'draft' => Cache::store('file')->get($this->key($request)),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'scope' => 'required|string|max:120',
            'draft' => 'required',
        ]);

        Cache::store('file')->put($this->key($request), $request->input('draft'), now()->addDays(7));

        return response()->json(['saved' => true]);
    }

    public function destroy(Request $request)
    {
        Cache::store('file')->forget($this->key($request));

        return response()->json(['deleted' => true]);
    }
}
