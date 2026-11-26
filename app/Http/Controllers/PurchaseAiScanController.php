<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AiBillScannerService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseAiScanController extends Controller
{
    /**
     * Get current AI bill scanner configuration status.
     */
    public function getAiConfig(AiBillScannerService $service)
    {
        $setting = Setting::whereNull('deleted_at')->first();
        $provider = $setting && $setting->ai_provider ? $setting->ai_provider : 'gemini';

        $hasGemini = ! empty($service->resolveApiKey(null, 'gemini'));
        $hasOpenAI = ! empty($service->resolveApiKey(null, 'openai'));

        return response()->json([
            'configured' => $hasGemini || $hasOpenAI,
            'provider' => $provider,
            'has_gemini' => $hasGemini,
            'has_openai' => $hasOpenAI,
            'masked_key' => $hasGemini ? '••••••••' . substr($service->resolveApiKey(null, 'gemini') ?? '', -4) : '',
            'quota' => $service->getMonthlyUsage(),
        ]);
    }

    /**
     * Save AI API Key to system settings.
     */
    public function saveAiKey(Request $request, AiBillScannerService $service)
    {
        $this->validate($request, [
            'api_key' => 'required|string|min:10',
            'provider' => 'nullable|string|in:gemini,openai',
        ]);

        $provider = $request->input('provider', 'gemini');
        $service->saveApiKey($request->input('api_key'), $provider);

        return response()->json([
            'success' => true,
            'message' => 'AI API Key saved successfully.',
        ]);
    }

    /**
     * Quick-create a product discovered by the AI Bill Scanner.
     */
    public function quickCreateProduct(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', \App\Models\Product::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'code' => 'nullable|string|max:191',
            'warehouse_id' => 'nullable|integer',
        ]);

        $defaultCat = \App\Models\Category::whereNull('deleted_at')->first()?->id ?? 1;
        $defaultUnit = \App\Models\Unit::whereNull('deleted_at')->first()?->id ?? 1;

        $code = trim($request->input('code') ?? '');
        if (empty($code)) {
            $code = 'PRD' . str_pad((string) mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        }

        // Ensure unique code
        while (\App\Models\Product::where('code', $code)->whereNull('deleted_at')->exists()) {
            $code = 'PRD' . str_pad((string) mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        }

        $cost = (float) $request->input('cost', 0);
        $price = (float) $request->input('price', round($cost * 1.25, 2));

        $product = \App\Models\Product::create([
            'code' => $code,
            'name' => $request->input('name'),
            'cost' => $cost,
            'price' => $price,
            'category_id' => $defaultCat,
            'unit_id' => $defaultUnit,
            'unit_sale_id' => $defaultUnit,
            'unit_purchase_id' => $defaultUnit,
            'TaxNet' => 0,
            'tax_method' => '1',
            'Type_barcode' => 'CODE128',
            'type' => 'is_single',
            'note' => 'Auto-created via AI Bill Scanner',
        ]);

        if ($request->filled('warehouse_id')) {
            \App\Models\product_warehouse::create([
                'product_id' => $product->id,
                'warehouse_id' => (int) $request->warehouse_id,
                'qte' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'cost' => (float) $product->cost,
                'unitPurchase' => $product->unitPurchase?->ShortName ?? 'pc',
                'purchase_unit_id' => $product->unit_purchase_id,
            ],
        ]);
    }

    /**
     * Quick-create a supplier discovered by the AI Bill Scanner.
     */
    public function quickCreateSupplier(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', \App\Models\Provider::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:100',
            'adresse' => 'nullable|string|max:255',
        ]);

        $lastProvider = \App\Models\Provider::latest('id')->first();
        $code = $lastProvider ? ((int) $lastProvider->code + 1) : 1;

        $provider = \App\Models\Provider::create([
            'name' => $request->input('name'),
            'code' => $code,
            'phone' => $request->input('phone'),
            'tax_number' => $request->input('tax_number'),
            'adresse' => $request->input('adresse'),
            'email' => $request->input('email'),
            'country' => $request->input('country', 'Pakistan'),
            'city' => $request->input('city', 'Karachi'),
        ]);

        return response()->json([
            'success' => true,
            'supplier' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'phone' => $provider->phone,
            ],
        ]);
    }

    /**
     * Scan an uploaded purchase bill image or PDF.
     */
    public function scan(Request $request, AiBillScannerService $service)
    {
        $request->validate([
            'bill_file' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:10240',
            'api_key' => 'nullable|string',
            'warehouse_id' => 'nullable|integer',
            'save_key' => 'nullable|boolean',
        ]);

        $file = $request->file('bill_file');
        $customKey = $request->input('api_key');
        $warehouseId = $request->input('warehouse_id');

        if ($request->boolean('save_key') && ! empty($customKey)) {
            $service->saveApiKey($customKey, 'gemini');
        }

        try {
            $result = $service->scanBill($file, $customKey, $warehouseId);
            return response()->json($result);
        } catch (Exception $e) {
            Log::error('AI Bill Scan Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
