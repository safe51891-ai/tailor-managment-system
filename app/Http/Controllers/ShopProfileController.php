<?php
namespace App\Http\Controllers;

use App\Models\ShopProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShopProfileController extends Controller
{
    public function edit()
    {
        return view('shop-profile', ['title' => 'Shop settings', 'shop' => ShopProfile::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100', 'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:500', 'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'remove_logo' => 'nullable|boolean',
            'primary_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'sidebar_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            'background_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/D'],
        ]);
        $shop = ShopProfile::current();
        $shop->id = 1;
        $shop->fill(array_intersect_key($data, array_flip(['name', 'phone', 'address', 'primary_color', 'sidebar_color', 'background_color'])));
        if ($request->boolean('remove_logo')) $shop->logo_path = null;
        if ($request->hasFile('logo')) $shop->logo_path = $request->file('logo')->store('shop-logos', 'public');
        $shop->save();
        return back()->with('message', 'Shop settings saved.')->with('alert-type', 'success');
    }

    public function logo()
    {
        $path = ShopProfile::current()->logo_path;
        abort_unless($path && strpos($path, 'shop-logos/') === 0 && strpos($path, '..') === false
            && Storage::disk('public')->exists($path), 404);
        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'no-store, private']);
    }
}
