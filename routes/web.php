<?php
use Illuminate\Support\Facades\Route;
Route::get('/', function () { return view('app'); });
// SPA fallback — JANGAN bentrok dengan /api: pola negatif menolak path yang diawali api/
Route::get('/{any}', function () { return view('app'); })->where('any', '^(?!api\/).*$');
