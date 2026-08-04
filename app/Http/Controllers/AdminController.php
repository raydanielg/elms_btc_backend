<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Announcement;
use App\Models\MediaGallery;
use App\Models\NewsEvent;
use App\Models\AuditLog;

class AdminController extends Controller
{
    // ===== Users =====
    public function indexUsers(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->latest()->paginate($request->get('limit', 20));
        return $this->paginated($users);
    }

    public function createUser(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'email' => 'required|string|email|max:150|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:SUPER_ADMIN,PRINCIPAL,REGISTRAR,ACCOUNTANT,TUTOR,STUDENT,APPLICANT,STAFF',
            'phone' => 'sometimes|string|max:20',
            'status' => 'sometimes|in:ACTIVE,INACTIVE,PENDING,SUSPENDED',
        ]);

        $user = User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? 'ACTIVE',
        ]);

        $this->audit('create_user', 'User', $user->id, ['role' => $user->role]);

        return $this->success($user, 201);
    }

    public function updateUser(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'full_name' => 'sometimes|string|max:150',
            'email' => 'sometimes|string|email|max:150|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|in:SUPER_ADMIN,PRINCIPAL,REGISTRAR,ACCOUNTANT,TUTOR,STUDENT,APPLICANT,STAFF',
            'phone' => 'sometimes|string|max:20',
            'status' => 'sometimes|in:ACTIVE,INACTIVE,PENDING,SUSPENDED',
            'photo_url' => 'sometimes|string|max:255',
        ]);

        $user->update($data);

        $this->audit('update_user', 'User', $user->id, $data);

        return $this->success($user);
    }

    // ===== Principal Home Message =====
    public function homeMessage(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'required|string',
        ]);

        $announcement = Announcement::create([
            'scope' => 'GLOBAL',
            'title' => $data['title'],
            'body' => $data['body'],
            'is_pinned' => true,
            'created_by' => $request->user()->id,
        ]);

        $this->audit('create_announcement', 'Announcement', $announcement->id, ['title' => $data['title']]);

        return $this->success($announcement, 201);
    }

    // ===== Media Gallery =====
    public function uploadMedia(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:PHOTO,VIDEO',
            'title' => 'sometimes|string|max:150',
            'url' => 'required|string|max:255',
        ]);

        $media = MediaGallery::create(array_merge($data, [
            'uploaded_by' => $request->user()->id,
        ]));

        $this->audit('upload_media', 'MediaGallery', $media->id, ['type' => $data['type']]);

        return $this->success($media, 201);
    }

    public function getMedia()
    {
        $media = MediaGallery::with('uploader')->latest()->get();
        return $this->success($media);
    }

    // ===== News & Events =====
    public function createNews(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'body' => 'sometimes|string',
            'image_url' => 'sometimes|string|max:255',
            'event_date' => 'sometimes|date',
            'published_at' => 'sometimes|date',
        ]);

        $news = NewsEvent::create($data);
        $this->audit('create_news', 'NewsEvent', $news->id, ['title' => $data['title']]);
        return $this->success($news, 201);
    }

    public function getNews()
    {
        $news = NewsEvent::latest()->get();
        return $this->success($news);
    }

    // ===== Audit Logs =====
    public function getAuditLogs(Request $request)
    {
        $query = AuditLog::with('actor');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('entity')) {
            $query->where('entity', $request->entity);
        }

        $logs = $query->latest()->paginate($request->get('limit', 20));
        return $this->paginated($logs);
    }
}
