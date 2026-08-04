<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['role', 'full_name', 'email', 'phone', 'password', 'status', 'photo_url'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ledDepartment()
    {
        return $this->hasOne(Department::class, 'hod_id');
    }

    public function teachingCourses()
    {
        return $this->belongsToMany(Course::class, 'course_tutors', 'tutor_id', 'course_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'enrollments', 'student_id', 'course_id')
            ->withPivot(['status', 'enrolled_at'])
            ->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class, 'student_id');
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class, 'student_id');
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'applicant_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'student_id');
    }

    public function debts()
    {
        return $this->hasMany(Debt::class, 'student_id');
    }

    public function results()
    {
        return $this->hasMany(Result::class, 'student_id');
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function discussionPosts()
    {
        return $this->hasMany(DiscussionPost::class, 'user_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function profileData()
    {
        return $this->hasMany(MUserInfoData::class, 'user_id');
    }

    public function emailConfirmations()
    {
        return $this->hasMany(MEmailConfirmation::class, 'user_id');
    }

    /**
     * Get a custom profile field value by shortname.
     */
    public function getProfileField(string $shortname): ?string
    {
        $data = $this->profileData()
            ->whereHas('field', fn($q) => $q->where('shortname', $shortname))
            ->first();
        return $data?->data;
    }

    /**
     * Set a custom profile field value by shortname.
     */
    public function setProfileField(string $shortname, ?string $value): void
    {
        $field = MUserInfoField::where('shortname', $shortname)->first();
        if (!$field) return;

        MUserInfoData::updateOrCreate(
            ['user_id' => $this->id, 'field_id' => $field->id],
            ['data' => $value]
        );
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($this->role, $roles);
    }

    public function isSuperAdmin(): bool { return $this->role === 'SUPER_ADMIN'; }
    public function isPrincipal(): bool { return $this->role === 'PRINCIPAL'; }
    public function isRegistrar(): bool { return $this->role === 'REGISTRAR'; }
    public function isAccountant(): bool { return $this->role === 'ACCOUNTANT'; }
    public function isTutor(): bool { return $this->role === 'TUTOR'; }
    public function isStudent(): bool { return $this->role === 'STUDENT'; }
    public function isApplicant(): bool { return $this->role === 'APPLICANT'; }
    public function isActive(): bool { return $this->status === 'ACTIVE'; }
}
