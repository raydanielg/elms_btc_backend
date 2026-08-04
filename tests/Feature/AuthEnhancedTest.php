<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MEmailConfirmation;
use App\Models\MUserInfoField;
use App\Models\MUserInfoData;
use App\Models\MUserInfoCategory;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EmailConfirmationNotification;

class AuthEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_register_creates_user_and_sends_confirmation_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'Test Student',
            'email' => 'student@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0712345678',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('email_confirmation_required', true);

        $this->assertDatabaseHas('users', [
            'email' => 'student@test.com',
            'role' => 'APPLICANT',
            'status' => 'PENDING',
        ]);

        $this->assertDatabaseHas('m_email_confirmations', [
            'user_id' => $response->json('data.user.id'),
        ]);

        Notification::assertSentTo(
            [$response->json('data.user.email')],
            EmailConfirmationNotification::class
        );
    }

    public function test_register_with_custom_profile_fields(): void
    {
        // Create a signup field
        $cat = MUserInfoCategory::create(['name' => 'Personal', 'sortorder' => 1]);
        MUserInfoField::create([
            'category_id' => $cat->id,
            'shortname' => 'region',
            'name' => 'Region',
            'datatype' => 'text',
            'signup' => true,
            'visible' => true,
        ]);

        $response = $this->postJson('/api/auth/register', [
            'full_name' => 'Test User',
            'email' => 'user@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'profile_fields' => ['region' => 'Dar es Salaam'],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('m_user_info_data', [
            'user_id' => $response->json('data.user.id'),
            'data' => 'Dar es Salaam',
        ]);
    }

    public function test_confirm_email_activates_account(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'status' => 'PENDING',
        ]);

        $confirmation = MEmailConfirmation::create([
            'user_id' => $user->id,
            'token' => 'test-token-123',
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->getJson('/api/auth/email/confirm/test-token-123');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Email confirmed successfully. Your account is now active.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'ACTIVE',
        ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('m_email_confirmations', ['id' => $confirmation->id]);
    }

    public function test_confirm_email_with_expired_token(): void
    {
        $user = User::factory()->create(['status' => 'PENDING']);

        MEmailConfirmation::create([
            'user_id' => $user->id,
            'token' => 'expired-token',
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/auth/email/confirm/expired-token');

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'TOKEN_EXPIRED');

        $this->assertDatabaseMissing('m_email_confirmations', ['token' => 'expired-token']);
    }

    public function test_confirm_email_with_invalid_token(): void
    {
        $response = $this->getJson('/api/auth/email/confirm/nonexistent-token');

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_TOKEN');
    }

    public function test_resend_confirmation(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'status' => 'PENDING',
        ]);

        $response = $this->postJson('/api/auth/email/resend', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('m_email_confirmations', ['user_id' => $user->id]);
        Notification::assertSentTo([$user], EmailConfirmationNotification::class);
    }

    public function test_resend_confirmation_already_verified(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/auth/email/resend', [
            'email' => $user->email,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'ALREADY_VERIFIED');
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@test.com',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
            'role' => 'TUTOR',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'access_token']]);
    }

    public function test_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'bad@test.com',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'bad@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_login_suspended_account(): void
    {
        User::factory()->create([
            'email' => 'suspended@test.com',
            'password' => bcrypt('password123'),
            'status' => 'SUSPENDED',
            'role' => 'TUTOR',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
    }

    public function test_login_student_without_payment_returns_403(): void
    {
        User::factory()->create([
            'email' => 'student@test.com',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
            'role' => 'STUDENT',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'student@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'PAYMENT_REQUIRED');
    }

    public function test_login_student_with_confirmed_payment_succeeds(): void
    {
        $user = User::factory()->create([
            'email' => 'paid@test.com',
            'password' => bcrypt('password123'),
            'status' => 'ACTIVE',
            'role' => 'STUDENT',
        ]);

        Payment::create([
            'student_id' => $user->id,
            'fee_item' => 'Registration',
            'amount' => 50000,
            'status' => 'CONFIRMED',
            'control_number' => 'CTRL123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'paid@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_me_returns_profile_data(): void
    {
        $user = User::factory()->create(['status' => 'ACTIVE', 'role' => 'TUTOR']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_update_profile_with_custom_fields(): void
    {
        $cat = MUserInfoCategory::create(['name' => 'Personal', 'sortorder' => 1]);
        $field = MUserInfoField::create([
            'category_id' => $cat->id,
            'shortname' => 'region',
            'name' => 'Region',
            'datatype' => 'text',
            'visible' => true,
            'locked' => false,
        ]);

        $user = User::factory()->create(['status' => 'ACTIVE', 'role' => 'TUTOR']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/auth/profile', [
                'full_name' => 'Updated Name',
                'profile_fields' => ['region' => 'Arusha'],
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Name', $user->fresh()->full_name);
        $this->assertDatabaseHas('m_user_info_data', [
            'user_id' => $user->id,
            'field_id' => $field->id,
            'data' => 'Arusha',
        ]);
    }

    public function test_signup_fields_endpoint(): void
    {
        $cat = MUserInfoCategory::create(['name' => 'Test', 'sortorder' => 1]);
        MUserInfoField::create([
            'category_id' => $cat->id,
            'shortname' => 'test_field',
            'name' => 'Test Field',
            'datatype' => 'text',
            'signup' => true,
            'visible' => true,
        ]);
        MUserInfoField::create([
            'category_id' => $cat->id,
            'shortname' => 'hidden_field',
            'name' => 'Hidden Field',
            'datatype' => 'text',
            'signup' => false,
            'visible' => true,
        ]);

        $response = $this->getJson('/api/profile-fields/signup');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $fields = collect($response->json('data'));
        $this->assertTrue($fields->contains('shortname', 'test_field'));
        $this->assertFalse($fields->contains('shortname', 'hidden_field'));
    }
}
