<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;

class ApiDocsController extends Controller
{
    public function index()
    {
        return view('api-docs', [
            'appName' => config('app.name'),
            'specUrl' => url('/api/docs/openapi.json'),
        ]);
    }

    public function openapiSpec()
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/') && !str_starts_with($r->uri(), 'api/docs'));

        $paths = [];
        $tags = [];

        foreach ($routes as $route) {
            $uri = '/' . $route->uri();
            $group = $this->getGroup($route->uri());
            $tags[$group] = $group;

            foreach ($route->methods() as $method) {
                $method = strtolower($method);
                if (in_array($method, ['head', 'options'])) continue;

                $hasAuth = in_array('auth:sanctum', $route->gatherMiddleware());
                $hasRole = collect($route->gatherMiddleware())->first(fn ($m) => str_starts_with($m, 'role:'));
                $roleLabel = $hasRole ? str_replace('role:', '', $hasRole) : null;

                $operationId = str_replace(['/', '{', '}', '-'], ['_', '', '', '_'], trim($uri, '/')) . '_' . $method;
                $summary = $this->getSummary($route->uri(), $method);
                $description = $this->getDescription($route->uri(), $method);
                $params = $this->getPathParams($uri);

                $responses = [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean', 'example' => true],
                                        'data' => ['type' => 'object'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                if ($hasAuth) {
                    $responses['401'] = ['description' => 'Unauthenticated'];
                }
                if ($hasRole) {
                    $responses['403'] = ['description' => 'Forbidden — requires role: ' . $roleLabel];
                }
                $responses['404'] = ['description' => 'Not found'];
                $responses['422'] = ['description' => 'Validation error'];

                $operation = [
                    'tags' => [$group],
                    'summary' => $summary,
                    'description' => $description,
                    'operationId' => $operationId,
                    'responses' => $responses,
                ];

                if ($hasAuth) {
                    $operation['security'] = [['bearerAuth' => []]];
                }

                if (!empty($params)) {
                    $operation['parameters'] = $params;
                }

                if (in_array($method, ['post', 'put', 'patch'])) {
                    $operation['requestBody'] = [
                        'description' => 'Request payload',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'object'],
                            ],
                        ],
                    ];
                }

                $paths[$uri][$method] = $operation;
            }
        }

        ksort($paths);

        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name') . ' API',
                'description' => "Butimba Teachers College — ELMS Backend API\n\n## Authentication\n\nMost endpoints require a Bearer token obtained from `POST /api/auth/login`.\n\nInclude the token in the `Authorization` header:\n```\nAuthorization: Bearer <your-token>\n```\n\n## Roles\n\nSome endpoints require specific roles (SUPER_ADMIN, REGISTRAR, TUTOR, ACCOUNTANT, PRINCIPAL).",
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Butimba Teachers College',
                    'email' => 'info@butimba.ac.tz',
                ],
            ],
            'servers' => [
                ['url' => url('/api'),
                 'description' => 'Current server'],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                        'description' => 'Enter your Sanctum bearer token',
                    ],
                ],
            ],
            'tags' => collect($tags)->map(fn ($name) => ['name' => $name, 'description' => $name . ' endpoints'])->values()->all(),
            'paths' => $paths,
        ];

        return response()->json($spec, 200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
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

    private function getSummary(string $uri, string $method): string
    {
        $uri = preg_replace('/^api\//', '', $uri);

        if (str_ends_with($uri, '/login')) return 'Login';
        if (str_ends_with($uri, '/register')) return 'Register';
        if (str_ends_with($uri, '/logout')) return 'Logout';
        if (str_ends_with($uri, '/me')) return 'Get current user';
        if (str_ends_with($uri, '/stats')) return 'Dashboard statistics';
        if (str_ends_with($uri, '/charts')) return 'Dashboard chart data';
        if (str_ends_with($uri, '/calendar')) return 'Calendar events';
        if (str_ends_with($uri, '/activity')) return 'Recent activity';
        if (str_ends_with($uri, '/mine') || str_ends_with($uri, '/my-courses')) return 'My courses';
        if (str_ends_with($uri, '/enroll')) return 'Enroll in course';
        if (str_ends_with($uri, '/students')) return 'Get course students';
        if (str_ends_with($uri, '/tutors')) return 'Get course tutors';
        if (str_ends_with($uri, '/verify')) return 'Verify payment';
        if (str_ends_with($uri, '/review')) return 'Review application';
        if (str_ends_with($uri, '/submit')) return 'Submit assignment';
        if (str_ends_with($uri, '/grade')) return 'Grade submission';
        if (str_ends_with($uri, '/download')) return 'Download file';
        if (str_ends_with($uri, '/export')) return 'Export CSV';
        if (str_ends_with($uri, '/backup')) return 'Create backup';
        if (str_ends_with($uri, '/restore')) return 'Restore backup';
        if (str_ends_with($uri, '/issue')) return 'Issue certificate';
        if (str_ends_with($uri, '/bulk-issue')) return 'Bulk issue certificates';
        if (str_ends_with($uri, '/revoke')) return 'Revoke certificate';
        if (str_ends_with($uri, '/reissue')) return 'Reissue certificate';
        if (str_ends_with($uri, '/duplicate')) return 'Duplicate template';
        if (str_ends_with($uri, '/generate')) return 'Generate transcript';
        if (str_ends_with($uri, '/unread-count')) return 'Unread notification count';
        if (str_ends_with($uri, '/read-all')) return 'Mark all notifications read';
        if (str_ends_with($uri, '/read')) return 'Mark as read';

        return match ($method) {
            'get' => 'List / retrieve ' . $this->getResourceName($uri),
            'post' => str_contains($uri, '{') ? 'Perform action' : 'Create ' . $this->getResourceName($uri),
            'put' => 'Update ' . $this->getResourceName($uri),
            'patch' => 'Update ' . $this->getResourceName($uri),
            'delete' => 'Delete ' . $this->getResourceName($uri),
            default => ucfirst($method),
        };
    }

    private function getDescription(string $uri, string $method): string
    {
        $uri = preg_replace('/^api\//', '', $uri);
        $group = $this->getGroup('api/' . $uri);

        $desc = $group . ' — ';

        if (str_contains($uri, '{')) {
            $desc .= 'Operates on a specific resource by ID';
        } elseif ($method === 'get') {
            $desc .= 'Returns a list of resources or a single resource';
        } elseif ($method === 'post') {
            $desc .= 'Creates a new resource or performs an action';
        } elseif ($method === 'put' || $method === 'patch') {
            $desc .= 'Updates an existing resource';
        } elseif ($method === 'delete') {
            $desc .= 'Removes a resource';
        }

        return $desc;
    }

    private function getResourceName(string $uri): string
    {
        $parts = explode('/', $uri);
        $last = end($parts);
        if (str_starts_with($last, '{')) {
            $prev = $parts[count($parts) - 2] ?? 'resource';
            return rtrim($prev, 's');
        }
        return rtrim($last, 's');
    }

    private function getPathParams(string $uri): array
    {
        $params = [];
        if (preg_match_all('/\{([^}]+)\}/', $uri, $matches)) {
            foreach ($matches[1] as $param) {
                $params[] = [
                    'name' => $param,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => 'The ' . str_replace('_', ' ', $param) . ' identifier',
                ];
            }
        }
        return $params;
    }
}
