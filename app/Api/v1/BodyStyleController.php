<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExpandedBodyStyle;
use App\Models\WebVanbodystyle;
use App\Models\WebVanbodystyles;
use App\Models\WebVanbodystyleslink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
class BodyStyleController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'commercial' => 'nullable|boolean',
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }
            $data = [];
            $carvan = (bool) $request->commercial;
            $data = match ($carvan) {
                true => DB::table('web_vanbodystyles as wvbs')
                    ->select('wvbs.id', 'wvbs.vanbodystyle as name')
                    ->distinct()
                    ->join('web_vanbodystyleslink as vbsl', 'wvbs.id', '=', 'vbsl.bodystyle_id')
                    ->join('vehicles as v', DB::raw('TRIM(vbsl.description)'), '=', DB::raw('TRIM(v.manufacturer_model_description)'))
                    ->where('v.commercial', $carvan)
                    ->where('v.current_vehicle', 1)
                    ->orderBy('wvbs.vanbodystyle')
                    ->get(),
                default => DB::table('expanded_body_style')
                    ->select('id', 'description as name')
                    ->whereIn('id', function ($query) use ($carvan) {
                            $query->select('expanded_body_style_id')
                            ->from('vehicles')
                            ->distinct()
                            ->where('commercial', $carvan)
                            ->whereNotNull('expanded_body_style_id');
                        })
                    ->orderBy('description')
                    ->get()
            };
            return $this->successHandler($data, 200, 'Body Types retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }
}
