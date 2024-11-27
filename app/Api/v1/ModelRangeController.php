<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ModelRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class ModelRangeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function getModelRangeByManufacturer(Request $request)
    {
        try {
            $validater = Validator::make($request->all(), [
                'manufacturer_id' => 'required'
            ], [
                'id.required' => 'The Manufacturer id Required'
            ]);
            if ($validater->fails()) {
                return $this->validationErrorHandler($validater->errors()->first());
            }
            $data = ModelRange::where('manufacturer_id', $request->manufacturer_id)
                ->select('id', 'description as name')
                ->groupBy('id', 'description')
                ->orderBy('name')
                ->get();
            if (!$data) {
                $this->errorHandler(404, "Model Range not found");
            }
            return $this->successHandler($data, 200, "Model Ranges get Successfully");
        } catch (\Throwable $th) {
            $this->errorHandler($th->getCode(), $th->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
