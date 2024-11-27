<?php

namespace App\Http\Controllers\Api\v1\Comman;

use App\Constants\ResponseCode;
use App\Http\Controllers\Controller as MasterController;
use App\Models\AboutUs;
use App\Models\Blog;
use App\Models\Category;
use App\Models\ComparisonPhrase;
use App\Models\Guide;
use App\Models\HomePage;
use App\Models\OtherPhrase;
use App\Models\PrimaryArchiveCategory;
use App\Models\Product;
use App\Models\PublishProduct;
use App\Models\SinglePage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Controller extends MasterController
{
    public function check($permalink)
    {
        switch (true) {
            case PublishProduct::where('permalink', $permalink)->exists():
                return response()->json(['type' => 'Product']);
            case AboutUs::where('permalink', $permalink)->exists():
                return response()->json(['type' => 'AboutUs']);
            case Guide::where('permalink', $permalink)->where('published', true)->exists():
                return response()->json(['type' => 'Guide']);
            case Blog::where('permalink', $permalink)->where('published', true)->exists():
                return response()->json(['type' => 'Blog']);
            case PrimaryArchiveCategory::where('title', Str::title(str_replace('-', ' ', $permalink)))->exists():
                return response()->json(['type' => 'PrimaryArchiveCategory']);
            case Category::where('title', Str::title(str_replace('-', ' ', $permalink)))->exists():
                return response()->json(['type' => 'ProductCategory']);
            case $permalink == "sitemaps":
                return response()->json(['type' => 'sitemaps']);
            case SinglePage::where('permalink', $permalink)->exists():
                return response()->json(['type' => 'SinglePage']);
            case str_contains($permalink, '-vs-'):
                $permalink = explode('-vs-', $permalink);
                if (PublishProduct::whereIn('permalink', $permalink)->count() >= count($permalink)) {
                    return response()->json(['type' => 'Compare']);
                } else {
                    return response()->json(['error' => 'Permalink not found'], 404);
                }
            default:
                return response()->json(['error' => 'Permalink not found'], 404);
        }
    }
    /**
     * Retrieves meta data for the given permalink.
     *
     * @param string $permalink The permalink to retrieve meta data for
     * @return mixed The retrieved meta data or an error response
     */
    public function metaData($permalink, Request $request)
    {
        if (!isset($request->category)) {
            if (PublishProduct::where('permalink', $permalink)->exists()) {
                $product = Product::whereHas('publishProduct', function ($query) use ($permalink) {
                    $query->where('permalink', $permalink);
                })->first();
                if ($product) {
                    return successHandler(
                        [
                            "title" => $product->title,
                            "heading_title" => $product->heading_title,
                            "meta_description" => $product->meta_description
                        ],
                        ResponseCode::OK_CODE,
                        'Meta Data fetch successfuly'
                    );
                } else {
                    return response()->json(['error' => 'Meta Data not found'], 404);
                }
            }
            if (Guide::where('permalink', $permalink)->exists()) {
                $guide = Guide::where('permalink', $permalink)->metaData()->first();
                if ($guide) {
                    return successHandler(
                        $guide,
                        ResponseCode::OK_CODE,
                        'Meta Data fetch successfuly'
                    );
                } else {
                    return response()->json(['error' => 'Meta Data not found'], 404);
                }
            }
            if (Blog::where('permalink', $permalink)->exists()) {
                $blog = Blog::where('permalink', $permalink)->metaData()->first();
                if ($blog) {
                    return successHandler(
                        $blog,
                        ResponseCode::OK_CODE,
                        'Meta Data fetch successfuly'
                    );
                } else {
                    return response()->json(['error' => 'Meta Data not found'], 404);
                }
            }
            if (AboutUs::where('permalink', $permalink)->exists()) {
                $aboutUs = AboutUs::where('permalink', $permalink)->metaData()->first();
                if ($aboutUs) {
                    return successHandler(
                        $aboutUs,
                        ResponseCode::OK_CODE,
                        'Meta Data fetch successfuly'
                    );
                } else {
                    return response()->json(['error' => 'Meta Data not found'], 404);
                }
            }
        } else {
            $category = $request->category;
            if (strpos($permalink, '-vs-') !== false) {
                $permalink = explode('-vs-', $permalink);
                $products = Product::whereHas('publishProduct', function ($query) use ($permalink) {
                    $query->whereIn('permalink', $permalink);
                })
                    ->when(!is_null($category), function ($query) use ($category) {
                        $query->whereHas('category', function ($query) use ($category) {
                            $query->where('title', 'LIKE', '%' . str_replace('-', ' ', $category) . '%');
                        });
                    })
                    ->join('categories', 'categories.id', '=', 'products.category_id')
                    ->select('name', 'categories.title as category', 'category_id')->get();
                if ($products) {
                    return successHandler(
                        ComparisonPhrase::metaData($products),
                        ResponseCode::OK_CODE,
                        'Meta Data fetch successfuly'
                    );
                } else {
                    return response()->json(['error' => 'Meta Data not found'], 404);
                }
            }
            if (
                PublishProduct::where('permalink', $permalink)
                    ->when(!is_null($category), function ($query) use ($category) {
                        $query->whereHas('category', function ($query) use ($category) {
                            $query->where('title', 'LIKE', '%' . str_replace('-', ' ', $category) . '%');
                        });
                    })
                    ->exists()
            ) {
                $product = Product::whereHas('publishProduct', function ($query) use ($permalink) {
                    $query->where('permalink', $permalink);
                })
                    ->when(!is_null($category), function ($query) use ($category) {
                        $query->whereHas('category', function ($query) use ($category) {
                            $query->where('title', 'LIKE', '%' . str_replace('-', ' ', $category) . '%');
                        });
                    })
                    ->first();
                if ($product) {
                    return successHandler(
                        [
                            "title" => $product->title,
                            "heading_title" => $product->heading_title,
                            "meta_description" => $product->meta_description
                        ],
                        ResponseCode::OK_CODE,
                        'Meta Data fetch successfuly'
                    );
                } else {
                    return response()->json(['error' => 'Meta Data not found'], 404);
                }
            }
            if (
                Guide::where('permalink', $permalink)
                    ->when(!is_null($category), function ($query) use ($category) {
                        $query->whereHas('category', function ($query) use ($category) {
                            $query->where('title', 'LIKE', '%' . str_replace('-', ' ', $category) . '%');
                        });
                    })
                    ->exists()
            ) {
                $guide = Guide::where('permalink', $permalink)
                    ->when(!is_null($category), function ($query) use ($category) {
                        $query->whereHas('category', function ($query) use ($category) {
                            $query->where('title', 'LIKE', '%' . str_replace('-', ' ', $category) . '%');
                        });
                    })
                    ->metaData()->first();
                if ($guide) {
                    return successHandler(
                        $guide,
                        ResponseCode::OK_CODE,
                        'Meta Data fetch successfuly'
                    );
                } else {
                    return response()->json(['error' => 'Meta Data not found'], 404);
                }
            }
            if (
                Blog::where('permalink', $permalink)
                    ->when(!is_null($category), function ($query) use ($category) {
                        $query->whereHas('category', function ($query) use ($category) {
                            $query->where('title', 'LIKE', '%' . str_replace('-', ' ', $category) . '%');
                        });
                    })
                    ->exists()
            ) {
                $blog = Blog::where('permalink', $permalink)
                    ->when(!is_null($category), function ($query) use ($category) {
                        $query->whereHas('category', function ($query) use ($category) {
                            $query->where('title', 'LIKE', '%' . str_replace('-', ' ', $category) . '%');
                        });
                    })
                    ->metaData()->first();
                if ($blog) {
                    return successHandler(
                        $blog,
                        ResponseCode::OK_CODE,
                        'Meta Data fetch successfuly'
                    );
                } else {
                    return response()->json(['error' => 'Meta Data not found'], 404);
                }
            }
        }
        if (PrimaryArchiveCategory::where('title', Str::title(str_replace('-', ' ', $permalink)))->exists()) {
            $primaryCategory = PrimaryArchiveCategory::where('title', Str::title(str_replace('-', ' ', $permalink)))->metaData();
            if ($primaryCategory) {
                return successHandler(
                    $primaryCategory,
                    ResponseCode::OK_CODE,
                    'Meta Data fetch successfuly'
                );
            } else {
                return response()->json(['error' => 'Meta Data not found'], 404);
            }
        } elseif (Category::where('title', Str::title(str_replace('-', ' ', $permalink)))->exists()) {
            $category = Category::where('title', Str::title(str_replace('-', ' ', $permalink)))->metaData();
            if ($category) {
                return successHandler(
                    $category,
                    ResponseCode::OK_CODE,
                    'Meta Data fetch successfuly'
                );
            } else {
                return response()->json(['error' => 'Meta Data not found'], 404);
            }
        } elseif ($permalink == 'homepage') {
            $homepage = HomePage::metaData()->first();
            if ($homepage) {
                return successHandler(
                    $homepage,
                    ResponseCode::OK_CODE,
                    'Meta Data fetch successfuly'
                );
            } else {
                return response()->json(['error' => 'Meta Data not found'], 404);
            }
        } elseif (SinglePage::where('permalink', $permalink)->exists()) {
            $singlePage = SinglePage::where('permalink', $permalink)->metaData()->first();
            if ($singlePage) {
                return successHandler(
                    $singlePage,
                    ResponseCode::OK_CODE,
                    'Meta Data fetch successfuly'
                );
            } else {
                return response()->json(['error' => 'Meta Data not found'], 404);
            }
        } else {
            return response()->json(['error' => 'Meta Data not found'], 404);
        }
    }

    function sitemaps()
    {
        return response()->view('sitemap.index')->header('Content-Type', 'text/xml')->content();
    }
    public function pageNotFound()
    {
        return successHandler(
            OtherPhrase::select(
                'page_not_found_title as title',
                'page_not_found_text as text',
            )->first(),
            ResponseCode::OK_CODE,
            'Page not fetch successfuly'
        );
    }
}
