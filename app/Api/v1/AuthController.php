<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Create a new user and api token/api key instance.
     *
     */
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'email' => 'required|email|unique:service_users',
                'domain' => 'required|url',
                'password' => 'nullable|min:8',
                'api_key' => 'required|unique:service_users,api_key',
                'api_token' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->validationErrorHandler($validator->errors()->first());
            }
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'domain' => $request->domain,
                'password' => $request->password,
                'api_token' => $request->api_token,
                'api_key' => $request->api_key,
            ]);
            return $this->successHandler(new UserResource($user), 201, 'User created successfully');
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }

    public function getUser(Request $request)
    {
        try {
            return $this->successHandler(new UserResource($request->user), 200, 'User found successfully');
        } catch (\Throwable $th) {
            return $this->errorHandler(500, $th->getMessage());
        }
    }

    public function unauthorized()
    {
        return $this->errorHandler(404, 'Route not found.');
    }
}
