<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    private function add(string $slug, string $interval = 'monthly')
    {
        return $this->post('/cart', ['plan' => $slug, 'billing_interval' => $interval]);
    }

    public function test_guest_can_add_and_see_cart_items_with_server_side_prices(): void
    {
        $this->add('hermes-cli-grow')->assertRedirect(route('cart.index'));

        $this->get('/cart')->assertOk()->assertSee('Grow')->assertSee('₹4,999');
        $this->assertSame(1, Cart::count());
        $this->assertNull(Cart::first()->user_id);
    }

    public function test_yearly_interval_uses_yearly_price(): void
    {
        $this->add('n8n-launch', 'yearly');

        $this->get('/cart')->assertSee('₹24,990')->assertSee('Billed yearly');
    }

    public function test_pending_plans_and_invalid_input_cannot_be_added(): void
    {
        $this->add('ai-complete-bundle')->assertSessionHas('error');
        $this->add('openclaw-pro')->assertSessionHas('error');
        $this->add('does-not-exist')->assertSessionHasErrors('plan');
        $this->add('hermes-cli-grow', 'weekly')->assertSessionHasErrors('billing_interval');

        $this->assertSame(0, CartItem::count());
    }

    public function test_adding_same_plan_twice_keeps_one_line(): void
    {
        $this->add('n8n-grow');
        $this->add('n8n-grow', 'yearly');

        $this->assertSame(1, CartItem::count());
        $this->assertSame('yearly', CartItem::first()->billing_interval);
    }

    public function test_remove_item(): void
    {
        $this->add('n8n-grow');
        $item = CartItem::first();

        $this->delete("/cart/{$item->id}")->assertRedirect(route('cart.index'));
        $this->assertSame(0, CartItem::count());
    }

    public function test_user_cannot_remove_another_users_item(): void
    {
        $this->add('n8n-grow');
        $item = CartItem::first();

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs(User::factory()->create())->delete("/cart/{$item->id}");

        $this->assertSame(1, CartItem::count());
    }

    public function test_cart_persists_for_logged_in_user_across_sessions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->add('hermes-workspace-launch');

        $this->flushSession();
        $this->get('/cart')->assertSee('Launch');
        $this->assertSame($user->id, Cart::first()->user_id);
    }

    public function test_guest_cart_merges_after_login(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);
        $this->add('hermes-cli-launch');

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123']);

        $cart = Cart::where('user_id', $user->id)->first();
        $this->assertNotNull($cart);
        $this->assertSame(['hermes-cli-launch'], $cart->items->map->plan->pluck('slug')->all());
        $this->assertSame(0, Cart::whereNull('user_id')->count());
    }

    public function test_merge_does_not_duplicate_existing_user_items(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);
        $userCart = Cart::create(['user_id' => $user->id]);
        $userCart->items()->create(['plan_id' => Plan::where('slug', 'n8n-grow')->first()->id, 'billing_interval' => 'yearly']);

        $this->add('n8n-grow');
        $this->add('n8n-launch');
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123']);

        $items = $userCart->fresh()->items;
        $this->assertCount(2, $items);
        $this->assertSame('yearly', $items->firstWhere('plan_id', Plan::where('slug', 'n8n-grow')->first()->id)->billing_interval);
    }

    public function test_guest_checkout_redirects_to_login_then_back_to_checkout_with_cart(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);
        $this->add('hermes-cli-grow');

        $this->get('/checkout')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret123'])->assertRedirect(route('checkout'));

        $this->get('/checkout')->assertOk()->assertSee('Grow')->assertSee('Pricing pending approval')->assertSee('Order unavailable');
    }

    public function test_registration_otp_then_returns_to_checkout_with_same_cart(): void
    {
        Mail::fake();
        $this->add('n8n-scale');
        $this->get('/checkout')->assertRedirect(route('login'));

        $this->post('/register', ['name' => 'New User', 'email' => 'new@example.com', 'phone_country' => '+91', 'phone' => '9123456789', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])
            ->assertRedirect(route('verification.notice'));

        // Unverified: still blocked from checkout.
        $this->get('/checkout')->assertRedirect(route('verification.notice'));

        $code = null;
        Mail::assertSent(OtpMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->post('/email/verify', ['code' => $code])->assertRedirect(route('checkout'));

        $this->get('/checkout')->assertOk()->assertSee('Scale');
        $this->assertSame(1, Cart::where('user_id', User::first()->id)->first()->items()->count());
    }

    public function test_checkout_with_empty_cart_redirects_to_cart(): void
    {
        $this->actingAs(User::factory()->create())->get('/checkout')->assertRedirect(route('cart.index'));
    }

    public function test_plan_made_unavailable_after_adding_blocks_checkout_link(): void
    {
        $this->add('n8n-grow');
        Plan::where('slug', 'n8n-grow')->update(['is_purchasable' => false]);

        $this->get('/cart')->assertSee('no longer available')->assertDontSee('Proceed to checkout');
    }
}
