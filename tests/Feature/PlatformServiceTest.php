<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MMessageConversation;
use App\Models\MMessageConversationMember;
use App\Models\MMessage;
use App\Models\MNotification;
use App\Models\MCalendarEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlatformServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private string $token1;
    private string $token2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user1 = User::factory()->create(['role' => 'TUTOR', 'status' => 'ACTIVE']);
        $this->user2 = User::factory()->create(['role' => 'STUDENT', 'status' => 'ACTIVE']);
        $this->token1 = $this->user1->createToken('test')->plainTextToken;
        $this->token2 = $this->user2->createToken('test')->plainTextToken;
    }

    // ===== Messaging =====

    public function test_create_conversation(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson('/api/messages/conversations', [
                'member_ids' => [$this->user2->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_group', false);
    }

    public function test_send_message_and_notify(): void
    {
        $conversation = MMessageConversation::create(['is_group' => false, 'created_by' => $this->user1->id]);
        MMessageConversationMember::create(['conversation_id' => $conversation->id, 'user_id' => $this->user1->id]);
        MMessageConversationMember::create(['conversation_id' => $conversation->id, 'user_id' => $this->user2->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/messages/conversations/{$conversation->id}", [
                'body' => 'Hello there!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.body', 'Hello there!');

        // Notification should be created for user2
        $this->assertDatabaseHas('m_notifications', [
            'user_id' => $this->user2->id,
            'event_type' => 'message_received',
        ]);
    }

    public function test_non_member_cannot_send_message(): void
    {
        $conversation = MMessageConversation::create(['is_group' => false, 'created_by' => $this->user1->id]);
        MMessageConversationMember::create(['conversation_id' => $conversation->id, 'user_id' => $this->user1->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token2)
            ->postJson("/api/messages/conversations/{$conversation->id}", [
                'body' => 'Unauthorized message',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'NOT_MEMBER');
    }

    public function test_list_conversations(): void
    {
        $conversation = MMessageConversation::create(['is_group' => false, 'created_by' => $this->user1->id]);
        MMessageConversationMember::create(['conversation_id' => $conversation->id, 'user_id' => $this->user1->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->getJson('/api/messages/conversations');

        $response->assertStatus(200);
    }

    public function test_mark_conversation_read(): void
    {
        $conversation = MMessageConversation::create(['is_group' => false, 'created_by' => $this->user1->id]);
        MMessageConversationMember::create(['conversation_id' => $conversation->id, 'user_id' => $this->user1->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/messages/conversations/{$conversation->id}/read");

        $response->assertStatus(200);
        $this->assertNotNull(
            MMessageConversationMember::where('conversation_id', $conversation->id)
                ->where('user_id', $this->user1->id)
                ->first()->last_read_at
        );
    }

    // ===== Notifications =====

    public function test_list_notifications(): void
    {
        MNotification::create([
            'user_id' => $this->user1->id,
            'subject' => 'Test notification',
            'read' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->getJson('/api/me/notifications');

        $response->assertStatus(200);
    }

    public function test_unread_count(): void
    {
        MNotification::create(['user_id' => $this->user1->id, 'subject' => 'N1', 'read' => false]);
        MNotification::create(['user_id' => $this->user1->id, 'subject' => 'N2', 'read' => false]);
        MNotification::create(['user_id' => $this->user1->id, 'subject' => 'N3', 'read' => true]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->getJson('/api/me/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_mark_notification_read(): void
    {
        $notif = MNotification::create(['user_id' => $this->user1->id, 'subject' => 'Test', 'read' => false]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson("/api/notifications/{$notif->id}/read");

        $response->assertStatus(200);
        $this->assertTrue($notif->fresh()->read);
    }

    public function test_mark_all_read(): void
    {
        MNotification::create(['user_id' => $this->user1->id, 'subject' => 'N1', 'read' => false]);
        MNotification::create(['user_id' => $this->user1->id, 'subject' => 'N2', 'read' => false]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson('/api/me/notifications/read-all');

        $response->assertStatus(200);
        $this->assertEquals(0, MNotification::where('user_id', $this->user1->id)->where('read', false)->count());
    }

    // ===== Calendar =====

    public function test_create_calendar_event(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->postJson('/api/calendar', [
                'name' => 'Faculty Meeting',
                'timestart' => now()->addDays(3)->toDateTimeString(),
                'timeduration' => 3600,
                'location' => 'Main Hall',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Faculty Meeting')
            ->assertJsonPath('data.eventtype', 'user');
    }

    public function test_list_calendar_events(): void
    {
        MCalendarEvent::create([
            'name' => 'Event 1', 'timestart' => now()->addDays(1),
            'timeduration' => 0, 'eventtype' => 'user', 'user_id' => $this->user1->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->getJson('/api/calendar');

        $response->assertStatus(200);
    }

    public function test_delete_own_calendar_event(): void
    {
        $event = MCalendarEvent::create([
            'name' => 'My Event', 'timestart' => now()->addDays(1),
            'timeduration' => 0, 'eventtype' => 'user', 'user_id' => $this->user1->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->deleteJson("/api/calendar/{$event->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('m_calendar_events', ['id' => $event->id]);
    }

    public function test_cannot_delete_others_event(): void
    {
        $event = MCalendarEvent::create([
            'name' => 'Their Event', 'timestart' => now()->addDays(1),
            'timeduration' => 0, 'eventtype' => 'user', 'user_id' => $this->user1->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token2)
            ->deleteJson("/api/calendar/{$event->id}");

        $response->assertStatus(403);
    }

    // ===== Logging =====

    public function test_admin_can_view_logs(): void
    {
        $admin = User::factory()->create(['role' => 'SUPER_ADMIN', 'status' => 'ACTIVE']);
        $token = $admin->createToken('test')->plainTextToken;

        \DB::table('m_logstore_standard_log')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'eventname' => '\core\event\user_loggedin',
            'component' => 'core',
            'action' => 'loggedin',
            'userid' => $admin->id,
            'timecreated' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/reports/logs');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_view_logs(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token1)
            ->getJson('/api/reports/logs');

        $response->assertStatus(403);
    }
}
