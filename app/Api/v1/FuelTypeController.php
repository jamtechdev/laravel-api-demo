<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FuelTypeResource;
use App\Models\FuelType;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FuelTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'commercial' => 'nullable|boolean',
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }
            // $data = FuelType::when($request->commercial, function ($query, $request) {
            //     $query->whereHas('vehicles', function ($query) use ($request) {
            //         $query->where('commercial', $request->commercial);
            //     });
            // })
            // ->groupBy(['fuel_types.id','fuel_types.name'])
            // ->get();


            $data = FuelType::select('fuel_types.id', 'fuel_types.name')
                ->join('vehicles', 'fuel_types.id', '=', 'vehicles.fuel_type_id')
                ->where('vehicles.current_vehicle', 1)
                ->when($request->commercial, function ($query) use ($request) {
                    $query->where('vehicles.commercial', $request->commercial);
                })
                ->groupBy('fuel_types.id', 'fuel_types.name')
                ->orderBy('fuel_types.name')
                ->get();
            return $this->successHandler(FuelTypeResource::collection($data), 200, 'Fuel Types retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function getfueltypesByVehicleModelRange(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'model_range_id' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }

            $data = FuelType::select('fuel_types.id', 'fuel_types.name')
                ->join('vehicles', 'fuel_types.id', '=', 'vehicles.fuel_type_id')
                ->where('vehicles.current_vehicle', 1)
                ->where('vehicles.model_range_id', $request->model_range_id)
                ->when($request->commercial, function ($query) use ($request) {
                    $query->where('vehicles.commercial', $request->commercial);
                })
                ->groupBy('fuel_types.id', 'fuel_types.name')
                ->orderBy('fuel_types.name')
                ->get();
            return $this->successHandler(FuelTypeResource::collection($data), 200, 'Fuel Types retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
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
