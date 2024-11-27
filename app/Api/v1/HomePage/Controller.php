<?php

namespace App\Http\Controllers\Api\v1\HomePage;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as MasterController;
use App\Http\Resources\HomePage\AsSeenOnResource;
use App\Http\Resources\HomePage\CategoryResource;
use App\Http\Resources\HomePage\FavoriteGuidesResource;
use App\Http\Resources\HomePage\HomePageResource;
use App\Models\Guide;
use App\Models\HomePage;
use App\Models\HomePagePhrase;
use App\Models\PrimaryArchiveCategory;
use App\Models\Product;
use Illuminate\Http\Request;

class Controller extends MasterController
{
    public function counts()
    {
        try {
            $buying_guides = Guide::where('published', true)->count();
            $products_reviews = Product::where('is_product_in_list', true)->count();
            $reviews_of_users = Product::where('is_product_in_list', true)->sum('no_of_reviews_total');
            $data_compared = Product::where('is_product_in_list', true)->withCount('attributes')->get()->sum('attributes_count');
            $homepage = HomePagePhrase::first();
            $data = [
                'buying_guides' => array(
                    "count" => number_format($buying_guides, 0, '', ' '),
                    ...$homepage->guide
                ),

                'products_reviews' => array(
                    "count" => number_format($products_reviews, 0, '', ' '),
                    ...$homepage->reviews
                ),

                'data_compared' => array(
                    "count" => number_format($data_compared, 0, '', ' '),
                    ...$homepage->data_compared
                ),

                'users_reviews' => array(
                    "count" => number_format($reviews_of_users, 0, '', ' '),
                    ...$homepage->review_users
                ),
            ];
            return successHandler(
                $data,
                ResponseCode::OK_CODE,
                ResponseMessage::HOMEPAGE_FETCHED_SUCCESS_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
    public function index()
    {
        try {
            $homepage = HomePage::with('homePageImages', 'homePageCategories')->first();
            $homepage['favorite_guides'] = collect(FavoriteGuidesResource::collection((new Guide())->favoriteGuides(json_decode($homepage->favorite_guides_order ?? "[]"))));
            $homepage['as_seen_on'] = collect(AsSeenOnResource::collection($homepage->homePageImages));
            $ids = PrimaryArchiveCategory::pluck('id')->toArray();
            $homepage['categories'] = collect(CategoryResource::collection($homepage->homePageCategories()->whereIn('primary_archive_category_id', $ids)->get()));
            return successHandler(
                new HomePageResource($homepage),
                ResponseCode::OK_CODE,
                ResponseMessage::HOMEPAGE_FETCHED_SUCCESS_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
    //home page seo meta datas
    public function meta(Request $request)
    {
        try {
            $homePageData = HomePage::select('title', 'description')->first();
            return successHandler(
                $homePageData,
                ResponseCode::OK_CODE,
                ResponseMessage::HOMEPAGE_FETCHED_SUCCESS_MESSAGE
            );
        } catch (\Exception $ex) {
            return serverErrorHandler($ex);
        }
    }
}
