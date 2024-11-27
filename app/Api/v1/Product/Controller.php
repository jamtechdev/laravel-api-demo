<?php

namespace App\Http\Controllers\Api\v1\Product;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as MasterController;
use App\Http\Resources\Guide\GuideProductResource;
use App\Http\Resources\Guide\GuideProductTableResource;
use App\Http\Resources\Product\CompareProductResource;
use App\Http\Resources\Product\OfenProdcutResource;
use App\Http\Resources\Product\ProductResource;
use App\Http\Resources\product\ReviewsResourece;
use App\Models\AttributeCategory;
use App\Models\Category;
use App\Models\ComparisonPhrase;
use App\Models\Description;
use App\Models\OtherPhrase;
use App\Models\Product;
use App\Models\PublishProduct;
use Exception;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Http\Request;


class Controller extends MasterController
{
    /**
     * A function to retrieve a product by its permalink.
     *
     * @param Request $request The request object
     * @param string $permalink The permalink of the product
     * @throws Exception When an error occurs
     * @return mixed The response of the function
     */
    public function getProductByPermalink(Request $request, $category, $permalink)
    {
        try {
            $publishProduct = PublishProduct::where('permalink', 'like', $permalink)
                ->whereHas('category', function ($q) use ($category) {
                    if ($category != null) {
                        $q->where('title', "like", "%" . str_replace('-', ' ', $category) . "%");
                    }
                })
                ->first();
            if (isset($request->compare) && $request->compare != null) {
                $slug = explode('-vs-', $request->query('compare'));
                if (count($slug) > 0) {
                    $request['ids'] = PublishProduct::select('product_id')
                        ->whereIn('permalink', $slug)
                        ->pluck('product_id')->toArray();
                }
            }
            if ($publishProduct->product && $publishProduct->product->is_publish_product) {
                return successHandler(
                    new ProductResource($publishProduct->product),
                    ResponseCode::OK_CODE,
                    ResponseMessage::PRODUCT_FETCHED_MESSAGE
                );
            }
            return notFoundErrorHandler(
                "Product Not Found !"
            );
        } catch (Exception $e) {
            // dd($e);
            return serverErrorHandler($e);
        }
    }
    /**
     * A description of the entire PHP function.
     *
     * @param  $paramname description
     */
    public function getProductByCategory($category_id, Request $request)
    {
        try {
            $product = $request->product;
            if ($product == null) {
                $products = Product::where('category_id', $category_id)
                    ->take(4)
                    ->get();
            } else {
                if (str_contains($product, '-vs-')) {
                    $slug = explode('-vs-', $product);
                    $products = Product::where('category_id', $category_id)
                        ->whereHas('publishProduct', function ($query) use ($slug) {
                            $query->whereIn('permalink', $slug);
                        })
                        // ->orderBy('overall_counted_score', 'desc')
                        ->get();
                } else {
                    $desiredProduct = Product::where('category_id', $category_id)->whereHas('publishProduct', function ($query) use ($product) {
                        $query->where('permalink', 'like', $product);
                    })->first();

                    $products = Product::where('category_id', $category_id)
                        ->whereHas('publishProduct', function ($query) use ($product) {
                            $query->where('permalink',  '!=', $product);
                        })
                        ->orderBy('overall_counted_score', 'desc')
                        ->get()->take(3);
                    if ($desiredProduct) {
                        $products = $products->prepend($desiredProduct);
                    }
                }
            }
            $ids = $products->pluck('id')->toArray();
            $request['ids'] = $ids;
            $request['overall_values'] = $products->pluck('overall_score')->toArray();
            if ($products) {
                return successHandler(
                    GuideProductTableResource::collection($products),
                    ResponseCode::OK_CODE,
                    ResponseMessage::PRODUCT_FETCHED_MESSAGE
                );
            }
            return notFoundErrorHandler(
                "Product Not Found !"
            );
        } catch (Exception $e) {
            return serverErrorHandler($e);
        }
    }

