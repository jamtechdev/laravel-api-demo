<?php

namespace App\Http\Controllers\Api\v1\Blog;

use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller as MasterController;
use App\Http\Resources\Blog\BlogArchivePageResource;
use App\Http\Resources\Blog\BlogCategoryCollection;
use App\Models\Blog;
use App\Models\Category;
use App\Models\HomePageCategory;
use App\Models\PrimaryArchiveCategory;
use Illuminate\Http\Request;

class Controller extends MasterController
{
    public function archivePage(Request $request)
    {
        try {
            $ids = HomePageCategory::all()->where('primary_archive_category_id', '!=', null)->pluck('primary_archive_category_id')->toArray();
            $primaryCategory = PrimaryArchiveCategory::whereIn('id', $ids)
                ->with([
                    'blogs' => function ($query) {
                        $query->limit(10);
                    }
                ])
                ->get();
            if ($primaryCategory) {
                return successHandler(
                    BlogArchivePageResource::collection($primaryCategory),
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return successHandler(
                    null,
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_FETCHED_SUCCESS_MESSAGE
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
    public function blogByPrimaryCategory($title)
    {
        try {
            $primaryCategory = PrimaryArchiveCategory::where('title', 'LIKE', $title)
                ->first();
            $blogs = Blog::where("primary_archive_category_id", $primaryCategory->id)->paginate(16);
            if ($blogs) {
                return successHandler(
                    new \App\Http\Resources\Blog\BlogCollection($blogs),
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return successHandler(
                    null,
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_FETCHED_SUCCESS_MESSAGE
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }

    public function showAllBlog(Request $request)
    {
        try {
            $limit = $request->limit ? $request->limit : 15;
            $blogs = Blog::with('bsection')->wherePublished(true)->paginate($limit);
            if ($blogs) {
                return successHandler(
                    new \App\Http\Resources\Blog\BlogCollection($blogs),
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return successHandler(
                    null,
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_FETCHED_SUCCESS_MESSAGE
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }


    public function blogBySlug($category, $slug)
    {

        try {

            $blog = Blog::where(['permalink' => $slug, 'published' => true])
                ->whereHas('category', function ($query) use ($category) {
                    $query->where('title', 'LIKE', '%' . str_replace('-', ' ', $category) . '%');
                })
                ->where('published', true)
                ->first();
            if (!is_object($blog)) {
                $blog = Blog::where(['permalink' => $slug, 'published' => true])->first();
            }
            if (isset($blog)) {
                return successHandler(
                    new \App\Http\Resources\Blog\SingleBlogResource($blog),
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_FETCHED_SUCCESS_MESSAGE
                );
            }
            return notFoundErrorHandler(
                ResponseMessage::NOT_FOUND_UID_MESSAGE
            );
        } catch (\Exception $e) {
            // dd($e);
            return serverErrorHandler($e);
        }
    }

    public function latestBlog(Request $request)
    {
        try {
            $limit = $request->limit ? $request->limit : 8;
            $blogs = Blog::orderBy('published_at', 'DESC')->wherePublished(true)->paginate($limit);
            if ($blogs) {
                return successHandler(
                    new \App\Http\Resources\Blog\LatestBlogCollection($blogs),
                    ResponseCode::OK_CODE,
                    ResponseMessage::LATEST_BLOG_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return successHandler(
                    null,
                    ResponseCode::OK_CODE,
                    ResponseMessage::LATEST_BLOG_FETCHED_SUCCESS_MESSAGE
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }

    public function allCategoryBlog(Request $request)
    {
        try {
            $limit = $request->get('limit') ? $request->get('limit') : 8;
            $category = Category::with('blogs')->paginate($limit);
            if ($category) {
                return successHandler(
                    new BlogCategoryCollection($category),
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_CATEGORY_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return successHandler(
                    null,
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_CATEGORY_FETCHED_SUCCESS_MESSAGE
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }

    public function blogByCategory(Request $request, $uid)
    {
        try {
            $category = Category::with('blogs')->where(['uid' => $uid, 'published' => true])->first();
            if ($category) {
                return successHandler(
                    new \App\Http\Resources\Blog\BlogCategoryResource($category),
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_CATEGORY_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return successHandler(
                    null,
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_CATEGORY_FETCHED_SUCCESS_MESSAGE
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }

    public function metaData($permalink)
    {
        try {
            $blog = Blog::where('published', true)->where('permalink', 'LIKE', $permalink)->select('title', 'meta_description', 'heading_title')->first();
            if ($blog) {
                return successHandler(
                    $blog,
                    ResponseCode::OK_CODE,
                    ResponseMessage::BLOG_FETCHED_SUCCESS_MESSAGE
                );
            } else {
                return notFoundErrorHandler(
                    'Blog not Found by this permalink - ' . $permalink,
                );
            }
        } catch (\Exception $e) {
            return serverErrorHandler($e);
        }
    }
}
