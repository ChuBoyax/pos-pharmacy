<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(Setting::allValues());
    }

    public function update(Request $request): JsonResponse
    {
        Setting::store($request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:50'],
            'store_tin' => ['nullable', 'string', 'max:50'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'senior_discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'daily_target' => ['required', 'numeric', 'min:0'],
            'expiry_warning_days' => ['required', 'integer', 'min:1', 'max:365'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
        ]));

        return response()->json(Setting::allValues());
    }
}