    /**
     * Get product category by category ID.
     *
     * @param int $category_id The ID of the category.
     * @throws Exception Description of exception
     * @return Json
     */
    public function getProductCategory($category_id)
    {

        try {
            $productIds = Product::where('category_id', $category_id)->orderBy('overall_counted_score', 'desc')->take(4)->pluck('id')->toArray();
            $catAttributes = AttributeCategory::with(['attribute.productAttr' => function ($query) use ($productIds) {
                $query->whereIn('product_id', $productIds);
            }])
                ->whereHas('attribute.productAttr', function ($query) use ($productIds) {
                    $query->whereIn('product_id', $productIds);
                })
                ->orderBy('attribute_category_position')
                ->where("category_id", $category_id)
                ->get();
            $phase =  OtherPhrase::first();
            return successHandler(
                [...new \App\Http\Resources\Guide\SideBar\SideBarCollection($catAttributes), collect([
                    'uid' => null,
                    'name' => $phase?->general,
                    'position' => (int)0,
                    'when_matters' => '',
                    'description' => '',
                    'importance' => '',
                    'attributes' => collect([
                        [
                            "name" =>  "Overall score",
                            "phase" =>  $phase?->overall_score,
                            ...Description::where('category_id', $category_id)->select(
                                'os_desc as description',
                                'os_when_it_matters as when_matters',
                            )->first()->toArray(),
                        ],
                        [
                            "name" => "Technical score",
                            "phase" =>  $phase?->technical_score,
                            ...Description::where('category_id', $category_id)->select(
                                'ts_desc as description',
                                'ts_when_it_matters as when_matters',
                            )->first()->toArray(),
                        ],
                        [
                            "name" => "Rating",
                            "phase" =>  $phase?->users_ratings,
                            ...Description::where('category_id', $category_id)->select(
                                'ur_desc as description',
                                'ur_when_it_matters as when_matters',
                            )->first()->toArray(),
                        ],
                        [
                            "name" => "Popularity",
                            "phase" =>  $phase?->popularity,
                            ...Description::where('category_id', $category_id)->select(
                                'popularity_desc as description',
                                'popularity_when_it_matters as when_matters',
                            )->first()->toArray(),
                        ],
                        [
                            "name" => "Price",
                            "phase" =>  $phase?->price
                        ],
                        [
                            "name" => "Ratio quality-price",
                            "phase" =>  $phase?->ratio_quality_price_points,
                            ...Description::where('category_id', $category_id)->select(
                                'rqp_desc as description',
                                'rqp_when_it_matters as when_matters',
                            )->first()->toArray(),
                        ]
                    ])
                ])],
                ResponseCode::OK_CODE,
                ResponseMessage::CATEGORY_ATTRIBUTES_FETCHED_SUCCESS_MESSAGE
            );
        } catch (Exception $e) {
            return serverErrorHandler($e);
        }
    }

