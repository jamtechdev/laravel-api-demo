<?php

namespace App\Http\Controllers\Api\v1\Sidebar;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as MasterController;
use App\Models\Attribute;
use App\Models\AttributeCategory;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductPriceLink;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Controller extends MasterController
{
    public function getAllCategoryAttributes($id, $permalink)
    {
        try {

            $guide = \App\Models\Guide::where('published', true)
                ->where('permalink', 'LIKE', $permalink)
                ->where('category_id', $id)
                ->first();
            // if (Cache::has('data' . $guide->id)) {
            //     return successHandler(
            //         Cache::get('data' . $guide->id),
            //         ResponseCode::OK_CODE,
            //         ResponseMessage::CATEGORY_ATTRIBUTES_FETCHED_SUCCESS_MESSAGE
            //     );
            // }
            $guideProduct = json_decode($guide->order_value);
            $productIds = collect($guideProduct)->map(function ($product) {
                return $product[2];
            });
            $products = Product::whereIn('id', $productIds)
                ->select('brand', 'lowest_price')
                ->with('productPriceLinks')
                ->get();

            $data = [];
            // $data['brands'] = $products->groupBy('brand')
            //     ->map(function ($group) {
            //         return [
            //             'brand' => $group->first()->brand,
            //             'count' => $group->count(),
            //         ];
            //     })
            //     ->values();

            $prices = $products->pluck('lowest_price');

            $data['price'] = [
                'max_price' => $prices->max(),
                'min_price' => $prices->min(),
            ];

            $data['available'] = $products->pluck('productPriceLinks')->flatten()->isNotEmpty();

            Cache::put('product_ids', $productIds, 60);

            $catAttributes = AttributeCategory::with([
                'attribute.productAttr' => function ($query) use ($productIds) {
                    $query->whereIn('product_id', $productIds)->orderBy('attribute_value');
                }
            ])
                ->whereHas('attribute.productAttr', function ($query) use ($productIds) {
                    $query->whereIn('product_id', $productIds);
                })
                ->orderBy('attribute_category_position')
                ->where("category_id", $id)
                ->get();

            $data['attribute_categories'] = new \App\Http\Resources\Guide\SideBar\SideBarCollection($catAttributes);
            Cache::put('data' . $guide->id, $data, now()->addMinutes(60));
            return successHandler(
                $data,
                ResponseCode::OK_CODE,
                ResponseMessage::CATEGORY_ATTRIBUTES_FETCHED_SUCCESS_MESSAGE
            );
        } catch (\Exception $e) {
            dd($e);
            return serverErrorHandler($e);
        }
    }
    public function getAllCategoryAttributesBackup($id, $permalink)
    {
        try {
            $guide = \App\Models\Guide::where('published', true)->where('permalink', 'LIKE', $permalink)->first();
            $guideProduct = json_decode($guide->order_value);
            $productIds = collect($guideProduct)->map(function ($product) {
                return $product[2];
            });
            $catAttributes = AttributeCategory::with([
                'attribute.productAttr' => function ($query) use ($productIds) {
                    $query->whereIn('product_id', $productIds);
                }
            ])
                ->whereHas('attribute.productAttr', function ($query) use ($productIds) {
                    $query->whereIn('product_id', $productIds);
                })
                ->where("category_id", $id)
                ->get();
            return successHandler(
                new \App\Http\Resources\Guide\SideBar\SideBarCollection($catAttributes),
                ResponseCode::OK_CODE,
                ResponseMessage::CATEGORY_ATTRIBUTES_FETCHED_SUCCESS_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
