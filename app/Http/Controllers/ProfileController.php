<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController
{
    public function show(Request $request)
    {
        return view('profile.show', ['user' => $request->user()]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'in:Nam,Nữ,Khác'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        unset($data['avatar']);
        if ($request->hasFile('avatar')) {
            if ($request->user()->avatar) {
                Storage::disk('public')->delete($request->user()->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $request->user()->update($data);

        return back()->with('status', 'Đã cập nhật thông tin tài khoản.');
    }
}