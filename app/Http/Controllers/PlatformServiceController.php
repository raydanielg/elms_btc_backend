<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MMessageConversation;
use App\Models\MMessageConversationMember;
use App\Models\MMessage;
use App\Models\MNotification;
use App\Models\MCalendarEvent;
use Illuminate\Support\Facades\DB;

class PlatformServiceController extends Controller
{
    // ===== Messaging (spec §20) =====

    /** GET /messages/conversations — list user's conversations */
    public function indexConversations(Request $request)
    {
        $conversations = MMessageConversation::whereHas('members', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })->with(['members.user:id,full_name,email', 'messages' => function ($q) {
            $q->latest()->limit(1);
        }])->get();

        return $this->success($conversations);
    }

    /** POST /messages/conversations — create a conversation */
    public function createConversation(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:200',
            'is_group' => 'sometimes|boolean',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'uuid|exists:users,id',
        ]);

        $conversation = MMessageConversation::create([
            'name' => $data['name'] ?? null,
            'is_group' => $data['is_group'] ?? count($data['member_ids']) > 1,
            'created_by' => $request->user()->id,
        ]);

        // Add creator + members
        $memberIds = array_unique(array_merge($data['member_ids'], [$request->user()->id]));
        foreach ($memberIds as $userId) {
            MMessageConversationMember::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
            ]);
        }

        return $this->success($conversation->load('members.user'), 201);
    }

    /** GET /messages/conversations/:id — get conversation with messages */
    public function showConversation(Request $request, string $id)
    {
        $conversation = MMessageConversation::with(['messages.user', 'members.user'])->findOrFail($id);

        // Verify membership
        $isMember = $conversation->members->contains('user_id', $request->user()->id);
        if (!$isMember) {
            return $this->error('NOT_MEMBER', 'You are not a member of this conversation.', 403);
        }

        return $this->success($conversation);
    }

    /** POST /messages/conversations/:id — send a message */
    public function sendMessage(Request $request, string $id)
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'reply_to' => 'nullable|uuid|exists:m_messages,id',
        ]);

        // Verify membership
        $isMember = MMessageConversationMember::where('conversation_id', $id)
            ->where('user_id', $request->user()->id)
            ->exists();
        if (!$isMember) {
            return $this->error('NOT_MEMBER', 'You are not a member of this conversation.', 403);
        }

        $message = MMessage::create([
            'conversation_id' => $id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'reply_to' => $data['reply_to'] ?? null,
        ]);

        // Create notifications for other members
        $otherMembers = MMessageConversationMember::where('conversation_id', $id)
            ->where('user_id', '!=', $request->user()->id)
            ->pluck('user_id');

        foreach ($otherMembers as $userId) {
            MNotification::create([
                'user_id' => $userId,
                'component' => 'core_message',
                'event_type' => 'message_received',
                'subject' => 'New message from ' . $request->user()->full_name,
                'body' => mb_substr($data['body'], 0, 200),
                'context_url' => '/messages/' . $id,
            ]);
        }

        return $this->success($message->load('user'), 201);
    }

    /** POST /messages/conversations/:id/read — mark conversation as read */
    public function markRead(Request $request, string $id)
    {
        MMessageConversationMember::where('conversation_id', $id)
            ->where('user_id', $request->user()->id)
            ->update(['last_read_at' => now()]);

        return $this->success(null);
    }

    // ===== Notifications (spec §20.2) =====

    /** GET /me/notifications — list user's notifications */
    public function indexNotifications(Request $request)
    {
        $notifications = MNotification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->paginated($notifications);
    }

    /** GET /me/notifications/unread-count — count unread */
    public function unreadCount(Request $request)
    {
        $count = MNotification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->count();

        return $this->success(['unread_count' => $count]);
    }

    /** POST /notifications/:id/read — mark notification as read */
    public function markNotificationRead(string $id)
    {
        MNotification::findOrFail($id)->update(['read' => true, 'read_at' => now()]);
        return $this->success(null);
    }

    /** POST /me/notifications/read-all — mark all as read */
    public function markAllRead(Request $request)
    {
        MNotification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->update(['read' => true, 'read_at' => now()]);

        return $this->success(null);
    }

    // ===== Calendar (spec §21) =====

    /** GET /calendar — list events for user */
    public function indexCalendarEvents(Request $request)
    {
        $query = MCalendarEvent::where('visible', true);

        if ($month = $request->get('month')) {
            $query->whereMonth('timestart', $month);
        }
        if ($year = $request->get('year')) {
            $query->whereYear('timestart', $year);
        }
        if ($courseId = $request->get('course_id')) {
            $query->where('course_id', $courseId);
        }

        // User sees: their events + course events + site events
        $query->where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
              ->orWhere('eventtype', 'site')
              ->orWhereIn('course_id', function ($subq) use ($request) {
                  $subq->select('course_id')
                      ->from('m_user_enrolments')
                      ->join('m_enrol', 'm_user_enrolments.enrol_id', '=', 'm_enrol.id')
                      ->where('m_user_enrolments.user_id', $request->user()->id)
                      ->where('m_user_enrolments.status', 'ACTIVE');
              });
        });

        $events = $query->orderBy('timestart')->get();
        return $this->success($events);
    }

    /** POST /calendar — create a calendar event */
    public function createCalendarEvent(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'timestart' => 'required|date',
            'timeduration' => 'sometimes|integer|min:0',
            'eventtype' => 'sometimes|string|in:course,site,user,group,category',
            'course_id' => 'nullable|uuid|exists:courses,id',
            'group_id' => 'nullable|uuid|exists:m_groups,id',
            'location' => 'nullable|string|max:255',
            'visible' => 'sometimes|boolean',
        ]);

        $data['user_id'] = $request->user()->id;
        if (!isset($data['eventtype'])) {
            $data['eventtype'] = 'user';
        }

        $event = MCalendarEvent::create($data);
        return $this->success($event, 201);
    }

    /** DELETE /calendar/:id — delete an event */
    public function deleteCalendarEvent(Request $request, string $id)
    {
        $event = MCalendarEvent::findOrFail($id);

        // Only owner or admin can delete
        if ($event->user_id !== $request->user()->id && !$request->user()->isSuperAdmin()) {
            return $this->error('ACCESS_DENIED', 'You cannot delete this event.', 403);
        }

        $event->delete();
        return $this->success(null);
    }

    // ===== Logging (spec §23) =====

    /** GET /reports/logs — query structured event logs (admin) */
    public function indexLogs(Request $request)
    {
        $query = DB::table('m_logstore_standard_log');

        if ($eventname = $request->get('eventname')) {
            $query->where('eventname', 'like', "%{$eventname}%");
        }
        if ($userid = $request->get('userid')) {
            $query->where('userid', $userid);
        }
        if ($courseid = $request->get('courseid')) {
            $query->where('courseid', $courseid);
        }
        if ($component = $request->get('component')) {
            $query->where('component', $component);
        }
        if ($from = $request->get('from')) {
            $query->where('timecreated', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->where('timecreated', '<=', $to);
        }

        $logs = $query->orderBy('timecreated', 'desc')->paginate(50);
        return $this->paginated($logs);
    }

    /** GET /admin/tasks — list scheduled tasks (admin) */
    public function indexTasks()
    {
        $scheduled = DB::table('m_task_scheduled')->orderBy('component')->get();
        $adhoc = DB::table('m_task_adhoc')->orderBy('nextruntime')->get();

        return $this->success([
            'scheduled' => $scheduled,
            'adhoc' => $adhoc,
        ]);
    }
}
