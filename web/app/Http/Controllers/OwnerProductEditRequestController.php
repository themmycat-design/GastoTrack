<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductEditRequest;
use App\Models\ProductIngredient;
use App\Models\StockItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OwnerProductEditRequestController extends Controller
{
    public function approve(Request $request, ProductEditRequest $editRequest)
    {
        abort_unless((int) $editRequest->business_id === (int) $request->user()->business_id, 404);

        $oldImage = DB::transaction(function () use ($request, $editRequest): ?string {
            $pending = ProductEditRequest::query()->whereKey($editRequest->id)
                ->where('business_id', $request->user()->business_id)
                ->where('status', 'pending')->lockForUpdate()->firstOrFail();
            $product = Product::query()->where('business_id', $request->user()->business_id)
                ->lockForUpdate()->findOrFail($pending->product_id);
            if ((int) $product->revision !== $pending->expected_revision) {
                throw ValidationException::withMessages([
                    'request' => 'This product changed after the request was sent. Reject this request and ask staff to submit an updated edit.',
                ]);
            }

            $changes = $pending->changes;
            $attributes = array_intersect_key($changes, array_flip([
                'name', 'category', 'size', 'price', 'description', 'emoji', 'is_available', 'display_order',
            ]));
            $oldImage = $product->image;
            if ($pending->image_path) {
                abort_unless(Storage::disk('public')->exists($pending->image_path), 409, 'The proposed product photo is missing.');
                $attributes['image'] = $pending->image_path;
            }
            if ($attributes) {
                $product->update($attributes);
            }

            if (array_key_exists('ingredients', $changes) || !empty($changes['replace_ingredients'])) {
                $ingredients = $changes['ingredients'] ?? [];
                $stockIds = array_column($ingredients, 'stock_id');
                $available = StockItem::query()->where('business_id', $product->business_id)
                    ->whereIn('id', $stockIds)->count();
                if ($available !== count(array_unique($stockIds))) {
                    throw ValidationException::withMessages([
                        'request' => 'An ingredient in this request is no longer available. Reject it and ask staff to submit an updated edit.',
                    ]);
                }
                $product->productIngredients()->delete();
                foreach ($ingredients as $ingredient) {
                    ProductIngredient::create([
                        'product_id' => $product->id,
                        'stock_item_id' => $ingredient['stock_id'],
                        'quantity' => $ingredient['quantity'],
                    ]);
                }
                if (! $product->wasChanged()) {
                    $product->touch();
                }
            }

            $pending->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $this->resolveNotifications($pending);

            return $pending->image_path ? $oldImage : null;
        });

        if ($oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('products.index')->with('success', 'Product edit approved and applied.');
    }

    public function reject(Request $request, ProductEditRequest $editRequest)
    {
        abort_unless((int) $editRequest->business_id === (int) $request->user()->business_id, 404);

        $imagePath = DB::transaction(function () use ($request, $editRequest): ?string {
            $pending = ProductEditRequest::query()->whereKey($editRequest->id)
                ->where('business_id', $request->user()->business_id)
                ->where('status', 'pending')->lockForUpdate()->firstOrFail();
            $pending->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $this->resolveNotifications($pending);

            return $pending->image_path;
        });

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return redirect()->route('products.index')->with('success', 'Product edit rejected.');
    }

    private function resolveNotifications(ProductEditRequest $editRequest): void
    {
        Notification::query()->where('business_id', $editRequest->business_id)
            ->where('type', 'product_edit_request')
            ->where('data->request_id', $editRequest->id)
            ->get()->each(fn (Notification $notification) => $notification->update([
                'read_at' => now(),
                'data' => array_merge($notification->data ?? [], ['resolved_at' => now()->toIso8601String()]),
            ]));
    }
}
