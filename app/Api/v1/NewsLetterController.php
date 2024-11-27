<?php

namespace App\Http\Controllers\Api\v1;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller;
use App\Models\NewsLetter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsLetterController extends Controller
{
    public function newsLetter(Request $request)
    {
        try {

            return errorHandler(ResponseCode::UNPROCESSABLE_ENTITY_CODE, "Sorry newsletter is not ready yet, try later.");

            // $validator = Validator::make(
            //     $request->all(),
            //     [
            //         'email' => 'email|required|unique:news_letters',
            //     ],
            //     [
            //         'email.unique' => 'You have already Subscribed'
            //     ]
            // );

            // if ($validator->fails()) {
            //     return validationErrorHandler($validator->errors());
            // }

            // $news = new NewsLetter();
            // $news->email = $request->email;
            // $news->save();

            // return successHandler(
            //     new \App\Http\Resources\NewsLetterResource($news),
            //     ResponseCode::ACCEPTED_CODE,
            //     ResponseMessage::NEWSLETTER_SUBSCRIPTION_SUCCESS_MESSAGE
            // );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
