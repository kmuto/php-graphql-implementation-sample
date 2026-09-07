<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/healthcheck', function () {
    return response()->json(['status' => 'ok']);
});
