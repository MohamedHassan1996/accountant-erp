<?php

use App\Http\Controllers\Api\Private\Parameter\ParameterController;
use App\Http\Controllers\Api\Private\Parameter\ParameterValueController;
use App\Http\Controllers\Api\Private\Select\SelectController;
use App\Http\Controllers\Api\Private\User\UserController;
use App\Http\Controllers\Api\Public\Auth\AuthController;
use App\Models\Parameter\ParameterValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1/auth')->group(function(){
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
});

Route::prefix('v1/users')->group(function(){
    Route::get('', [UserController::class, 'index']);
    Route::post('create', [UserController::class, 'create']);
    Route::get('edit', [UserController::class, 'edit']);
    Route::put('update', [UserController::class, 'update']);
    Route::delete('delete', [UserController::class, 'delete']);
    Route::put('change-status', [UserController::class, 'changeStatus']);
});

Route::prefix('v1/parameters')->group(function(){
    Route::get('', [ParameterValueController::class, 'index']);
    Route::post('create', [ParameterValueController::class, 'create']);
    Route::get('edit', [ParameterValueController::class, 'edit']);
    Route::put('update', [ParameterValueController::class, 'update']);
    Route::delete('delete', [ParameterValueController::class, 'delete']);
});

Route::prefix('v1/selects')->group(function(){
    Route::get('', [SelectController::class, 'getSelects']);
});
