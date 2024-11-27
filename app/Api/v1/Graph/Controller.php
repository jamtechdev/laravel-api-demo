<?php

namespace App\Http\Controllers\Api\v1\Graph;

use App\Constants\ResponseCode;
use App\Http\Controllers\Controller as MasterController;
use App\Models\Attribute;
use App\Models\AttributeCategory;
use App\Models\AttributeCategoryPoint;
use App\Models\Category;
use App\Models\OtherPhrase;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\PublishProduct;
use App\Models\Shortcode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Controller extends MasterController
{
    public function generateGhraph(Request $request)
    {
        try {
            $request->validate([
                'graph_shortcode' => ['required'],
            ]);
            $output = Shortcode::where(
                'code',
                'like',
                $request->graph_shortcode
            )->select('output')->first();
            return successHandler(
                $output?->output,
                ResponseCode::OK_CODE,
                "Chart Data fetch succesfully."
            );
        } catch (\Throwable $th) {
            return serverErrorHandler($th);
        }
    }
    private function matchData(array $matches): array
    {
        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $match_data = explode(';', trim($match, '[]'));
                if (strpos($match_data[0], 'chart') !== false) {
                    return $match_data;
                } else {
                    return [];
                }
            }
        } else {
            return [];
        }
        return [];
    }


    public function generateChart(Request $request, $category)
    {
        try {

            $request->validate([
                'attribute' => 'nullable',
            ]);
            $total_phase = OtherPhrase::select('total')->first()?->total;
            if ($request->query('attribute')) {
                $slug = array_unique(explode('-vs-', $request->query('slug')));

                $product_ids = PublishProduct::whereIn('permalink', $slug)
                    ->with('product', function ($query) {
                        $query->select('id');
                    })
                    ->whereHas('category', function ($q) use ($category) {
                        if ($category != null) {
                            $q->where('title', "like", "%" . str_replace('-', ' ', $category) . "%");
                        }
                    })
                    ->distinct('permalink')
                    ->get()
                    ->map(fn ($item) => $item->product->id)
                    ->toArray();
                $category = Category::where('title', "like", "%" . str_replace('-', ' ', $category) . "%")->first();
                return successHandler(
                    $this->createChart($request->query('attribute'), $slug, $product_ids, $category),
                    ResponseCode::OK_CODE,
                    "Chart Data fetch succesfully."
                );
            } else if (isset($request->permalink1) && isset($request->permalink2)) {
                if ($request->permalink1 == 'average' || $request->permalink2 == 'average') {
                    $slug = collect([$request->query('permalink1'), $request->query('permalink2'), $request->query('permalink3')])
                        ->reject('average')->toArray();
                    $product_ids = PublishProduct::whereIn('permalink', $slug)
                        ->with('product', function ($query) {
                            $query->select('id');
                        })
                        ->whereHas('category', function ($q) use ($category) {
                            if ($category != null) {
                                $q->where('title', "like", "%" . str_replace('-', ' ', $category) . "%");
                            }
                        })
                        ->get()
                        ->map(fn ($item) => $item->product->id);
                    $lable = AttributeCategoryPoint::whereIn('product_id', $product_ids)
                        ->with('attributeCategory')
                        ->groupBy('attribute_category_id')
                        ->get()
                        ->map(function ($item) {
                            return ['key' =>  $item->attributeCategory->attribute_category, 'label' => $item->attributeCategory->attribute_category];
                        })->toArray();
                    $data = $product_ids->map(function ($id) use ($total_phase) {
                        $product  = Product::find($id);
                        return  [$total_phase => $product->overall_score, ...AttributeCategoryPoint::where('product_id', $id)
                            ->with('attributeCategory')
                            // ->whereHas('attributeCategory', function ($query) {
                            //     $query->where('attribute_category', '!=', 'Control & Mapping');
                            // })
                            ->get()
                            ->mapWithKeys(function ($item) {
                                $key  = $item->attributeCategory['attribute_category'];
                                return ["$key" => round($item->attribute_evaluation, 2)];
                            })
                            ->toArray()];
                    });
                    $data->push([
                        $total_phase => Product::avg('overall_score'),
                        ...AttributeCategory::join('attribute_category_points', 'attribute_categories.id', '=', 'attribute_category_points.attribute_category_id')
                            ->select('attribute_category', DB::raw('avg(attribute_category_points.attribute_evaluation) as attribute_evaluation'))
                            ->groupBy('attribute_category')
                            ->get()
                            ->mapWithKeys(function ($item) {
                                return ["$item->attribute_category" => round($item->attribute_evaluation, 2)];
                            })
                            ->toArray()
                    ]);
                    return successHandler(
                        [
                            'variables' => $lable,
                            'sets' => $data->toArray(),
                        ],
                        ResponseCode::OK_CODE,
                        "Chart Data fetch succesfully."
                    );
                } else {
                    $slug = collect([$request->query('permalink1'), $request->query('permalink2'), $request->query('permalink3')])->toArray();
                    $product_ids = PublishProduct::whereIn('permalink', $slug)
                        ->with('product', function ($query) {
                            $query->select('id');
                        })
                        ->whereHas('category', function ($q) use ($category) {
                            if ($category != null) {
                                $q->where('title', "like", "%" . str_replace('-', ' ', $category) . "%");
                            }
                        })
                        ->get()
                        ->map(fn ($item) => $item->product->id);
                    $lable = AttributeCategoryPoint::whereIn('product_id', $product_ids)
                        ->with('attributeCategory')
                        ->groupBy('attribute_category_id')
                        ->get()
                        ->map(function ($item) {
                            return ['key' =>  $item->attributeCategory->attribute_category, 'label' => $item->attributeCategory->attribute_category];
                        })->toArray();
                    $data = $product_ids->map(function ($id) use ($total_phase) {
                        $product  = Product::find($id);
                        return  [$total_phase => $product->overall_score, ...AttributeCategoryPoint::where('product_id', $id)
                            ->with('attributeCategory')
                            // ->whereHas('attributeCategory', function ($query) {
                            //     $query->where('attribute_category', '!=', 'Control & Mapping');
                            // })
                            ->get()
                            ->mapWithKeys(function ($item) {
                                return [$item->attributeCategory->attribute_category => $item->attribute_evaluation];
                            })
                            ->toArray()];
                    });
                    return successHandler(
                        [
                            'variables' => $lable,
                            'sets' => $data->toArray(),
                        ],
                        ResponseCode::OK_CODE,
                        "Chart Data fetch succesfully."
                    );
                }
            }
        } catch (\Exception $th) {
            // dd($th);
            return serverErrorHandler($th);
        }
    }
    public function createChart($attribute, $slug, $ids = [], $category)
    {
        $output_attribute = Attribute::where('attribute_name', $attribute)
            ->where('category_id', $category?->id)
            ->with('category')
            ->first();
        if ($output_attribute) {
            if ($output_attribute->type_of_chart == "pie-chart") {
                if (!searchAttribute($attribute)) {
                    $select_products_with_attribute = ProductAttribute::where('attribute_id', $output_attribute->id)
                        ->whereIn('product_id', $ids)
                        ->join('attributes', 'product_attribute.attribute_id', '=', 'attributes.id')
                        ->select('product_attribute.attribute_value', 'attributes.attribute_name', DB::raw('GROUP_CONCAT(product_attribute.product_id) as product_ids'))
                        ->whereNotIn('product_attribute.attribute_value', ['?', '-'])
                        ->groupBy('product_attribute.attribute_value')
                        ->get();
                    $values = collect(ProductAttribute::where('attribute_id', $output_attribute->id)
                        ->whereNotIn('attribute_value', ['?', '-'])
                        ->select('attribute_value')
                        ->get()
                        ->map(fn ($item) => $item['attribute_value']))
                        ->toArray();
                    if (count($values) > 0) {
                        $responce = [
                            'title' => $output_attribute->attribute_name,
                            "type" => $output_attribute->type_of_chart,
                            'unit' => $output_attribute->unit ?? '',
                            ...$this->calculateUniqueValuePercentages($values,  $select_products_with_attribute, $output_attribute, $ids),
                        ];
                    } else {
                        $responce = [];
                    }
                    return $responce;
                }
            } else if ($output_attribute->type_of_chart == "vertical-chart") {
                $values =  DB::table('products')
                    ->join('publish_products', 'products.id', '=', 'publish_products.product_id')
                    ->join('product_attribute', 'product_attribute.product_id', '=', 'products.id')
                    ->groupBy('products.id')
                    ->where('product_attribute.attribute_id', $output_attribute->id)
                    ->where('products.category_id', $category->id)
                    ->whereNotIn('product_attribute.attribute_value', ['?', '-'])
                    ->select(
                        'products.id as id',
                        'products.name as name',
                        'publish_products.permalink as permalink',
                        'product_attribute.attribute_value as attribute_value',
                        DB::raw('CASE WHEN products.id IN (' . implode(',', $ids) . ') THEN true ELSE false END AS selected'),
                        DB::raw('(SELECT AVG(attribute_value) FROM product_attribute WHERE attribute_id = ' . $output_attribute->id . ') as average_value')
                    )
                    ->groupBy('products.id')
                    ->get();
                $ranges = $output_attribute->vertical_bar_chart_ranges_array;
                $ranges_frontend = $output_attribute->vertical_bar_chart_ranges_frontend_array->toArray();
                $total_count = $values->count();
                $response = [];
                foreach ($ranges as $key => $item) {
                    $value = 0;
                    $percentage = 0;
                    $permalinks = [];
                    $product_id = [];
                    $selected = 0;
                    $products = [];
                    $count = 0;
                    if (strrpos($item, '-') !== false) {
                        list($min, $max) = explode('-', $item);
                        $values_filted = $values->filter(function ($val) use ($min, $max) {
                            return (float) $val->attribute_value >= (float) $min && (float) $val->attribute_value <= (float)$max;
                        });
                        if (count($values_filted) != 0) {
                            $range_count = $values_filted->count();
                            $percentage = ($range_count / $total_count) * 100;
                            $count = $range_count;
                            $value = $values_filted->first();
                            foreach ($values_filted->pluck('permalink')->toArray() as $permalink) {
                                if (in_array($permalink, $slug)) {
                                    $permalinks[] = $permalink;
                                    $id = array_search($permalink, $slug, true);
                                    $product_id[] = $id !== false ? (int) $id + 1 : 0;
                                    $selected++;
                                    $products[] = $values_filted->where('permalink', $permalink)->first()->name;
                                }
                            }
                            if (count($slug) == 1) {
                                $average = $output_attribute->category_median;
                                if ($average >= $min && $average <= $max) {
                                    $permalinks[] = 'average';
                                    $product_id[] = 2;
                                    $selected++;
                                    $products[] = $category->average_title;
                                }
                            }
                        }
                    }

                    $response[] = [
                        'label' => $ranges_frontend[$key] ?? $item,
                        'product_count' => $count,
                        'value' => round($percentage, 1),
                        'product_url' => $permalinks,
                        'product_id' => $product_id,
                        'selected' => $selected,
                        'products' => $products
                    ];
                }
                return [
                    'title' => $output_attribute->attribute_name,
                    'type' => $output_attribute->type_of_chart,
                    'x_axis_label' => $output_attribute->attribute_name,
                    'y_axis_label' => "%",
                    'unitY' => "%",
                    'unit' => $output_attribute->unit,
                    'data' => $response ?? [],
                ];
            }
        } else {
            if (searchAttribute($attribute)) {
                $search_attribute = searchAttribute($attribute);
                $vertical_bar_chart_ranges_array = $category->vertical_bar_chart_ranges_array;
                $ranges_frontend = $category->vertical_bar_chart_ranges_frontend_array;
                if ($search_attribute == 'lowest_price') {
                    $vertical_bar_chart_ranges_array = $category->vertical_bar_chart_ranges_price_array;
                    $ranges_frontend = $category->vertical_bar_chart_ranges_frontend_price_array;
                }
                $values =
                    DB::table('products')
                    ->where('products.category_id', $category->id)
                    ->join('publish_products', 'products.id', '=', 'publish_products.product_id')
                    ->select(
                        'products.id as id',
                        'products.name as name',
                        'publish_products.permalink as permalink',
                        DB::raw("products." . $search_attribute . ' as ' . $search_attribute),
                        DB::raw('CASE WHEN products.id IN (' . implode(',', $ids) . ') THEN true ELSE false END AS selected'),
                        DB::raw('(SELECT AVG(' . $search_attribute . ') FROM products WHERE category_id = ' . $category->id . ') as average_value')
                    )
                    ->groupBy('id')->get();
                $responce = [];
                foreach ($vertical_bar_chart_ranges_array as $key1 => $item) {
                    $value = 0;
                    $percentage = 0;
                    $permalinks = [];
                    $product_id = [];
                    $selected = 0;
                    $products = [];
                    $count = 0;
                    $total_count  = $values->count();
                    if (strrpos($item, '-') !== false) {
                        list($min, $max) = explode('-', $item);
                        $values_filted =  $values->filter(fn ($item) => $item->$search_attribute >= $min && $item->$search_attribute <= $max);
                        $range_count = $values_filted->count();
                        $count = $range_count;
                        $value = $values_filted->first();
                        foreach ($values_filted->pluck('permalink')->toArray() as $key => $value) {
                            $percentage = ($range_count / $total_count) * 100;
                            if (in_array($value, $slug)) {
                                $permalinks[] = $value;
                                $id = array_search($value, $slug);
                                $product_id[] = $id !== false ? ((int)$id + 1) : 0;
                                $selected = (int) $selected + 1;
                                $products[] = $values_filted->where('permalink', $value)->first()->name;
                            }
                        }
                        if (count($slug) == 1) {
                            $avrage = 0;
                            if ($search_attribute == "users_ratings") {
                                $avrage = $category->user_rating;
                            } elseif ($search_attribute == "popularity_points") {
                                $avrage = $category->popularity;
                            } elseif ($search_attribute == "lowest_price") {
                                $avrage = $category->price;
                            } elseif ($search_attribute == "ratio_quality_price_points") {
                                $avrage = $category->ratio_quality_price;
                            } elseif ($search_attribute == "overall_score") {
                                $avrage = $category->overall_score;
                            } else {
                                $avrage = $values->avg($search_attribute);
                            }
                            if ($avrage >= $min && $avrage <= $max) {
                                // $percentage = $percentage + ($range_count / $total_count) * 100;
                                $permalinks[] = 'average';
                                $product_id[] = 2;
                                $selected = $selected + 1;
                                $products[] = $category->average_title;
                            }
                        }
                    }
                    $responce[] = [
                        'label' =>  $ranges_frontend[$key1] ?? $item,
                        'product_count' => $count,
                        'value' => round($percentage, 1),
                        'product_url' => $permalinks,
                        'product_id' => $product_id,
                        'selected' => $selected,
                        'products' => $products
                    ];
                }

                return [
                    "title" => generalAttributesNames($attribute),
                    "type" =>  "vertical-chart",
                    "x_axis_label" => generalAttributesNames($attribute),
                    "y_axis_label" => "%",
                    "unitY" => "%",
                    "unit" => $search_attribute == "lowest_price" ? currency() : '',
                    "data" => $responce
                ];
            }
        }
    }
    function calculateUniqueValuePercentages(array $array, $data, $attribute, $ids): array
    {
        try {
            $valueCounts = collect(array_count_values($array))->sortByDesc(fn ($count) => $count)->toArray();
            $percentages = [];
            $counts = [];
            $totalCount = count($array);
            foreach ($valueCounts as $value => $count) {
                $percentage = ($count / $totalCount) * 100;
                $percentages[$value] = (float) number_format($percentage, 1);
                foreach ($data as $item) {
                    $product_ids =  explode(',', $item['product_ids']);
                    if ($value == $item['attribute_value']) {
                        foreach ($ids ?? [] as $idkey => $id) {
                            $counts[$value][] = Product::select('name', 'id')->whereIn('id', $product_ids)->get()
                                ->mapWithKeys(function ($item, $key) use ($id, $idkey) {
                                    if ($idkey == 0 && $id == $item->id) {
                                        return [
                                            'product_name' => $item->name,
                                            'color' => '#437ECE'
                                        ];
                                    } elseif ($idkey == 1 && $id == $item->id) {
                                        return [
                                            'product_name' => $item->name,
                                            'color' => '#FF9933'
                                        ];
                                    } elseif ($idkey == 2 && $id == $item->id) {
                                        return [
                                            'product_name' => $item->name,
                                            'color' => '#00a38d'
                                        ];
                                    } else {
                                        return [];
                                    }
                                })
                                ->toArray();
                        }
                    }
                }
            }
            if (count($ids) == 1) {
                if (isset($counts[$attribute->category_median])) {
                    $counts[$attribute->category_median] = [...$counts[$attribute->category_median], [
                        'product_name' => $attribute->category->average_title,
                        'color' => '#FF9933'
                    ]];
                } else {
                    $counts[$attribute->category_median][] =
                        [
                            'product_name' => $attribute->category->average_title,
                            'color' => '#FF9933'
                        ];
                }
            }
            foreach ($percentages as $key => $item) {
                $final_data[] = [
                    'label' => $key,
                    'value' => $item,
                    'products' => $counts[$key] ?? [],
                ];
            }
            return [
                'data' => $final_data
            ];
        } catch (\Throwable $th) {
            // dd($th);
            return [
                'data' => []
            ];
        }
    }

    function hilightsVarticalChart($vertical_bar_chart_ranges_array, $ids, $attribute_id)
    {
        if (count($ids) == 1) {
            $average =  ProductAttribute::where('attribute_id', $attribute_id)
                ->avg('attribute_value');
            $selected = [];
            foreach ($vertical_bar_chart_ranges_array as $key => $value) {
                if (explode('-', $value)) {
                    list($min, $max) = explode('-', $value);
                    $selected[] =  ($average >= (int)$min && $average <= (int) $max) + (ProductAttribute::whereBetween('attribute_value', [(int) $min, (int) $max])
                        ->whereIn('product_id', $ids)
                        ->where('attribute_id', $attribute_id)
                        ->count());
                } else {
                    $selected[] =  $average == (int) $value +  ProductAttribute::where('attribute_value', $value)
                        ->whereIn('product_id', $ids)
                        ->where('attribute_id', $attribute_id)
                        ->count();
                }
            }
        } else {
            foreach ($vertical_bar_chart_ranges_array as $key => $value) {
                if (explode('-', $value)) {
                    list($min, $max) = explode('-', $value);
                    $selected[] =   (ProductAttribute::whereBetween('attribute_value', [(int) $min, (int) $max])
                        ->whereIn('product_id', $ids)
                        ->where('attribute_id', $attribute_id)
                        ->count());
                } else {
                    $selected[] =  ProductAttribute::where('attribute_value', $value)
                        ->whereIn('product_id', $ids)
                        ->where('attribute_id', $attribute_id)
                        ->count();
                }
            }
        }
        return $selected;
    }
}
