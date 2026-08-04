<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminServiceController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseStructureController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EnrolmentController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\FinanceBridgeController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GradebookController;
use App\Http\Controllers\LmsController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ProfileFieldController;
use App\Http\Controllers\PlatformServiceController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\QuizEngineController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

// API Documentation (public)
Route::get('/docs', [ApiDocsController::class, 'openapiSpec']);
Route::get('/docs/openapi.json', [ApiDocsController::class, 'openapiSpec']);

// Auth routes (public)
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Public certificate verification (no auth — spec §5)
Route::get('/verify/{code}', [CertificateController::class, 'verify']);
Route::get('/auth/email/confirm/{token}', [AuthController::class, 'confirmEmail']);
Route::post('/auth/email/resend', [AuthController::class, 'resendConfirmation']);

// Public profile fields (signup form)
Route::get('/profile-fields/signup', [ProfileFieldController::class, 'signupFields']);

// Public read routes
Route::get('/programs', [ProgramController::class, 'index']);
Route::get('/subjects', [SubjectController::class, 'index']);
Route::get('/departments', [DepartmentController::class, 'index']);
Route::get('/categories', [CourseStructureController::class, 'indexCategories']);
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);
Route::get('/courses/{courseId}/contents', [CourseStructureController::class, 'courseContents']);
Route::get('/announcements', [LmsController::class, 'getAnnouncements']);
Route::get('/news', [AdminController::class, 'getNews']);
Route::get('/media', [AdminController::class, 'getMedia']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/charts', [DashboardController::class, 'chartData']);
    Route::get('/dashboard/calendar', [DashboardController::class, 'calendar']);
    Route::get('/dashboard/activity', [DashboardController::class, 'recentActivity']);

    // RBAC — Contexts, Roles, Capabilities (spec §2-3)
    Route::get('/roles', [RbacController::class, 'indexRoles']);
    Route::post('/roles', [RbacController::class, 'createRole']);
    Route::post('/roles/{roleId}/assign', [RbacController::class, 'assignRole']);
    Route::delete('/roles/{roleId}/assign', [RbacController::class, 'unassignRole']);
    Route::post('/roles/{roleId}/capabilities', [RbacController::class, 'setCapabilityOverride']);
    Route::get('/roles/{roleId}/assignments', [RbacController::class, 'roleAssignments']);
    Route::get('/capabilities', [RbacController::class, 'indexCapabilities']);
    Route::get('/contexts', [RbacController::class, 'indexContexts']);
    Route::post('/contexts', [RbacController::class, 'createContext']);
    Route::get('/context/{contextId}/permissions/{userId}', [RbacController::class, 'getPermissions']);

    // My courses
    Route::get('/courses/mine', [CourseController::class, 'myCourses']);

    // Student self-service
    Route::get('/me/results', [FinanceController::class, 'myResults']);
    Route::get('/me/payments', [FinanceController::class, 'myPayments']);
    Route::get('/me/debts', [FinanceController::class, 'myDebts']);

    // Course enrollment
    Route::post('/courses/{course}/enroll', [CourseController::class, 'enroll']);
    Route::get('/courses/{course}/students', [CourseController::class, 'getStudents']);
    Route::get('/courses/{course}/tutors', [CourseController::class, 'getTutors']);

    // Course structure — categories, sections, modules (spec §4-5)
    Route::post('/categories', [CourseStructureController::class, 'createCategory']);
    Route::patch('/categories/{id}', [CourseStructureController::class, 'updateCategory']);
    Route::delete('/categories/{id}', [CourseStructureController::class, 'deleteCategory']);
    Route::get('/modules', [CourseStructureController::class, 'indexModules']);
    Route::get('/courses/{courseId}/sections', [CourseStructureController::class, 'indexSections']);
    Route::post('/courses/{courseId}/sections', [CourseStructureController::class, 'createSection']);
    Route::patch('/courses/{courseId}/sections/{sectionNum}', [CourseStructureController::class, 'updateSection']);
    Route::post('/courses/{courseId}/sections/{sectionNum}/move', [CourseStructureController::class, 'moveSection']);
    Route::post('/courses/{courseId}/modules', [CourseStructureController::class, 'addModule']);
    Route::patch('/modules/{cmid}', [CourseStructureController::class, 'updateModule']);
    Route::post('/modules/{cmid}/move', [CourseStructureController::class, 'moveModule']);
    Route::delete('/modules/{cmid}', [CourseStructureController::class, 'deleteModule']);
    Route::post('/courses/{courseId}/reset', [CourseStructureController::class, 'resetCourse']);

    // Enrolment methods, groups, cohorts (spec §6-8)
    Route::get('/courses/{courseId}/enrolment-methods', [EnrolmentController::class, 'indexMethods']);
    Route::post('/courses/{courseId}/enrolment-methods', [EnrolmentController::class, 'createMethod']);
    Route::post('/enrol/manual', [EnrolmentController::class, 'manualEnrol']);
    Route::post('/enrol/self', [EnrolmentController::class, 'selfEnrol']);
    Route::post('/enrol/{enrolId}/suspend/{userId}', [EnrolmentController::class, 'suspendEnrolment']);
    Route::get('/courses/{courseId}/enrolled-users', [EnrolmentController::class, 'enrolledUsers']);
    Route::get('/courses/{courseId}/groups', [EnrolmentController::class, 'indexGroups']);
    Route::post('/courses/{courseId}/groups', [EnrolmentController::class, 'createGroup']);
    Route::post('/courses/{courseId}/groups/auto', [EnrolmentController::class, 'autoCreateGroups']);
    Route::post('/groups/{groupId}/members', [EnrolmentController::class, 'addGroupMember']);
    Route::delete('/groups/{groupId}/members/{userId}', [EnrolmentController::class, 'removeGroupMember']);
    Route::post('/courses/{courseId}/groupings', [EnrolmentController::class, 'createGrouping']);
    Route::post('/groupings/{groupingId}/groups', [EnrolmentController::class, 'addGroupToGrouping']);
    Route::get('/cohorts', [EnrolmentController::class, 'indexCohorts']);
    Route::post('/cohorts', [EnrolmentController::class, 'createCohort']);
    Route::post('/cohorts/{cohortId}/members', [EnrolmentController::class, 'addCohortMember']);
    Route::post('/cohorts/{cohortId}/sync-to-course', [EnrolmentController::class, 'syncToCourse']);

    // Files API (spec §18)
    Route::post('/files/draft', [FilesController::class, 'uploadDraft']);
    Route::post('/{component}/{cmid}/files/save', [FilesController::class, 'saveFromDraft']);
    Route::get('/files/{fileId}/download', [FilesController::class, 'download']);
    Route::get('/files/area/{contextId}/{component}/{filearea}/{itemid?}', [FilesController::class, 'listFiles']);
    Route::get('/files/{contextId}/{component}/{filearea}/{itemId}/{filename}', [FilesController::class, 'getFile']);
    Route::delete('/files/{fileId}', [FilesController::class, 'deleteFile']);
    Route::get('/repositories', [FilesController::class, 'indexRepositories']);

    // LMS — Topics
    Route::get('/courses/{course}/topics', [LmsController::class, 'getTopics']);
    Route::post('/courses/{course}/topics', [LmsController::class, 'createTopic']);
    Route::get('/topics/{topic}/materials', [LmsController::class, 'getMaterials']);
    Route::post('/topics/{topic}/materials', [LmsController::class, 'createMaterial']);

    // LMS — Assignments
    Route::post('/courses/{course}/assignments', [LmsController::class, 'createAssignment']);
    Route::post('/assignments/{assignment}/submit', [LmsController::class, 'submitAssignment']);
    Route::get('/assignments/{assignment}/submissions', [LmsController::class, 'getSubmissions']);
    Route::patch('/submissions/{submission}/grade', [LmsController::class, 'gradeSubmission']);

    // LMS — Quizzes
    Route::post('/courses/{course}/quizzes', [LmsController::class, 'createQuiz']);
    Route::post('/quizzes/{quiz}/questions', [LmsController::class, 'createQuestion']);
    Route::post('/quizzes/{quiz}/attempt/start', [LmsController::class, 'startAttempt']);
    Route::post('/quizzes/{quiz}/attempt/submit', [LmsController::class, 'submitAttempt']);
    Route::get('/quizzes/{quiz}/results', [LmsController::class, 'getQuizResults']);

    // Question Bank (spec §10)
    Route::get('/question-categories', [QuestionBankController::class, 'indexCategories']);
    Route::post('/question-categories', [QuestionBankController::class, 'createCategory']);
    Route::get('/questions', [QuestionBankController::class, 'indexQuestions']);
    Route::post('/questions', [QuestionBankController::class, 'createQuestion']);
    Route::get('/questions/{id}', [QuestionBankController::class, 'showQuestion']);
    Route::patch('/questions/{id}', [QuestionBankController::class, 'updateQuestion']);
    Route::delete('/questions/{id}', [QuestionBankController::class, 'deleteQuestion']);

    // Quiz Engine — Moodle-style (spec §9, §11)
    Route::get('/quiz/{quizId}/slots', [QuizEngineController::class, 'indexSlots']);
    Route::post('/quiz/{quizId}/slots', [QuizEngineController::class, 'addSlot']);
    Route::delete('/quiz/{quizId}/slots/{slot}', [QuizEngineController::class, 'removeSlot']);
    Route::post('/quiz/{quizId}/attempt/start', [QuizEngineController::class, 'startAttempt']);
    Route::post('/quiz/{quizId}/attempt/{attemptId}/process', [QuizEngineController::class, 'processAttempt']);
    Route::post('/quiz/{quizId}/attempt/{attemptId}/finish', [QuizEngineController::class, 'finishAttempt']);
    Route::get('/quiz/{quizId}/attempts', [QuizEngineController::class, 'indexAttempts']);
    Route::get('/quiz/{quizId}/my-attempts', [QuizEngineController::class, 'myAttempts']);

    // Gradebook & Completion (spec §12, §14)
    Route::get('/courses/{courseId}/gradebook', [GradebookController::class, 'gradebook']);
    Route::post('/courses/{courseId}/grade-categories', [GradebookController::class, 'createCategory']);
    Route::post('/courses/{courseId}/grade-items', [GradebookController::class, 'createItem']);
    Route::put('/grade-items/{itemId}/grades/{userId}', [GradebookController::class, 'updateGrade']);
    Route::get('/courses/{courseId}/my-grades', [GradebookController::class, 'myGrades']);
    Route::get('/courses/{courseId}/grades/{userId}', [GradebookController::class, 'studentGrades']);
    Route::get('/courses/{courseId}/grades/export', [GradebookController::class, 'exportCsv']);
    Route::post('/courses/{courseId}/completion-criteria', [GradebookController::class, 'setCompletionCriteria']);
    Route::get('/courses/{courseId}/completion/{userId}', [GradebookController::class, 'completionStatus']);
    Route::post('/modules/{cmid}/complete', [GradebookController::class, 'markComplete']);

    // Finance Bridge + Applications (spec §22)
    Route::post('/payments/{paymentId}/verify', [FinanceBridgeController::class, 'verifyPayment']);
    Route::get('/payment-gateways', [FinanceBridgeController::class, 'indexGateways']);
    Route::get('/applications', [FinanceBridgeController::class, 'indexApplications']);
    Route::get('/applications/{id}', [FinanceBridgeController::class, 'showApplication']);
    Route::post('/applications/{id}/accept', [FinanceBridgeController::class, 'acceptApplication']);
    Route::post('/applications/{id}/reject', [FinanceBridgeController::class, 'rejectApplication']);

    // Messaging (spec §20)
    Route::get('/messages/conversations', [PlatformServiceController::class, 'indexConversations']);
    Route::post('/messages/conversations', [PlatformServiceController::class, 'createConversation']);
    Route::get('/messages/conversations/{id}', [PlatformServiceController::class, 'showConversation']);
    Route::post('/messages/conversations/{id}', [PlatformServiceController::class, 'sendMessage']);
    Route::post('/messages/conversations/{id}/read', [PlatformServiceController::class, 'markRead']);

    // Notifications (spec §20.2)
    Route::get('/me/notifications', [PlatformServiceController::class, 'indexNotifications']);
    Route::get('/me/notifications/unread-count', [PlatformServiceController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [PlatformServiceController::class, 'markNotificationRead']);
    Route::post('/me/notifications/read-all', [PlatformServiceController::class, 'markAllRead']);

    // Calendar (spec §21)
    Route::get('/calendar', [PlatformServiceController::class, 'indexCalendarEvents']);
    Route::post('/calendar', [PlatformServiceController::class, 'createCalendarEvent']);
    Route::delete('/calendar/{id}', [PlatformServiceController::class, 'deleteCalendarEvent']);

    // Badges (spec §15)
    Route::get('/badges', [AdminServiceController::class, 'indexBadges']);
    Route::post('/badges', [AdminServiceController::class, 'createBadge']);
    Route::post('/badges/{badgeId}/issue', [AdminServiceController::class, 'issueBadge']);
    Route::get('/me/badges', [AdminServiceController::class, 'myBadges']);

    // Competencies (spec §16)
    Route::get('/competency-frameworks', [AdminServiceController::class, 'indexFrameworks']);
    Route::post('/competency-frameworks', [AdminServiceController::class, 'createFramework']);
    Route::post('/competency-frameworks/{frameworkId}/competencies', [AdminServiceController::class, 'createCompetency']);
    Route::put('/competencies/{competencyId}/rate', [AdminServiceController::class, 'rateCompetency']);

    // Web Services (spec §24)
    Route::post('/webservice/token', [AdminServiceController::class, 'createToken']);
    Route::get('/webservice/tokens', [AdminServiceController::class, 'indexTokens']);
    Route::delete('/webservice/tokens/{id}', [AdminServiceController::class, 'revokeToken']);

    // Backup/Restore (spec §26)
    Route::post('/courses/{courseId}/backup', [AdminServiceController::class, 'createBackup']);
    Route::get('/courses/{courseId}/backups', [AdminServiceController::class, 'indexBackups']);
    Route::post('/backups/{backupId}/restore', [AdminServiceController::class, 'restoreBackup']);

    // Certificates, Awards & Transcripts (spec §1-8)
    Route::get('/certificate-templates', [CertificateController::class, 'indexTemplates']);
    Route::post('/certificate-templates', [CertificateController::class, 'createTemplate']);
    Route::get('/certificate-templates/{id}', [CertificateController::class, 'showTemplate']);
    Route::patch('/certificate-templates/{id}', [CertificateController::class, 'updateTemplate']);
    Route::delete('/certificate-templates/{id}', [CertificateController::class, 'deleteTemplate']);
    Route::post('/certificate-templates/{id}/duplicate', [CertificateController::class, 'duplicateTemplate']);
    Route::post('/certificate-templates/{templateId}/pages', [CertificateController::class, 'addPage']);
    Route::post('/pages/{pageId}/elements', [CertificateController::class, 'addElement']);
    Route::patch('/elements/{id}', [CertificateController::class, 'updateElement']);
    Route::delete('/elements/{id}', [CertificateController::class, 'deleteElement']);

    Route::post('/certificates/issue', [CertificateController::class, 'issue']);
    Route::post('/certificates/bulk-issue', [CertificateController::class, 'bulkIssue']);
    Route::patch('/certificates/{id}/revoke', [CertificateController::class, 'revoke']);
    Route::post('/certificates/{id}/reissue', [CertificateController::class, 'reissue']);
    Route::get('/certificates', [CertificateController::class, 'indexIssues']);
    Route::get('/certificates/{id}', [CertificateController::class, 'showIssue']);
    Route::get('/me/certificates', [CertificateController::class, 'myCertificates']);

    Route::post('/transcripts/generate', [CertificateController::class, 'generateTranscript']);

    Route::get('/awards', [CertificateController::class, 'indexAwards']);
    Route::post('/awards', [CertificateController::class, 'createAward']);

    // LMS — Online Classes
    Route::get('/courses/{course}/online-classes', [LmsController::class, 'getOnlineClasses']);
    Route::post('/courses/{course}/online-classes', [LmsController::class, 'createOnlineClass']);

    // LMS — Discussion Forum
    Route::get('/courses/{course}/forum', [LmsController::class, 'getThreads']);
    Route::post('/courses/{course}/forum', [LmsController::class, 'createThread']);
    Route::post('/threads/{thread}/posts', [LmsController::class, 'createPost']);

    // LMS — Announcements
    Route::get('/courses/{course}/announcements', [LmsController::class, 'getAnnouncements']);
    Route::post('/courses/{course}/announcements', [LmsController::class, 'createAnnouncement']);

    // Applications
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::get('/my-applications', [ApplicationController::class, 'myApplications']);
    Route::get('/applications/{application}', [ApplicationController::class, 'show']);
    Route::get('/applications/{application}/status', [ApplicationController::class, 'status']);
    Route::patch('/applications/{application}/pay-slip', [ApplicationController::class, 'updatePaySlip']);

    // Results — view (student sees own, tutor/admin sees all)
    Route::get('/results', [ResultController::class, 'index']);

    // ===== RBAC Protected Routes =====

    // Program/Subject/Department management
    Route::middleware('role:SUPER_ADMIN,REGISTRAR')->group(function () {
        Route::post('/programs', [ProgramController::class, 'store']);
        Route::patch('/programs/{program}', [ProgramController::class, 'update']);
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy']);

        Route::post('/subjects', [SubjectController::class, 'store']);
        Route::patch('/subjects/{subject}', [SubjectController::class, 'update']);
        Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);
    });

    Route::middleware('role:SUPER_ADMIN')->group(function () {
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::patch('/departments/{department}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
    });

    // Course management
    Route::middleware('role:SUPER_ADMIN,REGISTRAR')->group(function () {
        Route::post('/courses', [CourseController::class, 'store']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
    });

    // Course tutor assignment
    Route::middleware('role:SUPER_ADMIN,REGISTRAR,TUTOR')->group(function () {
        Route::post('/courses/{course}/tutors', [CourseController::class, 'assignTutor']);
    });
    Route::middleware('role:SUPER_ADMIN,REGISTRAR')->group(function () {
        Route::delete('/courses/{course}/tutors/{tutorId}', [CourseController::class, 'removeTutor']);
    });

    // Application review
    Route::middleware('role:REGISTRAR')->group(function () {
        Route::get('/applications', [ApplicationController::class, 'index']);
        Route::patch('/applications/{application}/review', [ApplicationController::class, 'review']);
    });

    // Finance
    Route::middleware('role:ACCOUNTANT,SUPER_ADMIN')->group(function () {
        Route::get('/fees', [FinanceController::class, 'getFees']);
        Route::post('/fees', [FinanceController::class, 'createFee']);
        Route::patch('/fees/{fee}', [FinanceController::class, 'updateFee']);
        Route::get('/payments', [FinanceController::class, 'getPayments']);
        Route::patch('/payments/{payment}/verify', [FinanceController::class, 'verifyPayment']);
        Route::get('/debts', [FinanceController::class, 'getDebts']);
        Route::post('/debts', [FinanceController::class, 'createDebt']);
        Route::patch('/debts/{debt}', [FinanceController::class, 'updateDebt']);
    });

    // Results management
    Route::middleware('role:TUTOR,REGISTRAR')->group(function () {
        Route::post('/results', [ResultController::class, 'store']);
        Route::patch('/results/{result}', [ResultController::class, 'update']);
    });

    // Admin / Principal
    Route::middleware('role:SUPER_ADMIN')->group(function () {
        Route::get('/users', [AdminController::class, 'indexUsers']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
        Route::get('/audit-logs', [AdminController::class, 'getAuditLogs']);

        // Profile field management (spec §19)
        Route::get('/profile-fields', [ProfileFieldController::class, 'index']);
        Route::post('/profile-fields/categories', [ProfileFieldController::class, 'createCategory']);
        Route::delete('/profile-fields/categories/{categoryId}', [ProfileFieldController::class, 'deleteCategory']);
        Route::post('/profile-fields/categories/{categoryId}/fields', [ProfileFieldController::class, 'createField']);
        Route::patch('/profile-fields/fields/{fieldId}', [ProfileFieldController::class, 'updateField']);
        Route::delete('/profile-fields/fields/{fieldId}', [ProfileFieldController::class, 'deleteField']);

        // Logging & Tasks (spec §23)
        Route::get('/reports/logs', [PlatformServiceController::class, 'indexLogs']);
        Route::get('/admin/tasks', [PlatformServiceController::class, 'indexTasks']);

        // Payment gateway management
        Route::post('/payment-gateways', [FinanceBridgeController::class, 'createGateway']);

        // Admin Config (spec §25)
        Route::get('/admin/config', [AdminServiceController::class, 'indexConfig']);
        Route::put('/admin/config/{name}', [AdminServiceController::class, 'updateConfig']);
    });

    Route::middleware('role:PRINCIPAL,SUPER_ADMIN')->group(function () {
        Route::post('/home/message', [AdminController::class, 'homeMessage']);
        Route::post('/media/gallery', [AdminController::class, 'uploadMedia']);
        Route::post('/media/news', [AdminController::class, 'createNews']);
    });
});
