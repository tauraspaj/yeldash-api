<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\JWTAuth;

class AuthController extends Controller
{
    protected $auth;

    public function __construct(JWTAuth $auth)
    {
        $this->auth = $auth;
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (empty($credentials['email']) || empty($credentials['password'])) {
            return response()->json([
                'error' => 'You must fill in all fields'
            ], 401);
        }

        if (!filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'error' => 'You must enter a valid email'
            ], 401);
        }
        
        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Find app access indicator
        if (auth()->user()->hasAppAccess() != 1) {
            return response()->json([
                'error' => 'Your group does not have access to the app'
            ], 401);
         }

        return $this->respondWithToken($token);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out'], 200);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }
}
