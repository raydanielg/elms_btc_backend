<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Route as RoutingRoute;

class ApiDocsController extends Controller
{
    public function index()
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/') || $r->uri() === 'api')
            ->map(fn ($r) => [
                'method' => implode('|', $r->methods()),
                'uri' => '/' . $r->uri(),
                'name' => $r->getName() ?: '',
                'middleware' => array_filter($r->gatherMiddleware(), fn ($m) => $m !== 'api'),
            ])
            ->groupBy(fn ($r) => $this->getGroup($r['uri']))
            ->sortBy(fn ($_, $key) => $key);

        $endpoints = [];
        foreach ($routes as $group => $items) {
            $endpoints[$group] = $items->sortBy('uri')->values()->all();
        }

        return view('api-docs', [
            'endpoints' => $endpoints,
            'appName' => config('app.name'),
        ]);
    }

    public function json()
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/') || $r->uri() === 'api')
            ->map(fn ($r) => [
                'method' => $r->methods(),
                'uri' => '/' . $r->uri(),
                'middleware' => array_filter($r->gatherMiddleware(), fn ($m) => $m !== 'api'),
            ])
            ->groupBy(fn ($r) => $this->getGroup($r['uri']))
            ->sortBy(fn ($_, $key) => $key);

        $result = [];
        foreach ($routes as $group => $items) {
            $result[$group] = $items->sortBy('uri')->values()->all();
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    private function getGroup(string $uri): string
    {
        $uri = preg_replace('/^api\//', '', $uri);
        $parts = explode('/', $uri);

        return match ($parts[0] ?? '') {
            'auth' => 'Authentication',
            'dashboard' => 'Dashboard',
            'roles', 'capabilities', 'contexts' => 'RBAC',
            'programs' => 'Programs',
            'subjects' => 'Subjects',
            'departments' => 'Departments',
            'categories' => 'Course Categories',
            'courses' => 'Courses',
            'modules' => 'Course Modules',
            'enrol', 'cohorts', 'groups', 'groupings' => 'Enrolment & Groups',
            'files', 'repositories' => 'Files API',
            'topics' => 'Topics & Materials',
            'assignments', 'submissions' => 'Assignments',
            'quizzes', 'quiz' => 'Quizzes',
            'question-categories', 'questions' => 'Question Bank',
            'grade-items', 'grade-categories' => 'Gradebook',
            'payments', 'fees', 'debts', 'payment-gateways' => 'Finance',
            'applications' => 'Applications',
            'messages' => 'Messaging',
            'notifications' => 'Notifications',
            'calendar' => 'Calendar',
            'badges' => 'Badges',
            'competency-frameworks', 'competencies' => 'Competencies',
            'webservice' => 'Web Services',
            'backups' => 'Backup & Restore',
            'certificate-templates', 'certificates', 'pages', 'elements', 'transcripts', 'awards' => 'Certificates & Awards',
            'results' => 'Results',
            'users' => 'User Management',
            'profile-fields' => 'Profile Fields',
            'announcements' => 'Announcements',
            'news' => 'News',
            'media' => 'Media Gallery',
            'verify' => 'Public Verification',
            'me' => 'Self-Service',
            'admin' => 'Admin Config',
            'reports' => 'Reports & Logs',
            'home' => 'Home Page',
            default => 'Other',
        };
    }
}
