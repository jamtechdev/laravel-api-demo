<?php

namespace App\Http\Controllers\Api\v1\Compare;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller;
use App\Http\Resources\Compare\ComparisonResource;
use App\Models\ComparisonPhrase;
use App\Models\GuidePhrase;
use App\Models\HomePagePhrase;
use App\Models\OtherPhrase;
use App\Models\Product;
use App\Models\ProductPhrase;
use App\Models\PublishComparison;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    function compareProducts(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'product_uid' => 'required',
                ]
            );
            if ($validator->fails()) {
                return validationErrorHandler($validator->errors());
            }
            $product = Product::where('uid', $request->product_uid)->with('category')->first();
            $products = Product::where('category_id', $product->category->id)->get();
            return successHandler(
                $products,
                ResponseCode::OK_CODE,
                ResponseMessage::FAVOURITE_GUIDE_FETCHED_SUCCESS_MESSAGE
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }


    function products(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'slug' => 'required',
                ]
            );
            if ($validator->fails()) {
                return validationErrorHandler($validator->errors());
            }
            $slug = explode('-vs-', $request->query('slug'));

            $products = Product::whereHas('publishProduct', function ($query) use ($slug) {
                $query->whereIn('permalink', $slug);
            })
                ->with('category')
                ->get();

            $alternativeProducts = collect();
            $relatedGuides = collect();
            foreach ($products as $product) {
                $relatedGuides = $relatedGuides->concat((new Product())->guideRating($product));
                $alternativeProducts = $alternativeProducts->concat(getFlatAlternativeProducts($product->alternative_products));
            }
            $relatedGuides = $relatedGuides
                ->uniqueStrict('permalink')
                ->values()
                ->map(function ($guide) use ($relatedGuides) {
                    $count = $relatedGuides->filter(function ($a) use ($guide) {
                        return $a['permalink'] === $guide['permalink'];
                    })->count();
                    $guide['final_number'] = $count * (11 - $guide['importance']);
                    return $guide;
                })
                ->sortByDesc('final_number')
                ->values()
                ->take(8)
                ->toArray();
            $alternativeProducts = $alternativeProducts
                ->reject(function ($item) use ($slug) {
                    return in_array($item['permalink'], $slug);
                })
                ->map(function ($item) use ($alternativeProducts) {
                    $alternativeProduct = $alternativeProducts
                        ->where('id', $item['id'])
                        ->pluck('potential')
                        ->reduce(function ($carry, $item) {
                            return $carry * $item;
                        }, 1);
                    $item['comparison_potential'] = 1 + $alternativeProduct;
                    return $item;
                })
                ->uniqueStrict('id');
            $alternativeProducts = $alternativeProducts
                ->sort(function ($a, $b) {
                    if ($a['comparison_potential'] == $b['comparison_potential']) {
                        return $b['counted_score'] <=> $a['counted_score'];
                    }

                    return $b['comparison_potential'] <=> $a['comparison_potential'];
                })
                ->values()
                ->take(10);
            if (count($slug) == 2) {
                $ids = $products->pluck('id')->toArray();

                $text = "";

                $product1 = $products->first();
                $product2 = $products->last();
                $product1_score  = round($product1?->overall_score, 1);
                $product2_score  = round($product2?->overall_score, 1);
                if ($product1_score == $product2_score) {
                    $text = ComparisonPhrase::select('comparison_phrase_draw')->first()?->comparison_phrase_draw;
                    $text = str_replace('@@@', $product1->name, $text);
                    $text = str_replace('###', $product1_score, $text);
                    $text = str_replace('^^^', $product2->name, $text);
                    $text = str_replace('&&&', $product2_score, $text);
                } else if ($product1_score > $product2_score) {
                    $text = ComparisonPhrase::select('comparison_phrase_winner')->first()?->comparison_phrase_winner;
                    $text = str_replace('@@@', $product1->name, $text);
                    $text = str_replace('###', $product1_score, $text);
                    $text = str_replace('^^^', $product2->name, $text);
                    $text = str_replace('&&&', $product2_score, $text);
                } else if ($product1_score < $product2_score) {
                    $text = ComparisonPhrase::select('comparison_phrase_winner')->first()?->comparison_phrase_winner;
                    $text = str_replace('@@@', $product2->name, $text);
                    $text = str_replace('###', $product2_score, $text);
                    $text = str_replace('^^^', $product1->name, $text);
                    $text = str_replace('&&&', $product1_score, $text);
                }
                $comparison = PublishComparison::whereIn('product_one_id', $ids)
                    ->whereIn('product_two_id', $ids)
                    ->with('author')
                    ->first();
                if ($comparison == null) {
                    $products_names = $products->pluck('name')->toArray();
                    $page_phases = $this->getPagePhasesAttribute($products_names, $products[0]->category->title);
                    return successHandler(
                        [

                            "should_buy_product_one" => [],
                            "should_buy_product_two" => [],
                            "panel_title" => null,
                            "permalink" => null,
                            "text_part" => null,
                            "author" => null,
                            "text" => $text,
                            'related_guides' => $relatedGuides,
                            "page_phases" => $page_phases,
                            'alternative_products' => $alternativeProducts
                        ],
                        ResponseCode::OK_CODE,
                        "Alternative product and Guides fetched successfully."
                    );
                } else {
                    $comparison['related_guides'] = $relatedGuides;
                    $comparison['alternative_products'] = $alternativeProducts;
                    $comparison['text'] = $text;
                    return successHandler(
                        new ComparisonResource($comparison),
                        ResponseCode::OK_CODE,
                        "Alternative product and Guides fetched successfully."
                    );
                }
            } else {
                $products_names = $products->pluck('name')->toArray();
                $page_phases = $this->getPagePhasesAttribute($products_names, $products[0]->category->title);
                return successHandler(
                    [
                        "should_buy_product_one" => [],
                        "should_buy_product_two" => [],
                        "panel_title" => null,
                        "permalink" => null,
                        "text_part" => null,
                        "author" => null,
                        'related_guides' => $relatedGuides,
                        "page_phases" => $page_phases,
                        'alternative_products' => $alternativeProducts
                    ],
                    ResponseCode::OK_CODE,
                    "Alternative product and Guides fetched successfully."
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }

    function  getPagePhasesAttribute($productNames, $category)
    {
        $page_phases = ComparisonPhrase::first();
        $page_phases = collect($page_phases->only($page_phases
            ->getfillable()))
            ->map(function ($item) use ($productNames, $category) {
                if (str_contains($item, '$$$')) {
                    $item = str_replace('$$$', $productNames[0] ?? '', $item);
                }
                if (str_contains($item, '###')) {
                    $item = str_replace('###', $productNames[1] ?? '', $item);
                }
                if (str_contains($item, '@@@')) {
                    $item = str_replace('@@@', $productNames[2] ?? "", $item);
                }
                if (str_contains($item, '%%%')) {
                    $item = str_replace('%%%', $category, $item);
                }
                return $item;
            });
        return [
            ...$page_phases->toArray(), ...OtherPhrase::random()->first()->toArray(),
            ...HomePagePhrase::compareProducts()->first()->toArray(),
            ...GuidePhrase::select([
                "price",
                "price_highest_to_lowest",
                "price_lowest_to_highest",
                "similar_guides",
                "image",
                "comparison",
                "compare_button",
            ])->first()->toArray(),
            ...ProductPhrase::select(
                'verdict_text_heading',
                'compared',
                'good_value_text',
                'importance_text',
            )->first()->toArray(),
        ];
    }

    function alternativeProduct(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'slug' => 'required',
                ]
            );
            if ($validator->fails()) {
                return validationErrorHandler($validator->errors());
            }
            $slug = explode('-vs-', $request->query('slug'));
            $products = Product::whereHas('publishProduct', function ($query) use ($slug) {
                $query->whereIn('permalink', $slug);
            })
                ->with('category')
                ->get();
            $alternativeProducts = collect();
            $relatedGuides = collect();
            foreach ($products as $product) {
                $relatedGuides = $relatedGuides->concat((new Product())->guideRating($product));
                $alternativeProducts = $alternativeProducts->concat(getFlatAlternativeProducts($product->alternative_products));
            }

            $relatedGuides = $relatedGuides
                ->uniqueStrict('permalink')
                ->values()
                ->map(function ($item) use ($relatedGuides) {
                    $count = $relatedGuides->filter(function ($a) use ($item) {
                        return $a['permalink'] === $item['permalink'];
                    })->count();
                    $item['final_number'] = $count * (11 - $item['importance']);
                    return $item;
                })
                ->sortByDesc('final_number')
                ->mapWithKeys(function ($item, $key) {
                    return [
                        $key =>  [
                            'guide_name' => $item['guide_name'],
                            'importance' => $item['importance'],
                            'final_number' => $item['final_number'],
                        ]
                    ];
                })
                ->values()
                ->take(8)
                ->toArray();
            $alternativeProducts = $alternativeProducts
                ->reject(function ($item) use ($slug) {
                    return in_array($item['permalink'], $slug);
                })
                ->map(function ($item) use ($alternativeProducts) {
                    $alternativeProduct = $alternativeProducts
                        ->where('id', $item['id'])
                        ->pluck('potential')
                        ->reduce(function ($carry, $item) {
                            return $carry * $item;
                        }, 1);
                    $item['comparison_potential'] = 1 + $alternativeProduct;
                    return $item;
                })->uniqueStrict('id');
            $alternativeProducts = $alternativeProducts
                ->mapWithKeys(function ($item, $key) {
                    return [$key =>  [
                        'name' => $item['name'],
                        'comparison_potential' => $item['comparison_potential'],
                        'id' => $item['id']
                    ]];
                })
                ->sort(function ($a, $b) {
                    if ($a['comparison_potential'] == $b['comparison_potential']) {
                        return $b['counted_score'] <=> $a['counted_score'];
                    }

                    return $b['comparison_potential'] <=> $a['comparison_potential'];
                })
                ->values()
                ->take(10);

            return successHandler(
                [
                    'related_guides' => $relatedGuides,
                    'alternative_products' => $alternativeProducts
                ],
                ResponseCode::OK_CODE,
                "Alternative product and Guides fetched successfully."
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
