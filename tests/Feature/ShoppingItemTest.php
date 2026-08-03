<?php

namespace Tests\Feature;

use App\Models\ShoppingItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_item_can_be_added_and_marked_as_purchased(): void
    {
        $this->post(route('shopping-items.store'), [
            'name' => 'Leche', 'quantity' => '2 litros', 'category' => 'fresh',
        ])->assertRedirect(route('home').'#compra');

        $item = ShoppingItem::firstOrFail();
        $this->patchJson(route('shopping-items.toggle', $item))
            ->assertOk()->assertJson(['purchased' => true]);
        $this->assertNotNull($item->fresh()->purchased_at);
    }
}
