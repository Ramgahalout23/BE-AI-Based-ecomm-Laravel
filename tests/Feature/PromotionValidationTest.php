<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionValidationTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'ADMIN']);
    }

    private function draftPromotion(array $overrides = []): Promotion
    {
        return Promotion::create(array_merge([
            'title' => 'Draft Promo',
            'type' => 'FLASH_SALE',
            'discount' => 0,
            'status' => 'PAUSED',
            'is_active' => false,
        ], $overrides));
    }

    /** @test */
    public function cannot_create_an_active_promotion_with_zero_discount()
    {
        $this->actingAs($this->adminUser())
            ->postJson('/api/v1/admin/promotions', [
                'title' => 'Zero Discount Promo',
                'type' => 'FLASH_SALE',
                'discount' => 0,
                'isActive' => true,
                'status' => 'ACTIVE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount');
    }

    /** @test */
    public function cannot_create_an_active_promotion_without_a_discount()
    {
        $this->actingAs($this->adminUser())
            ->postJson('/api/v1/admin/promotions', [
                'title' => 'No Discount Promo',
                'type' => 'FLASH_SALE',
                'isActive' => true,
                'status' => 'ACTIVE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount');
    }

    /** @test */
    public function can_create_an_inactive_promotion_without_a_discount()
    {
        $this->actingAs($this->adminUser())
            ->postJson('/api/v1/admin/promotions', [
                'title' => 'Inactive Draft',
                'type' => 'FLASH_SALE',
                'isActive' => false,
                'status' => 'PAUSED',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Inactive Draft');
    }

    /** @test */
    public function can_create_an_active_promotion_with_a_real_discount()
    {
        $this->actingAs($this->adminUser())
            ->postJson('/api/v1/admin/promotions', [
                'title' => 'Real Discount Promo',
                'type' => 'FLASH_SALE',
                'discount' => 10,
                'isActive' => true,
                'status' => 'ACTIVE',
            ])
            ->assertStatus(201)
            // decimal(10,2) columns are returned as strings ("10.00")
            ->assertJsonPath('data.discount', '10.00');
    }

    /** @test */
    public function cannot_activate_an_existing_promotion_with_zero_discount_via_toggle()
    {
        $promo = $this->draftPromotion();

        $this->actingAs($this->adminUser())
            ->putJson("/api/v1/admin/promotions/{$promo->id}", ['status' => 'ACTIVE'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount');
    }

    /** @test */
    public function can_activate_an_existing_promotion_that_has_a_discount_via_toggle()
    {
        $promo = $this->draftPromotion(['discount' => 15]);

        $this->actingAs($this->adminUser())
            ->putJson("/api/v1/admin/promotions/{$promo->id}", ['status' => 'ACTIVE'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    /** @test */
    public function cannot_keep_an_existing_promotion_active_while_zeroing_its_discount()
    {
        $promo = $this->draftPromotion(['discount' => 15, 'status' => 'ACTIVE', 'is_active' => true]);

        $this->actingAs($this->adminUser())
            ->putJson("/api/v1/admin/promotions/{$promo->id}", ['discount' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount');
    }
}
