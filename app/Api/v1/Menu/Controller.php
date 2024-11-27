<?php

namespace App\Http\Controllers\Api\v1\Menu;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as MasterController;
use App\Http\Resources\Menu\FooterRecource;
use App\Http\Resources\Menu\HeaderRecource;
use App\Models\Footer;
use App\Models\Menu;
use App\Models\OtherPhrase;
use Illuminate\Http\Request;

class Controller extends MasterController
{
    public function header()
    {

        try {
            $menu = Menu::with(['secondaryArchiveCategory', 'secondaryArchiveCategory'])
                // ->where('guide_ids', '!=', '[null]')
                ->take(8)
                ->get();
            $menu = $menu->reject(function ($item) {
                return $item->primary_archive_category_id == "";
            });
            if ($menu) {
                return successHandler(
                    HeaderRecource::collection($menu),
                    ResponseCode::ACCEPTED_CODE,
                    ResponseMessage::MENU_DATA_FETCHED_MESSAGE
                );
            }

            return successHandler(
                null,
                ResponseCode::ACCEPTED_CODE,
                ResponseMessage::MENU_DATA_FETCHED_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
    public function footer()
    {
        try {
            $footer = Footer::first();
            if ($footer) {
                return successHandler(
                    new FooterRecource($footer),
                    ResponseCode::ACCEPTED_CODE,
                    ResponseMessage::MENU_DATA_FETCHED_MESSAGE
                );
            }

            return successHandler(
                null,
                ResponseCode::ACCEPTED_CODE,
                ResponseMessage::MENU_DATA_FETCHED_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
