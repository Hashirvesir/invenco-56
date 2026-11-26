<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Provider;
use App\Models\Setting;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiBillScannerService
{
    /**
     * Resolve the active AI API key.
     */
    public function resolveApiKey(?string $customKey = null, string $provider = 'gemini'): ?string
    {
        if (! empty($customKey)) {
            return trim($customKey);
        }

        $setting = Setting::whereNull('deleted_at')->first();

        if ($provider === 'openai') {
            return env('OPENAI_API_KEY')
                ?: config('services.openai.key')
                ?: ($setting ? $setting->openai_api_key : null);
        }

        // Default: Gemini
        return env('GEMINI_API_KEY')
            ?: config('services.gemini.key')
            ?: ($setting ? $setting->gemini_api_key : null);
    }

    /**
     * Save an API key into settings.
     */
    public function saveApiKey(string $key, string $provider = 'gemini'): void
    {
        $setting = Setting::whereNull('deleted_at')->first();
        if ($setting) {
            if ($provider === 'openai') {
                $setting->openai_api_key = trim($key);
            } else {
                $setting->gemini_api_key = trim($key);
            }
            $setting->ai_provider = $provider;
            $setting->save();
        }
    }

    /**
     * Scan an uploaded purchase bill image or PDF file.
     *
     * @return array
     */
    public function scanBill(UploadedFile $file, ?string $customApiKey = null, ?int $warehouseId = null)
    {
        // 1. Store the uploaded bill image in public folder for preview and archiving
        $destinationPath = public_path('images/purchases_bills');
        if (! File::isDirectory($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true, true);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = 'bill_' . time() . '_' . uniqid() . '.' . $extension;
        $file->move($destinationPath, $filename);

        $savedFilePath = $destinationPath . DIRECTORY_SEPARATOR . $filename;
        $billUrl = '/images/purchases_bills/' . $filename;

        // 2. Determine mime type
        $mimeType = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'image/jpeg',
        };

        $base64Data = base64_encode(file_get_contents($savedFilePath));

        // 3. Resolve API key
        $setting = Setting::whereNull('deleted_at')->first();
        $preferredProvider = $setting && $setting->ai_provider === 'openai' ? 'openai' : 'gemini';

        $geminiKey = $this->resolveApiKey($customApiKey, 'gemini');
        $openaiKey = $this->resolveApiKey($customApiKey, 'openai');

        $rawJson = null;

        if ($geminiKey) {
            $rawJson = $this->callGemini($geminiKey, $base64Data, $mimeType);
        } elseif ($openaiKey) {
            $rawJson = $this->callOpenAI($openaiKey, $base64Data, $mimeType);
        } else {
            throw new Exception(
                'AI API Key missing. Please provide a Google Gemini API Key in Settings or in the scan window.'
            );
        }

        if (empty($rawJson)) {
            throw new Exception('AI was unable to extract information from this document. Please ensure the invoice is clear and readable.');
        }

        // 4. Match extracted data with inventory products and suppliers
        $matchedResult = $this->matchWithInventory($rawJson, $warehouseId);
        $matchedResult['bill_url'] = $billUrl;
        $matchedResult['filename'] = $filename;

        return $matchedResult;
    }

    /**
     * Call Google Gemini API (gemini-1.5-flash) with structured JSON output.
     */
    protected function callGemini(string $apiKey, string $base64Data, string $mimeType): array
    {
        $prompt = <<<PROMPT
You are an expert purchase bill / invoice analyzer for an inventory ERP system.
Read and extract all data from this purchase invoice / bill image or PDF.
Return a STRICT JSON object only, with no markdown code fence or extra text.

The JSON schema must be exactly:
{
  "supplier": {
    "name": "Supplier or Vendor or Company name on bill",
    "phone": "Phone number or null",
    "tax_number": "NTN or GST or VAT or Tax number or null",
    "address": "Supplier address or null"
  },
  "invoice": {
    "ref": "Invoice or Bill number",
    "date": "YYYY-MM-DD",
    "due_date": "YYYY-MM-DD or null"
  },
  "items": [
    {
      "name": "Product or item description",
      "code": "Item code, SKU, or barcode if visible, otherwise null",
      "quantity": 1,
      "unit_cost": 100.00,
      "discount": 0.00,
      "tax_percent": 0.00,
      "total": 100.00
    }
  ],
  "financials": {
    "tax_amount": 0.00,
    "tax_rate": 0.00,
    "discount": 0.00,
    "shipping": 0.00,
    "grand_total": 100.00,
    "notes": "Any terms or payment instructions"
  }
}

Important Rules:
1. "date" MUST be formatted as YYYY-MM-DD (e.g. 2026-11-26). If the year is 2-digit, convert to 4-digit. If date is missing, use today's date.
2. "quantity", "unit_cost", "total", "grand_total" MUST be valid numbers (not strings with currency symbols).
3. If an item total does not have tax or discount, keep discount as 0 and tax_percent as 0.
4. Extract every line item carefully without skipping.
PROMPT;

        $models = ['gemini-1.5-flash', 'gemini-2.0-flash', 'gemini-1.5-pro'];
        $lastException = null;

        foreach ($models as $model) {
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

                $response = Http::timeout(45)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($endpoint, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt],
                                    [
                                        'inlineData' => [
                                            'mimeType' => $mimeType,
                                            'data' => $base64Data,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                            'temperature' => 0.1,
                        ],
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $cleanJson = $this->cleanJsonString($text);
                    $parsed = json_decode($cleanJson, true);
                    if (is_array($parsed)) {
                        return $parsed;
                    }
                } else {
                    $err = $response->json()['error']['message'] ?? $response->body();
                    Log::warning("Gemini model {$model} failed: " . $err);
                    $lastException = new Exception("Gemini API Error: " . $err);
                }
            } catch (Exception $e) {
                Log::warning("Gemini call exception on {$model}: " . $e->getMessage());
                $lastException = $e;
            }
        }

        throw ($lastException ?: new Exception('Failed to connect to Google Gemini API.'));
    }

    /**
     * Fallback to OpenAI Vision API (gpt-4o-mini).
     */
    protected function callOpenAI(string $apiKey, string $base64Data, string $mimeType): array
    {
        $prompt = 'Extract all purchase bill details into JSON: supplier (name, phone, tax_number, address), invoice (ref, date YYYY-MM-DD, due_date), items (array of name, code, quantity, unit_cost, discount, tax_percent, total), financials (tax_amount, tax_rate, discount, shipping, grand_total, notes).';

        $imageUrl = "data:{$mimeType};base64,{$base64Data}";

        $response = Http::timeout(45)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            [
                                'type' => 'image_url',
                                'image_url' => ['url' => $imageUrl],
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            $json = $response->json();
            $content = $json['choices'][0]['message']['content'] ?? '';
            $parsed = json_decode($content, true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        throw new Exception('OpenAI Error: ' . ($response->json()['error']['message'] ?? $response->body()));
    }

    /**
     * Clean JSON string from potential markdown code fences.
     */
    protected function cleanJsonString(string $text): string
    {
        $text = trim($text);
        if (str_starts_with($text, '```json')) {
            $text = substr($text, 7);
        } elseif (str_starts_with($text, '```')) {
            $text = substr($text, 3);
        }
        if (str_ends_with($text, '```')) {
            $text = substr($text, 0, -3);
        }
        return trim($text);
    }

    /**
     * Match extracted supplier and products with the database.
     */
    public function matchWithInventory(array $data, ?int $warehouseId = null): array
    {
        // 1. Supplier Matching
        $supplierData = $data['supplier'] ?? [];
        $supplierName = trim($supplierData['name'] ?? '');
        $supplierPhone = trim($supplierData['phone'] ?? '');

        $matchedProvider = null;

        if ($supplierPhone) {
            $matchedProvider = Provider::whereNull('deleted_at')
                ->where('phone', 'LIKE', '%' . preg_replace('/[^0-9]/', '', $supplierPhone) . '%')
                ->first();
        }

        if (! $matchedProvider && $supplierName) {
            // Try exact name match first
            $matchedProvider = Provider::whereNull('deleted_at')
                ->where('name', $supplierName)
                ->first();

            // Try LIKE match if not found
            if (! $matchedProvider) {
                $matchedProvider = Provider::whereNull('deleted_at')
                    ->where('name', 'LIKE', '%' . $supplierName . '%')
                    ->first();
            }
        }

        $supplierResult = [
            'matched' => (bool) $matchedProvider,
            'id' => $matchedProvider ? $matchedProvider->id : null,
            'name' => $matchedProvider ? $matchedProvider->name : ($supplierName ?: 'Unknown Supplier'),
            'phone' => $supplierPhone ?: ($matchedProvider ? $matchedProvider->phone : ''),
            'tax_number' => $supplierData['tax_number'] ?? ($matchedProvider ? $matchedProvider->tax_number : ''),
            'address' => $supplierData['address'] ?? ($matchedProvider ? $matchedProvider->adresse : ''),
            'is_new' => ! $matchedProvider,
        ];

        // 2. Invoice Metadata
        $invoiceData = $data['invoice'] ?? [];
        $invoiceRef = trim($invoiceData['ref'] ?? '');
        $invoiceDate = trim($invoiceData['date'] ?? date('Y-m-d'));

        // Basic date format validation
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate)) {
            $invoiceDate = date('Y-m-d');
        }

        // 3. Line Items Matching
        $rawItems = $data['items'] ?? [];
        $matchedItems = [];

        foreach ($rawItems as $index => $item) {
            $itemName = trim($item['name'] ?? '');
            $itemCode = trim($item['code'] ?? '');
            $quantity = max((float) ($item['quantity'] ?? 1), 0.01);
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            $itemDiscount = (float) ($item['discount'] ?? 0);
            $taxPercent = (float) ($item['tax_percent'] ?? 0);
            $itemTotal = (float) ($item['total'] ?? ($quantity * $unitCost));

            $matchedProduct = null;

            // Search by code / barcode
            if ($itemCode) {
                $matchedProduct = Product::whereNull('deleted_at')
                    ->where(function ($q) use ($itemCode) {
                        $q->where('code', $itemCode)
                            ->orWhere('barcode', $itemCode);
                    })->first();
            }

            // Search by name if code match didn't succeed
            if (! $matchedProduct && $itemName) {
                // Exact name
                $matchedProduct = Product::whereNull('deleted_at')
                    ->where('name', $itemName)
                    ->first();

                // Partial name
                if (! $matchedProduct) {
                    $matchedProduct = Product::whereNull('deleted_at')
                        ->where('name', 'LIKE', '%' . $itemName . '%')
                        ->first();
                }
            }

            if ($matchedProduct) {
                // Load unit
                $unitPurchase = $matchedProduct->unitPurchase;
                $unitName = $unitPurchase ? $unitPurchase->ShortName : 'pc';

                $matchedItems[] = [
                    'detail_id' => $index + 1,
                    'matched' => true,
                    'is_new' => false,
                    'product_id' => $matchedProduct->id,
                    'product_variant_id' => null,
                    'name' => $matchedProduct->name,
                    'code' => $matchedProduct->code,
                    'extracted_name' => $itemName,
                    'extracted_code' => $itemCode,
                    'quantity' => $quantity,
                    'cost' => $unitCost > 0 ? $unitCost : (float) $matchedProduct->cost,
                    'Unit_cost' => $unitCost > 0 ? $unitCost : (float) $matchedProduct->cost,
                    'Net_cost' => $unitCost > 0 ? $unitCost : (float) $matchedProduct->cost,
                    'fix_cost' => (float) $matchedProduct->cost,
                    'discount' => $itemDiscount,
                    'DiscountNet' => $itemDiscount,
                    'discount_Method' => '2',
                    'tax_percent' => $taxPercent ?: (float) ($matchedProduct->TaxNet ?? 0),
                    'taxe' => 0,
                    'tax_method' => $matchedProduct->tax_method ?? '1',
                    'unitPurchase' => $unitName,
                    'purchase_unit_id' => $matchedProduct->unit_purchase_id,
                    'stock' => 0,
                    'subtotal' => $itemTotal > 0 ? $itemTotal : ($quantity * $unitCost),
                    'is_batch_tracked' => (bool) $matchedProduct->is_batch_tracked,
                    'batches' => [],
                ];
            } else {
                // Unmatched / New product
                $matchedItems[] = [
                    'detail_id' => $index + 1,
                    'matched' => false,
                    'is_new' => true,
                    'product_id' => null,
                    'product_variant_id' => null,
                    'name' => $itemName ?: 'Unmatched Item #' . ($index + 1),
                    'code' => $itemCode ?: ('NEW_' . ($index + 1)),
                    'extracted_name' => $itemName,
                    'extracted_code' => $itemCode,
                    'quantity' => $quantity,
                    'cost' => $unitCost,
                    'Unit_cost' => $unitCost,
                    'Net_cost' => $unitCost,
                    'fix_cost' => $unitCost,
                    'discount' => $itemDiscount,
                    'DiscountNet' => $itemDiscount,
                    'discount_Method' => '2',
                    'tax_percent' => $taxPercent,
                    'taxe' => 0,
                    'tax_method' => '1',
                    'unitPurchase' => 'pc',
                    'purchase_unit_id' => null,
                    'stock' => 0,
                    'subtotal' => $itemTotal > 0 ? $itemTotal : ($quantity * $unitCost),
                    'is_batch_tracked' => false,
                    'batches' => [],
                ];
            }
        }

        // 4. Financials
        $financials = $data['financials'] ?? [];
        $grandTotal = (float) ($financials['grand_total'] ?? 0);
        $taxAmount = (float) ($financials['tax_amount'] ?? 0);
        $taxRate = (float) ($financials['tax_rate'] ?? 0);
        $discount = (float) ($financials['discount'] ?? 0);
        $shipping = (float) ($financials['shipping'] ?? 0);
        $notes = trim($financials['notes'] ?? '');

        // If items sum up but grand total wasn't provided
        $computedItemsSubtotal = array_sum(array_column($matchedItems, 'subtotal'));
        if ($grandTotal <= 0 && $computedItemsSubtotal > 0) {
            $grandTotal = $computedItemsSubtotal - $discount + $taxAmount + $shipping;
        }

        return [
            'success' => true,
            'supplier' => $supplierResult,
            'invoice' => [
                'ref' => $invoiceRef,
                'date' => $invoiceDate,
                'due_date' => $invoiceData['due_date'] ?? null,
            ],
            'items' => $matchedItems,
            'total_items_count' => count($matchedItems),
            'matched_items_count' => count(array_filter($matchedItems, fn($i) => $i['matched'])),
            'financials' => [
                'subtotal' => $computedItemsSubtotal,
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
                'discount' => $discount,
                'shipping' => $shipping,
                'grand_total' => $grandTotal,
                'notes' => $notes,
            ],
        ];
    }
}
