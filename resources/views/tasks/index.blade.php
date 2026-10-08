<x-layout title="Tasks · ChurchFlow">
    @php $canManage = auth()->user()->hasPermission('members.view'); @endphp

    <div class="cf-page-head">
        <div>
            <h1>Tasks</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Assignable to-dos, optionally tied to an event. Open tasks appear on the calendar automatically.
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
                :action="route('tasks.store')"
                submit="Add task"
                layout="inline"
            >
                <x-form.input name="title" label="What needs doing" required placeholder="e.g. Order new chairs for the youth hall" />
                <x-form.input name="due_date" label="Due date" type="date" hint="Optional — an undated task simply never appears on the calendar." />
                <x-form.select name="status" label="Status" :value="'pending'" :options="[
                    'pending' => 'Pending', 'in_progress' => 'In progress', 'done' => 'Done', 'cancelled' => 'Cancelled',
                ]" />
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
                        <tr><th>Task</th><th>Due</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($tasks as $task)
                            <tr>
                                <td>{{ $task->title }}</td>
                                <td class="cf-small cf-muted" style="white-space:nowrap">
                                    {{ $task->due_date?->format('d M Y') ?? '—' }}
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

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
