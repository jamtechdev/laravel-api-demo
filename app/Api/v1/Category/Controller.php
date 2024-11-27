<?php

namespace App\Http\Controllers\Api\v1\Category;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as MasterController;
use App\Http\Resources\Category\CategoryArchiveResource;
use App\Http\Resources\Category\PrimaryArchiveCategoryCollection;
use App\Models\Category;
use App\Models\PrimaryArchiveCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Controller extends MasterController
{
    public function showAllCategory(Request $request)
    {
        try {
            $limit = $request->get('limit') ? $request->get('limit') : 8;
            $categories = PrimaryArchiveCategory::whereStatus(true)->with(['secondaryArchiveCat', 'category'])->paginate($limit);
            if ($categories) {
                return successHandler(
                    new PrimaryArchiveCategoryCollection($categories),
                    ResponseCode::OK_CODE,
                    ResponseMessage::CATEGORY_FETCHED_MESSAGE
                );
            } else {
                return successHandler(
                    null,
                    ResponseCode::OK_CODE,
                    ResponseMessage::CATEGORY_FETCHED_MESSAGE
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }

    public function metaData($permalink)
    {
        try {
            $Category = Category::where('title', 'LIKE', '%' . $permalink . '%')->select('title', 'meta_description', 'h1_title', 'main_title')->first();
            if ($Category) {
                return successHandler(
                    $Category,
                    ResponseCode::OK_CODE,
                    ResponseMessage::CATEGORY_FETCHED_MESSAGE
                );
            } else {
                return notFoundErrorHandler(
                    'Category not Found by this title - ' . $permalink,
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }

    public function archivePage($permalink)
    {
        try {
            $category = Category::where('title', 'LIKE',  Str::title(str_replace('-', ' ', $permalink)))->with('products', 'guides', 'blogs')->first();
            if ($category) {
                return successHandler(
                    new CategoryArchiveResource($category),
                    ResponseCode::OK_CODE,
                    ResponseMessage::CATEGORY_FETCHED_MESSAGE
                );
            } else {
                return notFoundErrorHandler(
                    'Category not Found by this title - ' . $permalink,
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
