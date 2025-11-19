<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    // POST /api/login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.'
            ], 401);
        }

        // Ambil user yang berhasil login
        $user = User::where('email', $request->email)->first();

        // Buat token (kunci masuk)
        // Token ini yang nanti dipakai Frontend untuk akses data
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user,
                'token' => $token // <--- Ini kuncinya
            ]
        ]);
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        // Hapus token yang sedang dipakai (agar tidak bisa dipakai lagi)
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    // GET /api/me (Cek user yang sedang login)
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }
}