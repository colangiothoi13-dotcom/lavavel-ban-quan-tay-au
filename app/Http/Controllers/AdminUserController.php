<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        // Guest chat identities are technical records used to keep a visitor's
        // conversation. They are managed from the chat inbox, not this list.
        $query = User::query()
            ->where('email', 'not like', 'guest-%@guest.invalid')
            ->orderByDesc('created_at');

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('role') && in_array($request->role, ['admin', 'user'], true)) {
            $query->where('role', $request->role);
        }

        $users = $query->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:male,female,other'],
            'role' => ['required', Rule::in(['user', 'admin'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'gender' => $data['gender'] ?? null,
            'role' => $data['role'],
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Đã thêm người dùng ' . $user->name . '.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['user', 'admin'])],
        ]);

        $user->update([
            'role' => $data['role'],
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Đã cập nhật vai trò người dùng.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort(405, 'Không cho phép xóa người dùng từ giao diện quản trị.');
    }
}
