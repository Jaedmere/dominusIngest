<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BotController; // <--- ESTA LÍNEA ES VITAL

// Ruta de prueba (puedes dejarla o borrarla)
Route::get('/prueba', function () {
    return response()->json(['mensaje' => 'API activa']);
});

// --- RUTA DEL BOT ---
// Esta es la que n8n está buscando
Route::post('/bot/ventas', [BotController::class, 'consultarVentas']);