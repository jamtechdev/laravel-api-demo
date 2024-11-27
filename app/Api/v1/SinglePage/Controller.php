<?php

namespace App\Http\Controllers\Api\v1\SinglePage;

use App\Constants\ResponseCode;
use App\Http\Controllers\Controller as masterController;
use App\Http\Resources\SinglePageResource;
use App\Models\SinglePage;
use Illuminate\Http\Request;

class Controller extends masterController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $permalink)
    {
        try {
            $page = SinglePage::where('permalink', $permalink)->where('published', 1)->first();
            if ($page) {
                return successHandler(
                    new SinglePageResource($page),
                    ResponseCode::OK_CODE,
                    "Single Page Fetched Successfully"
                );
            }
            return notFoundErrorHandler(
                "Single Page Not Found"
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
