<?php

namespace App\Http\Controllers\Api\v1\Chart;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChartController extends Controller
{
    public function chartDetails(Request $request)
    {
        $validator = Validator::make($request->all(),
        [
            'product_uid' => 'required',
            'attribute'=> 'required',
            'chart_type' => 'required',
        ]);

        if ($validator->fails()) {
            return validationErrorHandler($validator->errors());
        }

        
    }
}
