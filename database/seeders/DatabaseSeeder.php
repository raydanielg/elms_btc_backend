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
        $superAdmin = User::create([
            'full_name' => 'System Administrator',
            'email' => 'admin@butimba.ac.tz',
            'password' => bcrypt('password'),
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        // ===== Principal =====
        $principal = User::create([
            'full_name' => 'Dr. Rehema Mushi',
            'email' => 'principal@butimba.ac.tz',
            'password' => bcrypt('password'),
            'role' => 'PRINCIPAL',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        // ===== Registrar =====
        $registrar = User::create([
            'full_name' => 'Joseph Massawe',
            'email' => 'registrar@butimba.ac.tz',
            'password' => bcrypt('password'),
            'role' => 'REGISTRAR',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        // ===== Accountant =====
        $accountant = User::create([
            'full_name' => 'Sarah Kimaro',
            'email' => 'accounts@butimba.ac.tz',
            'password' => bcrypt('password'),
            'role' => 'ACCOUNTANT',
            'status' => 'ACTIVE',
            'email_verified_at' => now(),
        ]);

        // ===== Department =====
        $dept = Department::create([
            'code' => 'EDU',
            'name' => 'Department of Education',
            'hod_id' => null,
        ]);

        // ===== Tutors =====
        $tutors = [
            ['full_name' => 'Dr. Amina Hassan', 'email' => 'amina@butimba.ac.tz'],
            ['full_name' => 'Prof. John Mlay', 'email' => 'jmlay@butimba.ac.tz'],
            ['full_name' => 'Dr. Grace Kimaro', 'email' => 'grace@butimba.ac.tz'],
        ];

        $tutorIds = [];
        foreach ($tutors as $t) {
            $tutorIds[] = User::create([
                'full_name' => $t['full_name'],
                'email' => $t['email'],
                'password' => bcrypt('password'),
                'role' => 'TUTOR',
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
            ])->id;
        }

        // Set first tutor as HOD
        $dept->update(['hod_id' => $tutorIds[0]]);

        // ===== Program =====
        $program = Program::create([
            'code' => 'DSE-SCI',
            'name' => 'Diploma in Secondary Education (Science)',
            'level' => 'DIPLOMA',
            'department_id' => $dept->id,
            'duration_years' => 2,
            'description' => 'A two-year diploma program preparing teachers for secondary school science education.',
            'is_active' => true,
        ]);

        // Program modes
        foreach (['ODL', 'DAY', 'BRD'] as $mode) {
            ProgramMode::create([
                'program_id' => $program->id,
                'mode' => $mode,
            ]);
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
            $subject = Subject::create([
                'code' => $s['code'],
                'name' => $s['name'],
                'department_id' => $dept->id,
            ]);
            $subjectIds[$s['code']] = $subject->id;
        }

        // ===== Fee Structure =====
        $feeItems = ['Registration' => 50000, 'Tuition' => 450000, 'Bweni' => 300000, 'Examination' => 50000];
        foreach ($feeItems as $item => $amount) {
            FeeStructure::create([
                'program_id' => $program->id,
                'mode' => 'DAY',
                'item' => $item,
                'amount' => $amount,
                'academic_year' => '2025/2026',
            ]);
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
            $student = User::create([
                'full_name' => $s['full_name'],
                'email' => $s['email'],
                'password' => bcrypt('password'),
                'role' => 'STUDENT',
                'status' => 'ACTIVE',
                'email_verified_at' => now(),
            ]);
            $studentIds[] = $student->id;

            // Create confirmed Registration payment (so they can login)
            Payment::create([
                'student_id' => $student->id,
                'fee_item' => 'Registration',
                'amount' => 50000,
                'control_number' => 'CN' . rand(100000, 999999),
                'status' => 'CONFIRMED',
                'paid_at' => now()->subDays(rand(10, 60)),
                'verified_by' => $accountant->id,
            ]);
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
            $course = Course::create([
                'code' => $cd['code'],
                'title' => $cd['title'],
                'program_id' => $program->id,
                'subject_id' => $cd['subject'] ? ($subjectIds[$cd['subject']] ?? null) : null,
                'year' => $cd['year'],
                'semester' => $cd['semester'],
                'credits' => $cd['credits'],
                'description' => "Course covering {$cd['title']} for the Diploma program.",
                'is_active' => true,
            ]);

            // Assign tutor
            $tutorId = $tutorIds[$index % count($tutorIds)];
            $course->tutors()->attach($tutorId, ['role' => 'LEAD']);

            // Enroll students
            foreach ($studentIds as $sid) {
                Enrollment::create([
                    'student_id' => $sid,
                    'course_id' => $course->id,
                    'status' => 'ACTIVE',
                    'enrolled_at' => now()->subDays(rand(10, 60)),
                ]);
            }

            // Create topics
            for ($t = 1; $t <= 2; $t++) {
                $topic = Topic::create([
                    'course_id' => $course->id,
                    'title' => "Topic {$t}: " . ['Introduction', 'Core Concepts'][$t - 1],
                    'description' => "Key topics in {$cd['title']}.",
                    'order_no' => $t,
                ]);

                // Materials
                Material::create([
                    'topic_id' => $topic->id,
                    'type' => 'PDF',
                    'title' => "Lecture Notes - Topic {$t}",
                    'file_url' => '#',
                    'uploaded_by' => $tutorId,
                ]);

                Material::create([
                    'topic_id' => $topic->id,
                    'type' => 'VIDEO',
                    'title' => "Video Lecture - Topic {$t}",
                    'file_url' => '#',
                    'uploaded_by' => $tutorId,
                ]);
            }

            // Assignment
            $assignment = Assignment::create([
                'course_id' => $course->id,
                'title' => "Assignment: " . $cd['title'],
                'instructions' => "Complete the assignment for {$cd['title']}.",
                'max_marks' => 100,
                'open_at' => now()->subDays(7),
                'due_at' => now()->addDays(14),
                'allow_late' => true,
            ]);

            // Some submissions
            foreach (array_slice($studentIds, 0, 3) as $sid) {
                AssignmentSubmission::create([
                    'assignment_id' => $assignment->id,
                    'student_id' => $sid,
                    'content' => 'My submission for this assignment.',
                    'submitted_at' => now()->subDays(rand(1, 5)),
                    'marks' => rand(60, 95),
                    'feedback' => 'Good work.',
                    'status' => 'ON_TIME',
                ]);
            }

            // Announcement
            Announcement::create([
                'scope' => 'COURSE',
                'course_id' => $course->id,
                'title' => "Welcome to {$cd['title']}!",
                'body' => "Dear students, welcome to {$cd['title']}. Please review the course materials regularly.",
                'is_pinned' => true,
                'created_by' => $tutorId,
            ]);
        }

        // Global announcement from Principal
        Announcement::create([
            'scope' => 'GLOBAL',
            'course_id' => null,
            'title' => 'Welcome to ELMS Butimba',
            'body' => 'Welcome to the Electronic Learning Management System of Butimba Teachers College. We wish you a productive academic year.',
            'is_pinned' => true,
            'created_by' => $principal->id,
        ]);
    }
}

