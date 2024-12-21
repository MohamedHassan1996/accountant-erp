<?php

namespace App\Http\Controllers\Api\Private\Parameter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Paramter\CreateParamterRequest;
use App\Http\Requests\Paramter\UpdateParamterRequest;
use App\Models\ParameterValue;
use App\Services\Parameter\ParameterService;
use Illuminate\Http\Request;

class ParameterController extends Controller
{
    private $parameterService;
    public function __construct(ParameterService $parameterService)
    {
        $this->middleware('auth:api');
        $this->parameterService = $parameterService;
    }
    /**
     * Display a listing of the resource.
     */
    public function allparameters(Request $request)
    {
        return $this->parameterService->getAllParameters($request->parameterOrder);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(CreateParamterRequest $request)
    {
        return $this->parameterService->createParameter($request->validated());
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        return $this->parameterService->editParameter($request->parameterId);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateParamterRequest $request)
    {
        return $this->parameterService->updateParameter($request->validated());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Request $request)
    {   
        return $this->parameterService->deleteParameter($request->parameterId);

    }

}
