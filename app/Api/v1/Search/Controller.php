<?php

namespace App\Http\Controllers\Api\v1\Search;

use App\Constants\ResponseCode;
use App\Http\Controllers\Controller as MasterController;
use App\Http\Resources\Blog\BlogSearchResource;
use App\Http\Resources\Guide\GuideSearchResource;
use App\Http\Resources\Product\ProductSearchResource;
use App\Models\Blog;
use App\Models\Guide;
use App\Models\OtherPhrase;
use App\Models\Product;
use Illuminate\Http\Request;

use function PHPSTORM_META\map;

class Controller extends MasterController
{
    public function index(Request $request)
    {
        try {
            $string = $request->input('query');
            if (!isset($string)) {
                return successHandler(
                    [],
                    ResponseCode::OK_CODE,
                    'No Result Found'
                );
            }
            $data = collect();
            $guides = GuideSearchResource::collection(Guide::search($string)
                ->orderByRaw("FIELD(LEFT(title, 1), '{$string}') asc")
                ->get());


            $products = Product::search($string)
                ->get()
                ->sortBy('name')
                ->take(6);
            if (!$products->isEmpty()) {
                $products = ProductSearchResource::collection($products);
            } else {
                $products = Product::where('name', 'like', '%' . $string . '%')
                    ->orWhereFullText('name', $string)
                    ->get()->take(6);
                if ($products->count() > 0) {
                    $products = ProductSearchResource::collection($products->sortBy('name'));
                }
            }
            $blogs = BlogSearchResource::collection(Blog::search($string)
                ->orderByRaw("FIELD(LEFT(title, 1), '{$string}') asc")
                ->get());

            $phases = OtherPhrase::select([
                'guides_search_text',
                'products_search_text',
                'blogs_search_text',
            ])->first();
            if (count($guides) > 0) {
                $data['guides'] = $guides;
                $data['guides_text'] = $phases->guides_search_text;
            }
            if (count($products) > 0) {
                $data['products'] = $products;
                $data['products_text'] = $phases->products_search_text;
            }
            if (count($blogs) > 0) {
                $data['blogs'] = $blogs;
                $data['blogs_text'] = $phases->blogs_search_text;
            }
            if (count($data) > 0) {
                return successHandler(
                    $data,
                    ResponseCode::OK_CODE,
                    'Data Fetched Successfully'
                );
            }
            return successHandler(
                null,
                ResponseCode::OK_CODE,
                'No Result Found'
            );
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
