<?php

namespace App\Http\Controllers\Api\v1\AboutUs;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as masterController;
use App\Http\Resources\AboutUs\AboutUsResource;
use App\Http\Resources\Author\AuthorResource;
use App\Models\AboutUs;
use Spatie\Permission\Models\Role;

class Controller extends masterController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        try {
            $aboutus = AboutUs::first();
            $authorRole = Role::where('name', 'author')->first();
            $authors = $authorRole->users;
            $aboutus['authors'] = AuthorResource::collection($authors);
            if ($aboutus) {
                return successHandler(
                    new AboutUsResource($aboutus),
                    ResponseCode::OK_CODE,
                    ResponseMessage::ABOUTUS_FETCHED_SUCCESS_MESSAGE
                );
            }
            return notFoundErrorHandler(
                ResponseMessage::ABOUTUS_NOT_FOUND_UID_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
