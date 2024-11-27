<?php

namespace App\Http\Controllers\Api\v1\Guide;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as MasterController;
use App\Http\Resources\Guide\GuideByPermalinkResource;
use App\Http\Resources\Guide\GuideByPrimaryCategoryResource;
use App\Http\Resources\Guide\GuideProductCollection;
use App\Http\Resources\Guide\GuideProductResource;
use App\Http\Resources\Guide\GuideProductTableResource;
use App\Models\Attribute;
use App\Models\AttributeCategory;
use App\Models\Category;
use App\Models\Guide;
use App\Models\PrimaryArchiveCategory;
use App\Models\Product;
use App\Models\ProductPriceLink;
use App\Models\SecondaryArchiveCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class Controller extends MasterController
{

    public function guideByPermalink($category, $permalink)
    {
        try {
            $guide = Guide::where('published', true)
                ->whereHas('category', function ($query) use ($category) {
                    $query->where('title', 'LIKE', str_replace('-', ' ', $category));
                })
                ->where('permalink', 'LIKE', $permalink)->first();
            if (is_object($guide)) {
                $guideProduct = json_decode($guide->order_value);
                $productIds = collect($guideProduct)->map(function ($product) {
                    return $product[2];
                });
                $productNames = collect($guideProduct)->map(function ($product) {
                    return $product[0];
                });
                $guide['product_list'] = $productNames;

                //product count
                $category = $guide->category;
                $productQuery = Product::where('is_product_in_list', true)->whereIn('id', $productIds);
                $product_count = $productQuery->count();
                $brand_count = $productQuery->distinct()->count('brand');
                $attribute_count = $category->attributes->count();
                $data_compared_count = ($product_count * ($attribute_count + 4));
                $sum = Product::whereIn('id', $productIds)->sum('no_of_reviews_total');
                // dd($sum);
                $data = [
                    'products' => array(
                        "count" => number_format($product_count, 0, '', ' '),
                        "heading" => $guide->page_phases['products'] ?? "Products",
                    ),

                    'brands' => array(
                        "count" => number_format($brand_count, 0, '', ' '),
                        "heading" => $guide->page_phases['brand'] ?? "Brands",
                    ),

                    'data_compared' => array(
                        "count" => number_format($data_compared_count, 0, '', ' '),
                        "heading" => $guide->page_phases['data_compared'] ?? "Data Compared",
                    ),
                    'users_reviews' => array(
                        "count" => number_format($sum, 0, '', ' '),
                        "heading" => $guide->page_phases['reviews_of_users'] ?? "Reviews of Users",
                    ),
                ];
                $guide['counts'] = $data;
                return successHandler(
                    new GuideByPermalinkResource($guide),
                    ResponseCode::OK_CODE,
                    ResponseMessage::GUIDE_FETCHED_SUCCESS_MESSAGE
                );
            }
            return notFoundErrorHandler(
                ResponseMessage::GUIDE_NOT_FOUND_UID_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }

    public function topGuideCounts($category, $permalink)
    {
        try {
            $guide = Guide::where('published', true)
                ->whereHas('category', function ($query) use ($category) {
                    $query->where('title', 'LIKE', Str::title(str_replace('-', ' ', $category)));
                })
                ->where('permalink', 'LIKE', $permalink)
                ->with('category')->first();
            if ($guide) {
                $category = $guide->category;
                $product_count = Product::where('is_publish_product', true)->where('category_id', $category->id)->count();
                $brand_count = Product::where('is_publish_product', true)->where('category_id', $category->id)->distinct()->count('brand');
                $attribute_count = Attribute::where('category_id', $category->id)->count();
                $data_compared_count = ($attribute_count * $product_count);
                $data = [
                    'products' => array("count" => $product_count, "heading" => "Products", 'subheading' => "Discover If The Product Is Worth Buying"),

                    'brands' => array("count" => $brand_count, "heading" => "Brands", 'subheading' => "Find The Guide You Need"),

                    'data_compared' => array("count" => $data_compared_count, "heading" => "Data Compared", 'subheading' => "Favorite Source of Information"),

                    'users_reviews' => array("count" => 0, "heading" => "Reviews of Users", 'subheading' => "Millions of User Reviews Analyzed"),
                ];
                return successHandler(
                    $data,
                    ResponseCode::OK_CODE,
                    ResponseMessage::GUIDE_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return notFoundErrorHandler(
                    ResponseMessage::GUIDE_NOT_FOUND_UID_MESSAGE
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }

    public function archivePage($title)
    {
        try {
            $primaryCategory = PrimaryArchiveCategory::where('title', 'LIKE', Str::title(str_replace('-', ' ', $title)))
                ->with([
                    'secondaryArchiveCat',
                    'secondaryArchiveCat.guides'
                ])
                ->first();
            if ($primaryCategory) {
                return successHandler(
                    new GuideByPrimaryCategoryResource($primaryCategory),
                    ResponseCode::OK_CODE,
                    ResponseMessage::GUIDE_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return successHandler(
                    $title . ' Primary archive Category not in DB ',
                    ResponseCode::OK_CODE,
                    ResponseMessage::GUIDE_FETCHED_SUCCESS_MESSAGE
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
    public function metaData($category, $permalink)
    {
        try {

            $guide = Guide::where('published', true)
                ->whereHas('category', function ($query) use ($category) {
                    $query->where('title', 'LIKE', Str::title(str_replace('-', ' ', $category)));
                })->where('permalink', 'LIKE', $permalink)->select('title', 'meta_description', 'heading_title')->first();
            if ($guide) {
                return successHandler(
                    $guide,
                    ResponseCode::OK_CODE,
                    ResponseMessage::GUIDE_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return notFoundErrorHandler(
                    'Guide not Found by this permalink - ' . $permalink,
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
    private function productsGuide($order_value)
    {
        $guideProduct = json_decode($order_value, true);
        $includedProductids = collect($guideProduct)
            ->filter(function ($product) {
                return strcasecmp($product[5], 'included') === 0;
            })
            ->map(function ($product) {
                return $product[2];
            })
            ->values()
            ->toArray();
        $variantProductids = collect($guideProduct)
            ->filter(function ($product) {
                return strcasecmp($product[5], 'variant') === 1;
            })
            ->map(function ($product) {
                return $product[2];
            })
            ->values()
            ->toArray();
        $both = array_merge($includedProductids, $variantProductids);
        return [
            'included' => $includedProductids,
            'variant' => $variantProductids,
            'both' => $both
        ];
    }

    public function productsGuidePermalink(Request $request, $category, $permalink)
    {
        try {
            $guide = Guide::where('published', true)
                ->whereHas('category', function ($query) use ($category) {
                    $query->where('title', 'LIKE', '%' . Str::title(str_replace('-', ' ', $category)) . '%');
                })->where('permalink', 'LIKE', $permalink)->first();
            if (is_object($guide)) {
                $data = json_decode($request->input('query') ?? '[]', true);
                $attributes = $this->transformArray($data, $guide->category_id);
                $ids = $this->productsGuide($guide->order_value);
                $products = Product::where('category_id', $guide->category_id)->guideFilter($data, $attributes, $ids);
                $productNames = [];
                $request['catchy_titles'] = $guide->catchy_titles;
                $request['catchy_title_phase'] = $guide->catchy_title_phase;
                // dd(json_decode($guide->catchy_titles, true));
                return successHandler(
                    new GuideProductCollection($products->paginate(20), $productNames),
                    ResponseCode::OK_CODE,
                    ResponseMessage::GUIDE_FETCHED_SUCCESS_MESSAGE
                );
            }

            return notFoundErrorHandler(
                "The Guide not Have any Products"
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
    public function productsGuidePermalinkForTable(Request $request, $category, $permalink)
    {
        try {
            $guide = Guide::where('published', true)
                ->whereHas('category', function ($query) use ($category) {
                    $query->where('title', 'LIKE', Str::title(str_replace('-', ' ', $category)));
                })->where('permalink', 'LIKE', $permalink)->first();
            if (is_object($guide)) {
                $ids = array_slice($this->productsGuide($guide->order_value)['included'] ?? [], 0, 5);
                if (count($ids) > 0) {
                    $products = Product::where('category_id', $guide->category_id)
                        ->whereIn('id', $ids)
                        ->orderByDesc('overall_counted_score')->take(5)->get();
                    $request['ids'] = $products->pluck('id')->toArray();
                    $request['catchy_titles'] = $guide->catchy_titles;
                    $request['catchy_title_phase'] = $guide->catchy_title_phase;
                    return successHandler(
                        GuideProductTableResource::collection($products),
                        ResponseCode::OK_CODE,
                        ResponseMessage::GUIDE_FETCHED_SUCCESS_MESSAGE
                    );
                } else {
                    return successHandler(
                        collect([]),
                        ResponseCode::OK_CODE,
                        ResponseMessage::GUIDE_FETCHED_SUCCESS_MESSAGE
                    );
                }
            }
            return notFoundErrorHandler(
                "The Guide not Have any Products"
            );
        } catch (\Exception $e) {
            dd($e);
            return serverErrorHandler($e);
        }
    }
    public function transformArray($inputArray, $category_id)
    {
        $outputArray = [];
        foreach ($inputArray as $key => $value) {
            if ($key == "price") {
                continue;
            }
            if ($key == "available") {
                continue;
            }
            if ($key == "brand") {
                continue;
            }
            if ($key == "sort") {
                continue;
            }
            $attribute = Attribute::where('attribute_name', 'like', $key)->where('category_id', $category_id)->select('id', 'attribute_name')->first();
            $outputArray[] = [
                'attribute_id' => $attribute?->id ?? $key,
                'value' => $value,
            ];
        }
        $outputArray = array_filter($outputArray, function ($value) {
            return $value['attribute_id'] !== null;
        });
        return $outputArray;
    }

    public function attributeData($category_id, $product_id)
    {
        try {
            $product = Product::where('id', $product_id)->where('category_id', $category_id)->first();
            if ($product) {
                return successHandler(
                    [
                        'attributes' => new \App\Http\Resources\Guide\GuideProductAttributeCollection($product->productAttribute),
                        'guide_ratings' => (new Product())->guideRating($product)
                    ],
                    ResponseCode::OK_CODE,
                    ResponseMessage::GUIDE_FETCHED_SUCCESS_MESSAGE
                );
            }
            return notFoundErrorHandler(
                ResponseMessage::GUIDE_NOT_FOUND_UID_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