    /**
     * Compare products based on the given request.
     *
     * @param Request $request
     * @throws \Exception description of exception
     * @return Json
     */
    public function compareProducts(Request $request)
    {
        try {
            $string =  $request->input('query');
            if (!isset($string)) {
                return successHandler(
                    [],
                    ResponseCode::OK_CODE,
                    'No Result Found'
                );
            }

            $products = Product::search($string)
                ->get()
                ->sortByDesc('overall_counted_score')
                ->take(12);
            if (!$products->isEmpty()) {
                return successHandler(
                    CompareProductResource::collection($products),
                    ResponseCode::OK_CODE,
                    'Search Fetched Successfully'
                );
            } else {
                $products = Product::where('name', 'like', '%' . $string . '%')
                    ->orWhereFullText('name', $string)
                    ->get()->take(12);
                if ($products->count() > 0) {
                    return successHandler(
                        CompareProductResource::collection($products),
                        ResponseCode::OK_CODE,
                        'Search Fetched Successfully'
                    );
                }
            }
            return successHandler(
                null,
                ResponseCode::OK_CODE,
                'No Result Found'
            );
        } catch (Exception $e) {
            dd($e);
            return serverErrorHandler($e);
        }
    }
    /**
     * A description of the entire PHP function.
     *
     * @param Request $request description
     * @param  $paramname description
     * @throws \Exception
     * @return Json
     */
    public function compareCategoryProducts(Request $request, $category_id)
    {
        try {
            $string =  $request->input('query');
            if (!isset($string)) {
                return successHandler(
                    [],
                    ResponseCode::OK_CODE,
                    'No Result Found'
                );
            }
            $products = Product::search($string)
                ->get()
                ->filter(fn ($product) => $product->category_id == $category_id)
                ->take(12);
            if (!$products->isEmpty()) {
                return successHandler(
                    CompareProductResource::collection($products),
                    ResponseCode::OK_CODE,
                    'Search Fetched Successfully'
                );
            } else {
                $products = Product::where('category_id', $category_id)
                    ->where('name', 'like', '%' . $string . '%')
                    ->orWhereFullText('name', $string)
                    ->get()->take(12);
                if ($products->count() > 0) {
                    return successHandler(
                        CompareProductResource::collection($products),
                        ResponseCode::OK_CODE,
                        'Search Fetched Successfully'
                    );
                }
            }
            return successHandler(
                null,
                ResponseCode::OK_CODE,
                'No Result Found'
            );
        } catch (Exception $e) {
            dd($e);
            return serverErrorHandler($e);
        }
    }
    /**
     * A description of the entire PHP function.
     *
     * @param $paramname description
     * @throws Exception description of exception
     * @return Json
     */
    public function oftenProducts($category_id)
    {
        try {
            $category = Category::findOrFail($category_id);
            $relatedProducts = $category->products()
                ->orderBy('overall_counted_score', 'desc')
                // ->where('published', true)
                ->take(12)
                ->get();
            if (count($relatedProducts) > 0) {
                return successHandler(
                    OfenProdcutResource::collection($relatedProducts),
                    ResponseCode::OK_CODE,
                    'Often Product Fetched Successfully'
                );
            }
            return successHandler(
                null,
                ResponseCode::OK_CODE,
                'No Result Found'
            );
        } catch (Exception $e) {
            return serverErrorHandler($e);
        }
    }

