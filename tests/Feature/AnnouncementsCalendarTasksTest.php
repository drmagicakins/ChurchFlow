<?php

namespace Tests\Feature;

use App\Domains\Calendar\Services\CalendarService;
use App\Models\Announcement;
use App\Models\Church;
use App\Models\Department;
use App\Models\Event;
use App\Models\Member;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnnouncementsCalendarTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_church_wide_announcement_is_visible_to_every_member(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $member = Member::factory()->for($church, 'church')->create();
        $announcement = Announcement::create([
            'church_id' => $church->id,
            'title' => 'Service moved',
            'body' => 'Sunday service starts at 10am this week.',
            'audience_type' => 'church',
        ]);

        $this->assertTrue($announcement->isVisibleToMember($member));
    }

    public function test_department_targeted_announcement_is_only_visible_to_department_members(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $ushering = Department::create(['church_id' => $church->id, 'name' => 'Ushering']);
        $inDept = Member::factory()->for($church, 'church')->create();
        $outsideDept = Member::factory()->for($church, 'church')->create();
        $inDept->departments()->attach($ushering);

        $announcement = Announcement::create([
            'church_id' => $church->id,
            'title' => 'Ushers meeting',
            'body' => 'Meet by the entrance at 8am.',
            'audience_type' => 'department',
            'department_id' => $ushering->id,
        ]);

        $this->assertTrue($announcement->isVisibleToMember($inDept));
        $this->assertFalse($announcement->isVisibleToMember($outsideDept));
    }

    public function test_a_church_cannot_see_another_churchs_announcements(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        Announcement::create(['church_id' => $churchA->id, 'title' => 'A', 'body' => 'x', 'audience_type' => 'church']);
        Announcement::create(['church_id' => $churchB->id, 'title' => 'B', 'body' => 'x', 'audience_type' => 'church']);

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, Announcement::all());
        $this->assertSame('A', Announcement::first()->title);
    }

    public function test_calendar_service_merges_events_and_open_tasks_in_date_order(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        Event::factory()->for($church, 'church')->create([
            'title' => 'Youth Conference', 'starts_at' => now()->addDays(2),
        ]);
        Task::create([
            'church_id' => $church->id, 'title' => 'Book venue',
            'due_date' => now()->addDay(), 'status' => 'pending',
        ]);
        Task::create([
            'church_id' => $church->id, 'title' => 'Already done',
            'due_date' => now()->addDay(), 'status' => 'done',
        ]);

        $items = (new CalendarService())->itemsBetween(now(), now()->addWeek());

        $this->assertCount(2, $items); // the 'done' task is excluded
        $this->assertSame('Book venue', $items->first()['title']);
        $this->assertSame('Youth Conference', $items->last()['title']);
    }

    public function test_a_church_cannot_see_another_churchs_tasks(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        Task::create(['church_id' => $churchA->id, 'title' => 'Task A', 'status' => 'pending']);
        Task::create(['church_id' => $churchB->id, 'title' => 'Task B', 'status' => 'pending']);

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, Task::all());
        $this->assertSame('Task A', Task::first()->title);
    }
}
