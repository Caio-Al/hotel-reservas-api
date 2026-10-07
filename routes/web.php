<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Documentação interativa (Swagger UI) e o contrato OpenAPI que ela lê.
Route::view('/docs', 'docs');
Route::get('/docs/openapi.yaml', function () {
    return response()->file(base_path('docs/openapi.yaml'), ['Content-Type' => 'application/yaml']);
});
