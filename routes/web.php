<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::fallback(function (Request $request) {
    if ($request->is('api/*') || $request->expectsJson()) {
        return response()->json([
            'message' => 'API route not found.',
        ], 404);
    }

    return view('app');
});
