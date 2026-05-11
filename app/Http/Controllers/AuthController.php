<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;

class AuthController extends Controller
{
    // Fitur Register
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer', // Default role untuk registrasi publik
        ]);

        // $token = $user->createToken('auth_token')->plainTextToken;

        // return response()->json([
        //     'success' => true,
        //     'message' => 'Registrasi berhasil',
        //     'data' => [
        //         'user' => $user,
        //         'access_token' => $token,
        //         'token_type' => 'Bearer'
        //     ]
        // ], 201);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil'
        ], 201);
    }

    // Fitur Login
    public function login(LoginRequest $request)
    {
        if (Auth::attempt($request->only('email', 'password'))) {
            $request->session()->regenerate(); // Mencegah Session Fixation

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => Auth::user()
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Email atau password salah'
        ], 401);
    }

    // Fitur Logout
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ], 200);
    }
}