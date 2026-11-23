<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFixtures;
use Tests\TestCase;

class PhoneProfileTest extends TestCase
{
    use BuildsFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtures();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['name' => 'Ravi K', 'email' => 'ravi@example.com', 'phone_country' => '+91', 'phone' => '98765 43210', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'];
    }

    public function test_registration_form_shows_required_phone_with_india_default(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('name="phone"', false)->assertSee('name="phone_country"', false)
            ->assertSee('<option value="+91" selected', false)
            ->assertSeeInOrder(['Your name', 'Email address', 'Phone number', 'Create a password', 'Confirm password']);
    }

    public function test_new_registration_requires_a_valid_phone_and_stores_e164(): void
    {
        Mail::fake();
        $before = User::count();
        $this->post('/register', $this->payload(['phone' => '']))->assertSessionHasErrors('phone');
        $this->post('/register', $this->payload(['phone' => '12345']))->assertSessionHasErrors('phone'); // not a valid Indian mobile
        $this->post('/register', $this->payload(['phone_country' => '+999']))->assertSessionHasErrors('phone_country');
        $this->post('/register', $this->payload(['phone' => '98765abcde']))->assertSessionHasErrors('phone');
        $this->assertSame($before, User::count(), 'no user is created from invalid input');

        $this->post('/register', $this->payload())->assertRedirect(route('verification.notice'));
        $this->assertSame('+919876543210', User::where('email', 'ravi@example.com')->value('phone'));
        Mail::assertSent(OtpMail::class, 1); // email OTP is still mandatory; no SMS
    }

    public function test_international_numbers_and_pasted_country_code(): void
    {
        $this->assertSame('+447911123456', PhoneNumber::normalise('+44', '07911 123456'));
        $this->assertSame('+14155552671', PhoneNumber::normalise('+1', '(415) 555-2671'));
        $this->assertSame('+919876543210', PhoneNumber::normalise('+91', '+91 98765-43210'));
        $this->assertNull(PhoneNumber::normalise('+91', '+44 7911 123456'));
        $this->assertNull(PhoneNumber::normalise('+49', '1234567890123456'));
        $this->assertSame(['+971', '501234567'], PhoneNumber::split('+971501234567'));
        $this->assertSame('+91 98765 43210', PhoneNumber::display('+919876543210'));
    }

    public function test_existing_account_without_phone_keeps_working_and_is_prompted(): void
    {
        $legacy = $this->customer(['phone' => null]);

        $this->actingAs($legacy)->get('/dashboard')->assertOk()->assertSee('add your phone number');
        $this->actingAs($legacy)->get('/dashboard/orders')->assertOk();
        $this->actingAs($legacy)->get(route('dashboard.profile'))->assertOk()->assertSee('Please add your phone number');
    }

    public function test_customer_completes_profile_without_touching_other_records(): void
    {
        $user = $this->customer(['phone' => null, 'name' => 'Old Name']);
        $other = $this->customer(['phone' => '+919000000001', 'name' => 'Someone Else']);

        $this->actingAs($user)->put(route('dashboard.profile.update'), ['name' => 'New Name', 'phone_country' => '+91', 'phone' => '9123456789', 'user_id' => $other->id, 'email' => 'hijack@example.com', 'role' => 'admin'])
            ->assertRedirect(route('dashboard.profile'))->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('+919123456789', $user->phone);
        $this->assertSame('New Name', $user->name);
        $this->assertNotSame('hijack@example.com', $user->email);
        $this->assertSame('customer', $user->role);
        $this->assertSame('+919000000001', $other->fresh()->phone);
        $this->assertSame('Someone Else', $other->fresh()->name);

        $this->actingAs($user->fresh())->get('/dashboard')->assertDontSee('add your phone number');
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.profile.updated', 'user_id' => $user->id]);
    }

    public function test_profile_rejects_invalid_phone(): void
    {
        $user = $this->customer(['phone' => '+919123456789']);
        $this->actingAs($user)->put(route('dashboard.profile.update'), ['name' => 'X', 'phone_country' => '+91', 'phone' => '0000'])->assertSessionHasErrors('phone');
        $this->assertSame('+919123456789', $user->fresh()->phone);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = $this->customer(['password' => 'OldSecret1']);

        $this->actingAs($user)->put(route('dashboard.profile.password'), ['current_password' => 'wrong', 'password' => 'NewSecret2', 'password_confirmation' => 'NewSecret2'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $this->assertTrue(Hash::check('OldSecret1', $user->fresh()->password));

        $this->actingAs($user)->put(route('dashboard.profile.password'), ['current_password' => 'OldSecret1', 'password' => 'NewSecret2', 'password_confirmation' => 'NewSecret2'])
            ->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewSecret2', $user->fresh()->password));
    }

    public function test_admin_customer_list_shows_and_searches_phone(): void
    {
        $this->customer(['name' => 'Phone Person', 'phone' => '+919555512345']);
        $this->actingAs($this->staff())->get(route('admin.resource.index', ['resource' => 'customers', 'q' => '9555512345']))
            ->assertOk()->assertSee('Phone Person')->assertSee('+919555512345');
    }
}
