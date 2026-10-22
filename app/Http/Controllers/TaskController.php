<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->orderBy('due_date')
            ->paginate(25);

        return view('tasks.index', compact('tasks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            // §24: the reminder moment. A plain datetime-local input, so it is
            // validated as a date and stored as a datetime.
            'remind_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'status' => ['nullable', 'in:pending,in_progress,done,cancelled'],
        ]);

        $data['created_by'] = $request->user()->id;
        $task = Task::create($data);

        return redirect()->route('tasks.index')->with('status', 'Task "'.$task->title.'" created.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'status' => ['required', 'in:pending,in_progress,done,cancelled'],
            'remind_at' => ['nullable', 'date'],
        ]);

        // A changed reminder must be allowed to fire again: clearing the
        // "already shown" stamp whenever remind_at moves forward.
        if (array_key_exists('remind_at', $data)) {
            $data['reminder_sent_at'] = null;
        }

        $task->update($data);

        return back()->with('status', 'Task updated.');
    }

    /** §24: delete a task. Soft delete — the Auditable trait records who did it. */
    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('tasks.index')->with('status', 'Task deleted.');
    }

    /**
     * §24: the reminders the signed-in user should see right now. Returns JSON
     * so the layout's reminder popup can poll it without a full page reload.
     * A task reminds its assignee (or, if unassigned, its creator).
     *
     * `once_a_day=1` drops anything already shown in the last 24 hours. The
     * popup sends it: a reminder is stamped `reminder_sent_at` only when the
     * user clicks "Got it", so anything dismissed another way (tab closed, a
     * reload, the scrim) stayed due and re-opened on EVERY page load. A
     * datetime is stored rather than a date so the window rolls rather than
     * resetting at midnight.
     */
    public function dueReminders(Request $request)
    {
        $user = $request->user();

        $tasks = Task::query()
            ->reminderDue()
            ->when($request->boolean('once_a_day'), function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('reminder_sent_at')
                        ->orWhere('reminder_sent_at', '<=', now()->subDay());
                });
            })
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->whereNull('assigned_to')->where('created_by', $user->id);
                    });
            })
            ->orderBy('remind_at')
            ->limit(20)
            ->get(['id', 'title', 'description', 'remind_at', 'due_date']);

        return response()->json([
            'tasks' => $tasks->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'remind_at' => $t->remind_at?->toIso8601String(),
                'due_date' => $t->due_date?->toDateString(),
            ]),
        ]);
    }

    /** §24: acknowledge a reminder so it never pops up again for this user. */
    public function dismissReminder(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task->update(['reminder_sent_at' => now()]);

        return back();
    }
}
