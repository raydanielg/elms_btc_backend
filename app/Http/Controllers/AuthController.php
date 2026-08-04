<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Payment;
use App\Models\MUserInfoField;
use App\Models\MUserInfoData;
use App\Models\MEmailConfirmation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EmailConfirmationNotification;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'email' => 'required|string|email|max:150|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'sometimes|string|max:20',
            'profile_fields' => 'sometimes|array',
        ]);

        $user = User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => 'APPLICANT',
            'status' => 'PENDING',
        ]);

        // Save custom profile fields submitted during registration
        if (!empty($data['profile_fields'])) {
            foreach ($data['profile_fields'] as $shortname => $value) {
                $field = MUserInfoField::where('shortname', $shortname)->where('signup', true)->first();
                if ($field) {
                    MUserInfoData::create([
                        'user_id' => $user->id,
                        'field_id' => $field->id,
                        'data' => $value,
                    ]);
                }
            }
        }

        // Generate email confirmation token
        $confirmation = MEmailConfirmation::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);

        // Send confirmation email (don't fail registration if email fails)
        try {
            Notification::route('mail', $user->email)
                ->notify(new EmailConfirmationNotification($confirmation->token));
        } catch (\Exception $e) {
            // Email sending failed — continue anyway
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'access_token' => $token,
            'email_confirmation_required' => true,
        ], 201);
    }

    public function confirmEmail(Request $request, string $token)
    {
        $confirmation = MEmailConfirmation::where('token', $token)->first();

        if (!$confirmation) {
            return $this->error('INVALID_TOKEN', 'Invalid confirmation token.', 400);
        }

        if ($confirmation->isExpired()) {
            $confirmation->delete();
            return $this->error('TOKEN_EXPIRED', 'Confirmation token has expired. Please request a new one.', 400);
        }

        $user = $confirmation->user;
        $user->update(['email_verified_at' => now()]);

        // Activate user if they were pending
        if ($user->status === 'PENDING') {
            $user->update(['status' => 'ACTIVE']);
        }

        $confirmation->delete();

        return $this->success(['message' => 'Email confirmed successfully. Your account is now active.']);
    }

    public function resendConfirmation(Request $request)
    {
        $request->validate(['email' => 'required|string|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Don't reveal whether email exists
            return $this->success(['message' => 'If the email exists, a confirmation link has been sent.']);
        }

        if ($user->email_verified_at) {
            return $this->error('ALREADY_VERIFIED', 'Email is already verified.', 400);
        }

        // Delete old confirmations
        $user->emailConfirmations()->delete();

        $confirmation = MEmailConfirmation::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addHours(24),
        ]);

        try {
            Notification::route('mail', $user->email)
                ->notify(new EmailConfirmationNotification($confirmation->token));
        } catch (\Exception $e) {
            // Email sending failed — continue anyway
        }

        return $this->success(['message' => 'Confirmation link sent.']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        // Rate limiting: 5 attempts per minute per IP+email
        $key = 'login:' . $request->ip() . ':' . $request->email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->error('RATE_LIMITED', "Too many login attempts. Try again in {$seconds} seconds.", 429);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 60);
            return $this->error('INVALID_CREDENTIALS', 'The provided credentials are incorrect.', 401);
        }

        if ($user->status === 'SUSPENDED') {
            return $this->error('ACCOUNT_SUSPENDED', 'Your account has been suspended. Contact administration.', 403);
        }

        // Payment gate for STUDENT role (spec §22 — payment-gated enrolment)
        if ($user->role === 'STUDENT') {
            $hasConfirmedPayment = Payment::where('student_id', $user->id)
                ->where('status', 'CONFIRMED')
                ->where(function ($q) {
                    $q->where('fee_item', 'Registration')
                      ->orWhere('fee_item', 'Admission');
                })
                ->exists();

            if (!$hasConfirmedPayment) {
                return $this->error(
                    'PAYMENT_REQUIRED',
                    'Your registration fee has not been confirmed. Please contact the accounts office.',
                    403
                );
            }
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($key);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user' => $user,
            'access_token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->load(['enrolledCourses', 'teachingCourses', 'profileData.field']);

        return $this->success(['user' => $user]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'sometimes|string|max:150',
            'phone' => 'sometimes|string|max:20',
            'photo_url' => 'sometimes|string|max:255',
            'profile_fields' => 'sometimes|array',
        ]);

        $user = $request->user();
        $user->update(collect($data)->except('profile_fields')->toArray());

        // Update custom profile fields
        if (!empty($data['profile_fields'])) {
            foreach ($data['profile_fields'] as $shortname => $value) {
                $field = MUserInfoField::where('shortname', $shortname)->first();
                if ($field && !$field->locked) {
                    MUserInfoData::updateOrCreate(
                        ['user_id' => $user->id, 'field_id' => $field->id],
                        ['data' => $value]
                    );
                }
            }
        }

        return $this->success(['user' => $user->fresh('profileData.field')]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => $request->password]);

        return $this->success(null);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|string|email']);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (\Exception $e) {
            return $this->success(['message' => 'If the email exists, a reset link has been sent.']);
        }

        if ($status === Password::RESET_LINK_SENT) {
            return $this->success(['message' => 'Password reset link sent to your email.']);
        }

        return $this->success(['message' => 'If the email exists, a reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(['message' => 'Password reset successfully.']);
        }

        return $this->error('RESET_FAILED', 'Unable to reset password.', 400);
    }
}

