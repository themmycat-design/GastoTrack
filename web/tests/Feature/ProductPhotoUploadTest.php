<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
    }

    private function staff(): User
    {
        $business = Business::create(['name' => 'Upload test shop', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    public function test_staff_can_create_and_replace_a_photo_using_mobile_multipart_requests(): void
    {
        Storage::fake('public');
        $this->staff();
        $created = $this->post('/api/v1/products', [
            'name' => 'Latte', 'category' => 'Coffee', 'price' => '100', 'is_available' => '1',
            'image' => $this->photo()->size(3072),
        ], ['Accept' => 'application/json'])->assertCreated()->json('product');
        Storage::disk('public')->assertExists($created['image']);

        $updated = $this->post('/api/v1/products/'.$created['id'], [
            '_method' => 'PUT', 'image' => $this->photo(), 'replace_ingredients' => '1',
        ], ['Accept' => 'application/json'])->assertOk()->json('product');
        Storage::disk('public')->assertExists($updated['image']);
        Storage::disk('public')->assertMissing($created['image']);
        $this->get('/api/v1/product-images/'.$created['id'])->assertOk();
    }

    public function test_storage_failure_keeps_existing_photo_and_product_details(): void
    {
        $staff = $this->staff();
        $product = Product::create([
            'business_id' => $staff->business_id, 'name' => 'Original',
            'category' => 'Coffee', 'price' => 100, 'image' => 'products/original.png',
        ]);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldNotReceive('delete');
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $this->post('/api/v1/products/'.$product->id, [
            '_method' => 'PUT', 'name' => 'Changed', 'image' => $this->photo(),
        ], ['Accept' => 'application/json'])->assertStatus(503);
        $this->assertSame('Original', $product->fresh()->name);
        $this->assertSame('products/original.png', $product->fresh()->image);
    }
}
