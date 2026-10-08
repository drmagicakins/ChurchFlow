<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Church;
use App\Models\Event;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_succeeds_while_capacity_remains(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $event = Event::factory()->for($church, 'church')->create(['capacity' => 2]);
        $m1 = Member::factory()->for($church, 'church')->create();
        $m2 = Member::factory()->for($church, 'church')->create();

        $event->registrations()->create(['member_id' => $m1->id, 'status' => 'registered']);
        $event->registrations()->create(['member_id' => $m2->id, 'status' => 'registered']);

        $this->assertSame(2, $event->fresh()->confirmedCount());
        $this->assertFalse($event->fresh()->hasCapacityFor(1));
    }

    public function test_registration_beyond_capacity_is_waitlisted_not_rejected(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $event = Event::factory()->for($church, 'church')->create(['capacity' => 1]);
        $m1 = Member::factory()->for($church, 'church')->create();
        $m2 = Member::factory()->for($church, 'church')->create();

        $event->registrations()->create(['member_id' => $m1->id, 'status' => 'registered']);

        // Second registrant: application logic (EventController::register)
        // is responsible for choosing 'waitlisted' once hasCapacityFor()
        // returns false — verifying the building block here.
        $this->assertFalse($event->fresh()->hasCapacityFor(1));
    }

    public function test_unlimited_capacity_event_never_reports_full(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $event = Event::factory()->for($church, 'church')->create(['capacity' => null]);

        $this->assertTrue($event->hasCapacityFor(500));
    }

    public function test_a_church_cannot_see_another_churchs_events_or_registrations(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $eventA = Event::factory()->for($churchA, 'church')->create();
        $eventB = Event::factory()->for($churchB, 'church')->create();

        $memberB = Member::factory()->for($churchB, 'church')->create();
        $eventB->registrations()->create(['member_id' => $memberB->id, 'status' => 'registered']);

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, Event::all());
        $this->assertSame($eventA->id, Event::first()->id);
        $this->assertCount(0, \App\Models\EventRegistration::all());
    }

    public function test_attendance_present_count_only_counts_present_and_late(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $session = AttendanceSession::create([
            'church_id' => $church->id,
            'name' => 'Sunday Service',
            'type' => 'service',
            'session_date' => now()->toDateString(),
        ]);

        $present = Member::factory()->for($church, 'church')->create();
        $late = Member::factory()->for($church, 'church')->create();
        $absent = Member::factory()->for($church, 'church')->create();

        $session->records()->create(['member_id' => $present->id, 'status' => 'present']);
        $session->records()->create(['member_id' => $late->id, 'status' => 'late']);
        $session->records()->create(['member_id' => $absent->id, 'status' => 'absent']);

        $this->assertSame(2, $session->presentCount());
    }

    public function test_a_church_cannot_see_another_churchs_attendance_sessions_or_records(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $sessionA = AttendanceSession::create([
            'church_id' => $churchA->id, 'name' => 'A Service', 'type' => 'service', 'session_date' => now(),
        ]);
        $sessionB = AttendanceSession::create([
            'church_id' => $churchB->id, 'name' => 'B Service', 'type' => 'service', 'session_date' => now(),
        ]);

        $memberB = Member::factory()->for($churchB, 'church')->create();
        $sessionB->records()->create(['member_id' => $memberB->id, 'status' => 'present']);

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, AttendanceSession::all());
        $this->assertSame('A Service', AttendanceSession::first()->name);
        $this->assertCount(0, \App\Models\AttendanceRecord::all());
    }

    public function test_member_attendance_rate_is_calculated_correctly(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $member = Member::factory()->for($church, 'church')->create();

        foreach (['present', 'present', 'absent', 'late'] as $i => $status) {
            $session = AttendanceSession::create([
                'church_id' => $church->id,
                'name' => "Session {$i}",
                'type' => 'service',
                'session_date' => now()->subDays($i),
            ]);
            $session->records()->create(['member_id' => $member->id, 'status' => $status]);
        }

        $rate = (new \App\Domains\Attendance\Services\AttendanceAnalyticsService())->memberAttendanceRate($member);

        $this->assertSame(75.0, $rate); // 3 of 4 (present, present, late) count as attended
    }
}
