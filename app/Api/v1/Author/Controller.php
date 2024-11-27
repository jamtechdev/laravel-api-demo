<?php

namespace App\Http\Controllers\Api\v1\Author;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as masterController;
use App\Http\Resources\Author\AuthorAllDataResource;
use App\Models\User;
use Illuminate\Http\Request;

class Controller extends masterController
{
    // author by id
    public function author($id)
    {
        try {
            $author = User::where('permalink', $id)->first();
            if ($author) {
                return successHandler(
                    new AuthorAllDataResource($author),
                    ResponseCode::OK_CODE,
                    ResponseMessage::AUTHOR_FETCHED_SUCCESS_MESSAGE
                );
            }
            return notFoundErrorHandler(
                ResponseMessage::AUTHOR_NOT_FOUND_UID_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
