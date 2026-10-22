<x-layout title="Tasks · ChurchFlow">
    @php
        $canManage = auth()->user()->hasPermission('members.view');
        $me = auth()->id();
        $statuses = [
            'pending' => 'Pending',
            'in_progress' => 'In progress',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
        ];
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Tasks</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Assignable to-dos, optionally tied to an event. Open tasks appear on the calendar automatically,
                and a reminder pops up at the moment you set.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="task-create" aria-expanded="false">
                <x-ui.icon name="check" class="h-4 w-4" /> Add Task
            </button>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    @if($canManage)
        <div data-panel="task-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Add a task"
                description="Set a due date for the deadline and, if you want a nudge, a reminder date and time — the reminder plays a chime when it comes round."
                :action="route('tasks.store')"
                submit="Add task"
                layout="inline"
            >
                <x-form.input name="title" label="What needs doing" required placeholder="e.g. Order new chairs for the youth hall" />
                <x-form.input name="due_date" label="Due date" type="date" hint="Optional — an undated task simply never appears on the calendar." />
                <x-form.input name="remind_at" label="Remind me at" type="datetime-local"
                    hint="Optional. When this moment passes, a reminder popup chimes for the assignee." />
                <x-form.select name="status" label="Status" :value="'pending'" :options="$statuses" />
                <x-form.textarea name="description" label="Notes" rows="2" wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($tasks->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No tasks yet</p>
                <p class="cf-small cf-muted">Tasks you add appear here and on the calendar until they're done.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>Task</th><th>Due</th><th>Reminder</th><th>Status</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($tasks as $task)
                            <tr>
                                <td>
                                    <strong>{{ $task->title }}</strong>
                                    @if($task->description)
                                        <div class="cf-tiny cf-muted">{{ \Illuminate\Support\Str::limit($task->description, 80) }}</div>
                                    @endif
                                </td>
                                <td class="cf-small cf-muted" style="white-space:nowrap">
                                    {{ $task->due_date?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="cf-small cf-muted" style="white-space:nowrap">
                                    @if($task->remind_at)
                                        <span title="{{ $task->remind_at->format('d M Y, H:i') }}">{{ $task->remind_at->diffForHumans() }}</span>
                                        @if($task->reminder_sent_at)
                                            <span class="cf-badge cf-badge--ok" style="margin-left:.3rem">Sent</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @php $tone = match ($task->status) {
                                        'done' => 'cf-badge--ok',
                                        'cancelled' => 'cf-badge--danger',
                                        'in_progress' => 'cf-badge--brand',
                                        default => 'cf-badge--warn',
                                    }; @endphp
                                    <span class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($task->status)) }}</span>
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    {{-- Quick status change — the update route accepts status and remind_at. --}}
                                    @if($task->status !== 'done' && ($task->assigned_to === $me || $task->created_by === $me || auth()->user()->hasPermission('settings.manage')))
                                        <form method="POST" action="{{ route('tasks.update', $task) }}" style="display:inline">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="done">
                                            <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Mark done</button>
                                        </form>
                                    @endif
                                    @if(auth()->user()->can('delete', $task))
                                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" style="display:inline"
                                            data-confirm="Delete the task “{{ $task->title }}”? This cannot be undone."
                                            data-confirm-title="Delete task?" data-confirm-ok="Delete">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="cf-btn cf-btn--danger cf-btn--sm">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($tasks->hasPages())
        <div style="padding:1rem 1.35rem">{{ $tasks->links() }}</div>
    @endif

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function () {
                var btn = document.querySelector('[data-toggle="task-create"]');
                var panel = document.querySelector('[data-panel="task-create"]');
                if (!btn || !panel) return;
                btn.addEventListener('click', function () {
                    var isOpen = !panel.hasAttribute('hidden');
                    if (isOpen) { panel.setAttribute('hidden', ''); }
                    else { panel.removeAttribute('hidden'); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                    btn.setAttribute('aria-expanded', String(!isOpen));
                });
                if (panel.querySelector('[aria-invalid="true"]')) { panel.removeAttribute('hidden'); }
            })();
        </script>
    @endpush
</x-layout>
