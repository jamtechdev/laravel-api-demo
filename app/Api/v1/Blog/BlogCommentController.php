<?php

namespace App\Http\Controllers\Api\v1\Blog;
use Exception;
use Validator;
use App\Constants\ResponseCode;
use App\Constants\ResponseMessage;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogComment;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {

    }

    public function show($locale, $uid){

    }

    public function find(Request $request)
    {

    }

    /**
     * @OA\Post(
     *      path="/api/v1/blog-comment/create-comment",
     *      operationId="createComment",
     *      tags={"Create Blog Comment API's"},
     *      summary="Create blog comment.",
     *      description="Return Blog created message.",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/BlogCommentRequest")
     *      ),
     *      @OA\Response(
     *          response=202,
     *          description="Successful"
     *       )
     * )
     */

    public function store(Request $request){
        try{
            $validator = Validator::make($request->all(), [
                'blog_id' => 'required|exists:blogs,id',
                'name' => 'required',
                'comment' => 'required'
            ]);

            if ($validator->fails()) {
                return validationErrorHandler($validator->errors());
            }

            $data = array('uid' => getUID(),'blog_id'=> $request->get('blog_id'), 'name' => $request->get('name'), 'comment' => $request->get('comment'));
            BlogComment::create($data);
            return successHandler(
                "Blog comment created.",
                ResponseCode::CREATED_CODE,
                ResponseMessage::BLOG_COMMENT_CREATE_MESSAGE
            );

        } catch (Exception $e) {
            return serverErrorHandler($e);
        }

    }



}
