<?php

use App\Http\Controllers\Api\Private\Client\ClientPaymentTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Parameter\ParameterValue;
use App\Http\Controllers\Api\Public\Auth\AuthController;
use App\Http\Controllers\Api\Private\Task\TaskController;
use App\Http\Controllers\Api\Private\User\UserController;
use App\Http\Controllers\Api\Private\Client\ClientController;
use App\Http\Controllers\Api\Private\Select\SelectController;
use App\Http\Controllers\Api\Private\Reports\ReportController;
use App\Http\Controllers\Api\Private\Task\AdminTaskController;
use App\Http\Controllers\Api\Private\Invoice\InvoiceController;
use App\Http\Controllers\Api\Private\Task\ActiveTaskController;
use App\Http\Controllers\Api\Private\Task\TaskTimeLogController;
use App\Http\Controllers\Api\Private\Parameter\ParameterController;
use App\Http\Controllers\Api\Private\Client\ClientAddressController;
use App\Http\Controllers\Api\Private\Client\ClientContactController;
use App\Http\Controllers\Api\Private\Parameter\ParameterValueController;
use App\Http\Controllers\Api\Private\ServiceCategory\ServiceCategoryController;
use App\Http\Controllers\Api\Private\Client\ClientServiceCategoryDiscountController;
use App\Http\Controllers\ImportClientController;
use App\Http\Controllers\ImportServiceCategoryController;

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

Route::prefix('v1/clients')->group(function(){
    Route::get('', [ClientController::class, 'index']);
    Route::post('create', [ClientController::class, 'create']);
    Route::get('edit', [ClientController::class, 'edit']);
    Route::put('update', [ClientController::class, 'update']);
    Route::delete('delete', [ClientController::class, 'delete']);
});

Route::prefix('v1/client-addresses')->group(function(){
    Route::get('', [ClientAddressController::class, 'index']);
    Route::post('create', [ClientAddressController::class, 'create']);
    Route::get('edit', [ClientAddressController::class, 'edit']);
    Route::put('update', [ClientAddressController::class, 'update']);
    Route::delete('delete', [ClientAddressController::class, 'delete']);
});

Route::prefix('v1/client-contacts')->group(function(){
    Route::get('', [ClientContactController::class, 'index']);
    Route::post('create', [ClientContactController::class, 'create']);
    Route::get('edit', [ClientContactController::class, 'edit']);
    Route::put('update', [ClientContactController::class, 'update']);
    Route::delete('delete', [ClientContactController::class, 'delete']);
});

Route::prefix('v1/service-categories')->group(function(){
    Route::get('', [ServiceCategoryController::class, 'index']);
    Route::post('create', [ServiceCategoryController::class, 'create']);
    Route::get('edit', [ServiceCategoryController::class, 'edit']);
    Route::put('update', [ServiceCategoryController::class, 'update']);
    Route::delete('delete', [ServiceCategoryController::class, 'delete']);
});

Route::prefix('v1/client-service-discounts')->group(function(){
    Route::post('changeShow', [ClientServiceCategoryDiscountController::class, 'changeShow']);
    Route::get('', [ClientServiceCategoryDiscountController::class, 'index']);
    Route::post('create', [ClientServiceCategoryDiscountController::class, 'create']);
    Route::get('edit', [ClientServiceCategoryDiscountController::class, 'edit']);
    Route::put('update', [ClientServiceCategoryDiscountController::class, 'update']);
    Route::delete('delete', [ClientServiceCategoryDiscountController::class, 'delete']);
});

Route::prefix('v1/tasks')->group(function(){
    Route::get('', [TaskController::class, 'index']);
    Route::post('create', [TaskController::class, 'create']);
    Route::get('edit', [TaskController::class, 'edit']);
    Route::put('update', [TaskController::class, 'update']);
    Route::delete('delete', [TaskController::class, 'delete']);
    Route::put('change-status', [TaskController::class, 'changeStatus']);
});

Route::prefix('v1/admin-tasks')->group(function(){
    Route::get('', [AdminTaskController::class, 'index']);
});

Route::prefix('v1/task-time-logs')->group(function(){
    Route::get('', [TaskTimeLogController::class, 'index']);
    Route::post('create', [TaskTimeLogController::class, 'create']);
    Route::get('edit', [TaskTimeLogController::class, 'edit']);
    Route::put('update', [TaskTimeLogController::class, 'update']);
    Route::delete('delete', [TaskTimeLogController::class, 'delete']);
});

Route::prefix('v1/user-active-tasks')->group(function(){
    Route::get('', [ActiveTaskController::class, 'index']);
    Route::put('update', [ActiveTaskController::class, 'update']);
});

Route::prefix('v1/invoices')->group(function(){
    Route::get('', [InvoiceController::class, 'index']);
    Route::post('create', [InvoiceController::class, 'create']);
});

Route::prefix('v1/parameters')->group(function(){
    Route::get('', [ParameterValueController::class, 'index']);
    Route::post('create', [ParameterValueController::class, 'create']);
    Route::get('edit', [ParameterValueController::class, 'edit']);
    Route::put('update', [ParameterValueController::class, 'update']);
    Route::delete('delete', [ParameterValueController::class, 'delete']);
});
Route::prefix('v1/reports')->group(function(){
Route::get('', ReportController::class);
});
Route::prefix('v1/selects')->group(function(){
    Route::get('', [SelectController::class, 'getSelects']);
});

Route::prefix('v1/import-clients')->group(function(){
    Route::post('', [ImportClientController::class, 'index']);
});

Route::prefix('v1/import-service-categories')->group(function(){
    Route::post('', [ImportServiceCategoryController::class, 'index']);
});

Route::prefix('v1/client-payment-type')->group(function(){
    Route::post('', [ClientPaymentTypeController::class, 'index']);
});


