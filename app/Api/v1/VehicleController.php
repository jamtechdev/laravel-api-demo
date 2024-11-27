<?php

namespace App\Http\Controllers\Api\V1;
use App\Facades\VehicleValidate;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Http\Resources\ModelRangeVehicleCollection;
use App\Http\Resources\VehicleCollection;
use App\Http\Resources\VehicleDetailResource;
use App\Http\Resources\VehicleResource;
use App\Models\Manufacturer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Wltpmkwh;
use App\Models\Wltpmpg;
use App\Services\VehicleDetailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class VehicleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $key = 'vehicle' . collect($request->all())->implode('-');
            $seconds = 3600; // 1 hour...
            return Cache::remember($key, $seconds, function () use ($request) {
                $data = Vehicle::applyFilters($request)
                    ->with('manufacturer', 'fuelType', 'bodyStyle', 'expandedBodyStyle')
                    ->paginate(20);
                return $this->successHandler(new VehicleCollection($data), 200, 'Vehicles retrieved successfully');
            });
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }
    public function getVehicleByModelRange(Request $request)
    {
        try {
            // dd(Wltpmpg::where('id', 19811)->get());
            $validator = Validator::make($request->all(), [
                'id' => 'required',
                'fuel_type_id' => 'nullable'
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }
            $key = "vehicle-model{$request->user->id}" . collect($request->all())->implode('-');
            $seconds = 3600; // 1 hour...
            return Cache::remember($key, $seconds, function () use ($request) {
                $data = Vehicle::where('model_range_id', $request->id)
                    ->where('current_vehicle', 1)
                    ->orderBy('on_the_road_price')
                    ->orderBy('ids_code')
                    ->when(isset($request->fuel_type_id), function ($query) use ($request) {
                        $query->where('fuel_type_id', $request->fuel_type_id);
                    })
                    ->with(
                        'manufacturer',
                        'fuelType',
                        'bodyStyle',
                        'expandedBodyStyle',
                        'wltpmpg',
                        'wltpmkwh',
                        'wltpco2',
                        'wltprange'
                    )
                    ->paginate(20);
                return $this->successHandler(new ModelRangeVehicleCollection($data), 200, 'Vehicles retrieved successfully');
            });
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }
    public function getVehicleById(Request $request)
    {
        try {
            // dd(Wltpmpg::where('id', 19811)->get());
            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }
            $data = Vehicle::where('id', $request->id)
                ->with(
                    'manufacturer',
                    'fuelType',
                    'bodyStyle',
                    'expandedBodyStyle',
                    'wltpmpg',
                    'wltpmkwh',
                    'wltpco2',
                    'wltprange'
                )
                ->first();
            return $this->successHandler(new VehicleResource($data), 200, 'Vehicle retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }
    public function modelRangeVehicles(Request $request)
    {
        try {
            // dd(Wltpmpg::where('id', 19811)->get());
            $validator = Validator::make($request->all(), [
                'model_range_id' => 'required',
                'year' => 'nullable'
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }
            if (isset($request->year)) {
                $modelgroup = Vehicle::where('model_range_id', $request->model_range_id)
                    ->where('current_vehicle', 1)
                    ->select('model_tree_description')
                    ->first()?->model_tree_description;
                $data = Vehicle::where('model_range_id', $request->model_range_id)
                    ->where('current_vehicle', 1)
                    ->where('model_tree_description', $modelgroup)
                    ->select('id', 'description as name')
                    ->get();
            } else {
                $data = Vehicle::where('model_range_id', $request->model_range_id)
                    ->where('current_vehicle', 1)
                    ->select('id', 'description as name')
                    ->get();
            }
            return $this->successHandler($data, 200, 'Vehicle retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }

    public function getVehicleDetails(Request $request)
    {
        try {
            // dd(Wltpmpg::where('id', 19811)->get());
            $validator = Validator::make($request->all(), [
                'vehicle_id' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }

            // SELECT sn_code, sn_variable FROM cronus WHERE sn_front=1 and sn_location=1
            // $stmt = DB::table('cronus')->where([
            //     'sn_front' => 1,
            //     'sn_location' => 1
            // ])
            //     ->select('sn_code', 'sn_variable')
            //     ->get()->map(function ($item) {
            //         $item->sn_code = $item->sn_variable;
            //     });

            // dd($stmt);


            $data = Vehicle::where('id', $request->vehicle_id)
                ->with(
                    'manufacturer',
                    'fuelType',
                    'bodyStyle',
                    'expandedBodyStyle',
                    'wltpmpg',
                    'wltpmkwh',
                    'wltpco2',
                    'wltprange',
                    'warrantie'
                )
                ->first();

            // $vehicleDetailService = new VehicleDetailService($data , $request->term, $request->mileage);

            // $values =  get_object_vars($vehicleDetailService);

            // dd($values);
            return $this->successHandler(new VehicleDetailResource($data), 200, 'Vehicle retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }
    public function getVehiclesDetails(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'vehicle1_id' => 'required',
                'vehicle2_id' => 'nullable',
                'vehicle3_id' => 'nullable',
                'vehicle4_id' => 'nullable',
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }
            $ids = [
                $request?->vehicle1_id,
                $request?->vehicle2_id,
                $request?->vehicle3_id,
                $request?->vehicle4_id
            ];
            $data = Vehicle::whereIn('id', $ids)
                ->with(
                    'manufacturer',
                    'fuelType',
                    'bodyStyle',
                    'expandedBodyStyle',
                    'wltpmpg',
                    'wltpmkwh',
                    'wltpco2',
                    'wltprange',
                    'warrantie'
                )
                ->get();
            return $this->successHandler(VehicleDetailResource::collection($data), 200, 'Vehicle retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }
    public function getVehiclesDetailsPdf(Request $request)
    {
        try {
            $request->user = User::find(3);
            $validator = Validator::make($request->all(), [
                'vehicle1_id' => 'required',
                'vehicle2_id' => 'nullable',
                'vehicle3_id' => 'nullable',
                'vehicle4_id' => 'nullable',
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }
            $ids = [
                $request?->vehicle1_id,
                $request?->vehicle2_id,
                $request?->vehicle3_id,
                $request?->vehicle4_id
            ];
            $key = 'vehicle' . collect($request->all())->implode('-');
            $seconds = 3600; // 1 hour...
            $data = Cache::remember($key, $seconds, function () use ($ids) {
                $data = Vehicle::whereIn('id', $ids)
                    ->with(
                        'manufacturer',
                        'fuelType',
                        'bodyStyle',
                        'expandedBodyStyle',
                        'wltpmpg',
                        'wltpmkwh',
                        'wltpco2',
                        'wltprange',
                        'warrantie'
                    )
                    ->get();
                return response()->json(VehicleDetailResource::collection($data));
            });
            $cars = $data->getData();
            // Load the view
            $pdf = Pdf::loadView('components.pdf-report', compact('cars'));

            // Set paper size (e.g., 'A4', 'letter') and orientation ('portrait', 'landscape')
            $pdf->setPaper('A4', 'landscape'); // Use 'landscape' for horizontal layout

            // // Optionally, adjust margins to fit more content
            // $pdf->set_option('margin-top', 5);
            // $pdf->set_option('margin-bottom', 5);
            // $pdf->set_option('margin-left', 10);
            // $pdf->set_option('margin-right', 10);

            // Return the PDF as a response to download or display
            return $pdf->stream('report.pdf');
        } catch (\Throwable $th) {
            dd($th);
            return $this->errorHandler(500, $th->getMessage());
        }
    }

    public function searchVehicle(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'vrm' => ['required', 'regex:/^[a-zA-Z0-9_ \-~.]+$/'],
            ], [
                'vrm.regex' => 'The VRM field contains invalid characters.',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }
            $key = 'vehicle-search' . collect($request->all())->implode('-');
            $seconds = 3600; // 1 hour...
            return Cache::remember($key, $seconds, function () use ($request) {
                $id = VehicleValidate::getVehicleData($request->vrm);
                if (!$id) {
                    return $this->errorHandler(500, 'Vehicle not found');
                }
                $data = [
                    'vehicle_id' => $id,
                ];
                return $this->successHandler($data, 200, 'Vehicle Id retrieved successfully');
            });
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }
}
