<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MileageResource;
use App\Models\WebDdl;
use Illuminate\Http\Request;

class MileageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $data = WebDdl::where('ddl_id', 1)->get();
            return $this->successHandler(MileageResource::collection($data), 200, "Mileage Range get Successfully");
        } catch (\Exception $e) {
            $this->errorHandler($e->getCode(), $e->getMessage());
        }
    }
    public function termsTypes()
    {
        try {
            $data = WebDdl::where('ddl_id', 2)->get();
            return $this->successHandler(MileageResource::collection($data), 200, "Mileage Range get Successfully");
        } catch (\Exception $e) {
            $this->errorHandler($e->getCode(), $e->getMessage());
        }
    }
}