    /**
     * A description of the entire PHP function.
     *
     * @param string $paramname description
     * @throws Exception description of exception
     * @return Json
     */
    public function metaData($permalink)
    {
        try {
            $product = PublishProduct::where('published', true)->where('permalink', 'LIKE',  $permalink)->select('title', 'meta_description', 'heading_title')->first();
            if ($product) {
                return successHandler(
                    $product,
                    ResponseCode::OK_CODE,
                    ResponseMessage::PRODUCT_FETCHED_MESSAGE
                );
            } else {
                return notFoundErrorHandler(
                    'Product not Found by this permalink - ' . $permalink,
                );
            }
        } catch (Exception $e) {
            return serverErrorHandler($e);
        }
    }
    /**
     * A description of the entire PHP function.
     *
     * @param array $item description
     * @return
     */
    public static function rejectData($item)
    {
        if (in_array($item['attribute_value'], ['?', '-'])) {
            return true;
        }
        if (strpos($item['vs'], '-') || strpos($item['vs'], '?')) {
            return true;
        }
    }
    /**
     * Compare data and return the average cons, average pros, total average cons, total average pros, and general data.
     *
     * @param mixed $compareProduct The product to compare
     * @param int $cons_limit The limit for cons
     * @param int $pros_limit The limit for pros
     * @return array The array containing average cons, average pros, total average cons, total average pros, and general data
     */
    public static function compareData($compareProduct, $cons_limit, $pros_limit)
    {
        // dd($compareProduct['general']);
        $general = collect([
            'pros' => collect(filterArrayByPP($compareProduct['general']['pros'] ?? [], $pros_limit))
                ->sortByDesc('pp')
                ->reject(function ($item) {
                    return self::rejectData($item);
                })
                ->map(function ($item) {
                    if (is_numeric($item['difference_value'])) {
                        $item['difference_value'] = round((float)$item['difference_value'], 2);
                    }
                    $item['pp'] = round((float)$item['pp'], 2);
                    $item['vs'] = str_replace('-', '', $item['vs']);
                    return $item;
                })
                ->take(8)
                ->values()
                ->toArray(),
            'cons' => collect(filterArrayByPP($compareProduct['general']['cons'] ?? [], $cons_limit))
                ->sortByDesc('pp')
                ->reject(function ($item) {
                    return self::rejectData($item);
                })
                ->map(function ($item) {
                    if (is_numeric($item['difference_value'])) {
                        $item['difference_value'] = round((float)$item['difference_value'], 2);
                    }
                    $item['pp'] = round((float)$item['pp'], 2);
                    $item['vs'] = str_replace('-', '', $item['vs']);
                    return $item;
                })
                ->take(8)
                ->values()
                ->toArray(),
        ]);
        $total_average_cons = collect(filterArrayByPP($compareProduct['cons_api'] ?? [], $cons_limit ?? 0))
            ->sortByDesc('pp')
            ->reject(function ($item) {
                return self::rejectData($item);
            })
            ->map(function ($item) {
                if (is_numeric($item['difference_value'])) {
                    $item['difference_value'] = round((float)$item['difference_value'], 2);
                }
                $item['pp'] = round((float)$item['pp'], 2);
                $item['vs'] = str_replace('-', '', $item['vs']);
                return $item;
            })
            ->take(8)
            ->values()
            ->toArray();
        $total_average_pros = collect(filterArrayByPP($compareProduct['pros_api'] ?? [], $pros_limit ?? 0))
            ->sortByDesc('pp')
            ->reject(function ($item) {
                return self::rejectData($item);
            })
            ->map(function ($item) {
                if (is_numeric($item['difference_value'])) {
                    $item['difference_value'] = round((float)$item['difference_value'], 2);
                }
                $item['pp'] = round((float)$item['pp'], 2);
                $item['vs'] = str_replace('-', '', $item['vs']);
                return $item;
            })
            ->take(8)
            ->values()
            ->toArray();
        $average_cons = collect(filterNestedArrayByPP($compareProduct['category_cons_api'] ?? [], $cons_limit ?? 0))
            ->sortByDesc([['pp']])
            ->map(function ($item) {
                $filteredValues = collect($item)
                    ->reject(function ($value) {
                        return self::rejectData($value);
                    })
                    ->map(function ($value) {
                        if (is_numeric($value['difference_value'])) {
                            $value['difference_value'] = round((float)$value['difference_value'], 2);
                        }
                        $value['pp'] = round((float)$value['pp'], 2);
                        $value['vs'] = str_replace('-', '', $value['vs']);
                        return $value;
                    })
                    ->sortByDesc('pp')
                    ->values()
                    ->toArray();
                if (!empty($filteredValues)) {
                    return $filteredValues;
                }

                return null;
            })
            ->filter()
            ->toArray();

        $average_pros = collect(filterNestedArrayByPP($compareProduct['category_pros_api'] ?? [], $pros_limit ?? 0))
            ->sortByDesc([['pp']])
            ->map(function ($item) {
                $filteredValues = collect($item)
                    ->reject(function ($value) {
                        return self::rejectData($value);
                    })
                    ->map(function ($value) {
                        if (is_numeric($value['difference_value'])) {
                            $value['difference_value'] = round((float)$value['difference_value'], 2);
                        }
                        $value['pp'] = round((float)$value['pp'], 2);
                        $value['vs'] = str_replace('-', '', $value['vs']);
                        return $value;
                    })
                    ->sortByDesc('pp')
                    ->values()
                    ->toArray();
                if (!empty($filteredValues)) {
                    return $filteredValues;
                }
                return null;
            })
            ->filter()
            ->toArray();

        return [
            'average_cons' =>  $average_cons,
            'average_pros' => $average_pros,
            'total_average_cons' => $total_average_cons,
            'total_average_pros' => array_values($total_average_pros),
            'general' => $general
        ];
    }

