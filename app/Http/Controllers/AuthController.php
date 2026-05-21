<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
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

        // Kirim email verifikasi
        // event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil. Silakan login dengan Bearer token.',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    // Fitur Login
    public function login(LoginRequest $request)
    {
        // $remember = $request->boolean('remember_me');

        // if ($remember) {
        //     Auth::guard('web')->setRememberDuration(config('auth.remember', 20160)); // 14 hari
        // }

        // if (Auth::attempt($request->only('email', 'password'), $remember)) {
        //     $request->session()->regenerate(); // Mencegah Session Fixation

        //     return response()->json([
        //         'success' => true,
        //         'message' => 'Login berhasil',
        //         'data' => Auth::user()
        //     ], 200);
        // }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 200);
    }

    // Fitur Logout
    public function logout(Request $request)
    {
        // Auth::guard('web')->logout();
        // 
        // $request->session()->invalidate();
        // $request->session()->regenerateToken();

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ], 200);
    }

    // Verifikasi Email
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Link verifikasi tidak valid',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email sudah diverifikasi sebelumnya',
            ]);
        }

        $user->markEmailAsVerified();

        return redirect()->away(env('FRONTEND_URL') . '/login');
    }

    // Kirim Ulang Email Verifikasi
    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email sudah diverifikasi',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Email verifikasi berhasil dikirim ulang',
        ]);
    }

    // Forgot Password: Kirim link reset ke email
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Link reset password berhasil dikirim ke email Anda',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Email tidak ditemukan',
        ], 422);
    }

    // Reset Password: Ubah password menggunakan token dari email
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*]).+$/',
            ],
        ], [
            'password.regex' => 'Password harus mengandung minimal 1 huruf kecil, 1 huruf besar, 1 angka, dan 1 simbol (!@#$%^&*).',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password berhasil direset. Silakan login dengan password baru.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Token tidak valid atau sudah kedaluwarsa',
        ], 422);
    }

    // Edit Profil (hanya nama)
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $user->update(['name' => $request->name]);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => $user,
        ]);
    }
}