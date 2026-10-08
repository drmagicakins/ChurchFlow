<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Appointment::class);

        $appointments = Appointment::query()
            ->visibleTo($request->user())
            ->orderBy('scheduled_at')
            ->paginate(25);

        return view('pastoral.appointments.index', compact('appointments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Appointment::class);

        $data = $request->validate([
            'member_id' => ['nullable', 'exists:members,id'],
            'pastoral_case_id' => ['nullable', 'exists:pastoral_cases,id'],
            'pastor_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $appointment = Appointment::create($data);

        return redirect()->route('pastoral.appointments.index')->with('status', 'Appointment scheduled: '.$appointment->title);
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $data = $request->validate([
            'status' => ['required', 'in:scheduled,completed,cancelled,no_show'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $appointment->update($data);

        return back()->with('status', 'Appointment updated.');
    }
}
