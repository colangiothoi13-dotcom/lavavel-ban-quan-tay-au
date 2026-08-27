<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddressController
{
    public function index(Request $request): View
    {
        return view('addresses.index', [
            'addresses' => $request->user()->addresses()->latest('is_default')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:255'],
            'street_address' => ['required', 'string', 'max:500'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $address = $request->user()->addresses()->create($data);
        if ($request->boolean('is_default') || $request->user()->addresses()->count() === 1) {
            $this->setDefault($address);
        }

        return back()->with('status', 'Đã lưu địa chỉ nhận hàng.');
    }

    public function makeDefault(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $this->setDefault($address);

        return back()->with('status', 'Đã chọn địa chỉ mặc định.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user()->addresses()->latest()->first()?->update(['is_default' => true]);
        }

        return back()->with('status', 'Đã xóa địa chỉ.');
    }

    private function setDefault(Address $address): void
    {
        $address->user->addresses()->where('id', '<>', $address->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }
}
