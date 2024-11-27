<?php

namespace App\Http\Controllers\Api\v1\Getters;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as MasterController;
use App\Models\AboutUs;
use App\Models\Faq;
use App\Models\PrivacyPolicy;
use App\Models\TermsAndCondition;
use Illuminate\Http\Request;

class Controller extends MasterController
{
    public function getAbout()
    {
        try{

            $about = AboutUs::first();
            if($about)
            {
                return successHandler(
                    new \App\Http\Resources\AboutResource($about),
                    ResponseCode::ACCEPTED_CODE,
                    ResponseMessage::ABOUT_DATA_FETCHED_MESSAGE
                );
            }

            return successHandler(
                null,
                ResponseCode::ACCEPTED_CODE,
                ResponseMessage::ABOUT_DATA_FETCHED_MESSAGE
            );
        }
        catch(\Exception $e)
        {
            return serverErrorHandler($e);
        }

    }
    public function getPolicy()
    {
        try{

            $policy = PrivacyPolicy::first();
            if($policy)
            {
                return successHandler(
                    new \App\Http\Resources\PrivacyPolicyResource($policy),
                    ResponseCode::ACCEPTED_CODE,
                    ResponseMessage::POLICY_DATA_FETCHED_MESSAGE
                );
            }

            return successHandler(
                null,
                ResponseCode::ACCEPTED_CODE,
                ResponseMessage::POLICY_DATA_FETCHED_MESSAGE
            );
        }
        catch(\Exception $e)
        {
            return serverErrorHandler($e);
        }

    }
    public function getTerms()
    {
        try{

            $terms = TermsAndCondition::first();
            if($terms)
            {
                return successHandler(
                    new \App\Http\Resources\TermsAndConditionResource($terms),
                    ResponseCode::ACCEPTED_CODE,
                    ResponseMessage::TERMS_DATA_FETCHED_MESSAGE
                );
            }

            return successHandler(
                null,
                ResponseCode::ACCEPTED_CODE,
                ResponseMessage::TERMS_DATA_FETCHED_MESSAGE
            );
        }
        catch(\Exception $e)
        {
            return serverErrorHandler($e);
        }

    }
    public function getFaq()
    {
        try{

            $faq = Faq::all();
            if($faq)
            {
                return successHandler(
                    new \App\Http\Resources\FaqCollection($faq),
                    ResponseCode::ACCEPTED_CODE,
                    ResponseMessage::FAQ_DATA_FETCHED_MESSAGE
                );
            }

            return successHandler(
                null,
                ResponseCode::ACCEPTED_CODE,
                ResponseMessage::FAQ_DATA_FETCHED_MESSAGE
            );
        }
        catch(\Exception $e)
        {
            return serverErrorHandler($e);
        }

    }
}
