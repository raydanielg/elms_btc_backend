<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use App\Models\Program;
use App\Models\ProgramMode;
use App\Models\Subject;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Topic;
use App\Models\Material;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Announcement;
use App\Models\Payment;
use App\Models\FeeStructure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ===== SUPER_ADMIN =====
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@butimba.ac.tz'],
            ['full_name' => 'System Administrator', 'password' => bcrypt('password'), 'role' => 'SUPER_ADMIN', 'status' => 'ACTIVE', 'email_verified_at' => now()]
        );

        // ===== Principal =====
        $principal = User::firstOrCreate(
            ['email' => 'principal@butimba.ac.tz'],
            ['full_name' => 'Dr. Rehema Mushi', 'password' => bcrypt('password'), 'role' => 'PRINCIPAL', 'status' => 'ACTIVE', 'email_verified_at' => now()]
        );

        // ===== Registrar =====
        $registrar = User::firstOrCreate(
            ['email' => 'registrar@butimba.ac.tz'],
            ['full_name' => 'Joseph Massawe', 'password' => bcrypt('password'), 'role' => 'REGISTRAR', 'status' => 'ACTIVE', 'email_verified_at' => now()]
        );

        // ===== Accountant =====
        $accountant = User::firstOrCreate(
            ['email' => 'accounts@butimba.ac.tz'],
            ['full_name' => 'Sarah Kimaro', 'password' => bcrypt('password'), 'role' => 'ACCOUNTANT', 'status' => 'ACTIVE', 'email_verified_at' => now()]
        );

        // ===== Department =====
        $dept = Department::firstOrCreate(
            ['code' => 'EDU'],
            ['name' => 'Department of Education', 'hod_id' => null]
        );

        // ===== Tutors =====
        $tutors = [
            ['full_name' => 'Dr. Amina Hassan', 'email' => 'amina@butimba.ac.tz'],
            ['full_name' => 'Prof. John Mlay', 'email' => 'jmlay@butimba.ac.tz'],
            ['full_name' => 'Dr. Grace Kimaro', 'email' => 'grace@butimba.ac.tz'],
        ];

        $tutorIds = [];
        foreach ($tutors as $t) {
            $tutorIds[] = User::firstOrCreate(
                ['email' => $t['email']],
                ['full_name' => $t['full_name'], 'password' => bcrypt('password'), 'role' => 'TUTOR', 'status' => 'ACTIVE', 'email_verified_at' => now()]
            )->id;
        }

        // Set first tutor as HOD
        $dept->update(['hod_id' => $tutorIds[0]]);

        // ===== Program =====
        $program = Program::firstOrCreate(
            ['code' => 'DSE-SCI'],
            ['name' => 'Diploma in Secondary Education (Science)', 'level' => 'DIPLOMA', 'department_id' => $dept->id, 'duration_years' => 2, 'description' => 'A two-year diploma program preparing teachers for secondary school science education.', 'is_active' => true]
        );

        // Program modes
        foreach (['ODL', 'DAY', 'BRD'] as $mode) {
            ProgramMode::firstOrCreate(
                ['program_id' => $program->id, 'mode' => $mode]
            );
        }

        // ===== Subjects =====
        $subjectsData = [
            ['code' => 'MAT', 'name' => 'Mathematics'],
            ['code' => 'BIO', 'name' => 'Biology'],
            ['code' => 'CHE', 'name' => 'Chemistry'],
            ['code' => 'PHY', 'name' => 'Physics'],
            ['code' => 'ENG', 'name' => 'English'],
        ];

        $subjectIds = [];
        foreach ($subjectsData as $s) {
            $subject = Subject::firstOrCreate(
                ['code' => $s['code']],
                ['name' => $s['name'], 'department_id' => $dept->id]
            );
            $subjectIds[$s['code']] = $subject->id;
        }

        // ===== Fee Structure =====
        $feeItems = ['Registration' => 50000, 'Tuition' => 450000, 'Bweni' => 300000, 'Examination' => 50000];
        foreach ($feeItems as $item => $amount) {
            FeeStructure::firstOrCreate(
                ['program_id' => $program->id, 'mode' => 'DAY', 'item' => $item, 'academic_year' => '2025/2026'],
                ['amount' => $amount]
            );
        }

        // ===== Students =====
        $studentsData = [
            ['full_name' => 'David Mwakyusa', 'email' => 'david@student.butimba.ac.tz'],
            ['full_name' => 'Neema Joseph', 'email' => 'neema@student.butimba.ac.tz'],
            ['full_name' => 'Peter Lyimo', 'email' => 'peter@student.butimba.ac.tz'],
            ['full_name' => 'Anna Shayo', 'email' => 'anna@student.butimba.ac.tz'],
            ['full_name' => 'Joseph Kingu', 'email' => 'joseph@student.butimba.ac.tz'],
        ];

        $studentIds = [];
        foreach ($studentsData as $s) {
            $student = User::firstOrCreate(
                ['email' => $s['email']],
                ['full_name' => $s['full_name'], 'password' => bcrypt('password'), 'role' => 'STUDENT', 'status' => 'ACTIVE', 'email_verified_at' => now()]
            );
            $studentIds[] = $student->id;

            // Create confirmed Registration payment (so they can login)
            Payment::firstOrCreate(
                ['student_id' => $student->id, 'fee_item' => 'Registration', 'status' => 'CONFIRMED'],
                ['amount' => 50000, 'control_number' => 'CN' . rand(100000, 999999), 'paid_at' => now()->subDays(rand(10, 60)), 'verified_by' => $accountant->id]
            );
        }

        // ===== Courses =====
        $coursesData = [
            ['code' => 'ASE 231', 'title' => 'Assessment & Evaluation', 'subject' => 'MAT', 'year' => 2, 'semester' => 'I', 'credits' => 8],
            ['code' => 'EDU 101', 'title' => 'Foundations of Education', 'subject' => null, 'year' => 1, 'semester' => 'I', 'credits' => 6],
            ['code' => 'MAT 110', 'title' => 'Mathematics for Teachers', 'subject' => 'MAT', 'year' => 1, 'semester' => 'I', 'credits' => 8],
            ['code' => 'ENG 120', 'title' => 'English Language Teaching Methods', 'subject' => 'ENG', 'year' => 1, 'semester' => 'II', 'credits' => 6],
            ['code' => 'BIO 210', 'title' => 'Biology Teaching Strategies', 'subject' => 'BIO', 'year' => 2, 'semester' => 'I', 'credits' => 8],
            ['code' => 'EDU 310', 'title' => 'Curriculum Development', 'subject' => null, 'year' => 2, 'semester' => 'II', 'credits' => 6],
        ];

        foreach ($coursesData as $index => $cd) {
            $course = Course::firstOrCreate(
                ['code' => $cd['code']],
                ['title' => $cd['title'], 'program_id' => $program->id, 'subject_id' => $cd['subject'] ? ($subjectIds[$cd['subject']] ?? null) : null, 'year' => $cd['year'], 'semester' => $cd['semester'], 'credits' => $cd['credits'], 'description' => "Course covering {$cd['title']} for the Diploma program.", 'is_active' => true]
            );

            // Assign tutor
            $tutorId = $tutorIds[$index % count($tutorIds)];
            $course->tutors()->syncWithoutDetaching([$tutorId => ['role' => 'LEAD']]);

            // Enroll students
            foreach ($studentIds as $sid) {
                Enrollment::firstOrCreate(
                    ['student_id' => $sid, 'course_id' => $course->id],
                    ['status' => 'ACTIVE', 'enrolled_at' => now()->subDays(rand(10, 60))]
                );
            }

            // Create topics
            for ($t = 1; $t <= 2; $t++) {
                $topic = Topic::firstOrCreate(
                    ['course_id' => $course->id, 'order_no' => $t],
                    ['title' => "Topic {$t}: " . ['Introduction', 'Core Concepts'][$t - 1], 'description' => "Key topics in {$cd['title']}."]
                );

                // Materials
                Material::firstOrCreate(
                    ['topic_id' => $topic->id, 'title' => "Lecture Notes - Topic {$t}", 'type' => 'PDF'],
                    ['file_url' => '#', 'uploaded_by' => $tutorId]
                );

                Material::firstOrCreate(
                    ['topic_id' => $topic->id, 'title' => "Video Lecture - Topic {$t}", 'type' => 'VIDEO'],
                    ['file_url' => '#', 'uploaded_by' => $tutorId]
                );
            }

            // Assignment
            $assignment = Assignment::firstOrCreate(
                ['course_id' => $course->id, 'title' => "Assignment: " . $cd['title']],
                ['instructions' => "Complete the assignment for {$cd['title']}.", 'max_marks' => 100, 'open_at' => now()->subDays(7), 'due_at' => now()->addDays(14), 'allow_late' => true]
            );

            // Some submissions
            foreach (array_slice($studentIds, 0, 3) as $sid) {
                AssignmentSubmission::firstOrCreate(
                    ['assignment_id' => $assignment->id, 'student_id' => $sid],
                    ['content' => 'My submission for this assignment.', 'submitted_at' => now()->subDays(rand(1, 5)), 'marks' => rand(60, 95), 'feedback' => 'Good work.', 'status' => 'ON_TIME']
                );
            }

            // Announcement
            Announcement::firstOrCreate(
                ['scope' => 'COURSE', 'course_id' => $course->id, 'title' => "Welcome to {$cd['title']}!"],
                ['body' => "Dear students, welcome to {$cd['title']}. Please review the course materials regularly.", 'is_pinned' => true, 'created_by' => $tutorId]
            );
        }

        // Global announcement from Principal
        Announcement::firstOrCreate(
            ['scope' => 'GLOBAL', 'course_id' => null, 'title' => 'Welcome to ELMS Butimba'],
            ['body' => 'Welcome to the Electronic Learning Management System of Butimba Teachers College. We wish you a productive academic year.', 'is_pinned' => true, 'created_by' => $principal->id]
        );

        // ===== Certificate Templates =====
        $this->call(CertificateTemplateSeeder::class);
    }
}

