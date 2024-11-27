<?php

namespace App\Http\Controllers\Api\v1\Contact;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as MasterController;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Controller extends MasterController
{
    function store(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    'email' => 'required|email',
                    'mobile_number' => 'required',
                    'comment' => 'required',
                    'description' => 'required',
                ]
            );

            if ($validator->fails()) {
                return validationErrorHandler($validator->errors());
            }

            Contact::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile_number' => $request->mobile_number,
                'comment' => $request->comment,
                'description' => $request->description,
            ]);

            return successHandler(
                '',
                ResponseCode::OK_CODE,
                ResponseMessage::CONTACT_ADDED_SUCCESS_MESSAGE
            );
        } catch (\Throwable $th) {
            return serverErrorHandler($th);
        }
    }
}
