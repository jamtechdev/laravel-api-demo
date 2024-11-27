<?php

namespace App\Http\Controllers\Api\v1\AppLogo;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as masterController;
use App\Models\FrontendLogo;
use Illuminate\Http\Request;

class Controller extends masterController
{
    function appLogo()
    {
        try {
            $logo = FrontendLogo::first();
            if ($logo) {
                return successHandler(
                    [
                        'logo' => $logo->logo_url,
                        'favicon' => $logo->favicon_url
                    ],
                    ResponseCode::OK_CODE,
                    'App Logo Fetched Successfully'
                );
            }
            return notFoundErrorHandler(
                "App Logo Not Found"
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