    /**
     * A method to compare products based on their permalinks.
     *
     * @param Request $request The request object containing the query parameters.
     * @return mixed
     */
    public function compareProductsByPermalink(Request $request, $category)
    {
        try {
            if (count($request->query()) == 2) {
                $product1 = PublishProduct::where('permalink', 'LIKE', '%' . $request->permalink1 . '%')
                    ->with('product')
                    ->whereHas('category', function ($q) use ($category) {
                        if ($category != null) {
                            $q->where('title', "like", "%" . str_replace('-', ' ', $category) . "%");
                        }
                    })
                    ->first();
                $product2 = PublishProduct::where('permalink', 'LIKE', '%' . $request->permalink2 . '%')
                    ->with('product')
                    ->whereHas('category', function ($q) use ($category) {
                        if ($category != null) {
                            $q->where('title', "like", "%" . str_replace('-', ' ', $category) . "%");
                        }
                    })
                    ->first();
                if (isset($product1->product) && isset($product2->product)) {
                    $compareProduct =  (new Product())->compareProduct($product1->product, $product2->product);

                    $pharse = collect(ComparisonPhrase::select('two_products_better_then', 'two_products_worse_then')
                        ->first()->toArray())
                        ->map(function ($item) use ($product1, $product2) {
                            if (str_contains($item, '$$$')) {
                                $item = str_replace('$$$', $product1?->product?->name ?? '', $item);
                            }
                            if (str_contains($item, '###')) {
                                $item = str_replace('###', $product2?->product?->name ?? '', $item);
                            }
                            return $item;
                        })
                        ->toArray();
                    return successHandler(
                        [
                            ...$pharse,
                            ...self::compareData($compareProduct, $product1->category->comparing_cons_limit, $product1->category->comparing_pros_limit)
                        ],
                        ResponseCode::OK_CODE,
                        ResponseMessage::PRODUCT_FETCHED_MESSAGE
                    );
                } else {
                    return notFoundErrorHandler(
                        'Product not Found by this permalink ',
                    );
                }
            } elseif (count($request->query()) == 3) {
                $product1 = PublishProduct::where('permalink', 'LIKE', '%' . $request->permalink1 . '%')
                    ->whereHas('category', function ($q) use ($category) {
                        if ($category != null) {
                            $q->where('title', "like", "%" . str_replace('-', ' ', $category) . "%");
                        }
                    })
                    ->with('product')->first();
                $product2 = PublishProduct::where('permalink', 'LIKE', '%' . $request->permalink2 . '%')
                    ->whereHas('category', function ($q) use ($category) {
                        if ($category != null) {
                            $q->where('title', "like", "%" . str_replace('-', ' ', $category) . "%");
                        }
                    })
                    ->with('product')->first();
                $product3 = PublishProduct::where('permalink', 'LIKE', '%' . $request->permalink3 . '%')
                    ->whereHas('category', function ($q) use ($category) {
                        if ($category != null) {
                            $q->where('title', "like", "%" . str_replace('-', ' ', $category) . "%");
                        }
                    })
                    ->with('product')->first();
                if (isset($product1->product) && isset($product2->product) && isset($product3->product)) {
                    $compareProduct =  (new Product())->compareTwoProducts($product1->product, $product2->product, $product3->product);


                    return successHandler(
                        [
                            ...collect(ComparisonPhrase::select('three_products_worse_then', 'three_products_better_then')->first()->toArray())
                                ->map(function ($item) use ($product1, $product2, $product3) {
                                    if (str_contains($item, '$$$')) {
                                        $item = str_replace('$$$', $product1?->product?->name ?? '', $item);
                                    }
                                    if (str_contains($item, '###')) {
                                        $item = str_replace('###', $product2?->product?->name ?? '', $item);
                                    }
                                    if (str_contains($item, '@@@')) {
                                        $item = str_replace('@@@', $product3?->product?->name ?? "", $item);
                                    }
                                    return $item;
                                })
                                ->toArray(),
                            ...self::compareData($compareProduct, $product1->category->comparing_cons_limit, $product1->category->comparing_pros_limit),
                        ],
                        ResponseCode::OK_CODE,
                        ResponseMessage::PRODUCT_FETCHED_MESSAGE
                    );
                } else {
                    return notFoundErrorHandler(
                        'Product not Found by this permalink - ',
                    );
                }
            }
        } catch (Exception $e) {
            dd($e);
            return serverErrorHandler($e);
        }
    }
}
