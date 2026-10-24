<x-layout :title="$department->name . ' · Departments · ChurchFlow'">
    @php
        $canManage = $canManage ?? auth()->user()->hasPermission('departments.manage');
        $initials = fn($name) => \Illuminate\Support\Str::of($name)
            ->explode(' ')
            ->filter()
            ->map(fn($w) => mb_substr($w, 0, 1))
            ->take(2)
            ->implode('');
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('departments.index') }}">← All departments</a>
            </p>
            <h1 style="margin:.35rem 0 0">{{ $department->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                <span class="cf-badge">{{ $department->organizationalUnit?->name ?? 'Whole church' }}</span>
                <span style="margin-left:.4rem">{{ $department->members->count() }}
                    {{ \Illuminate\Support\Str::plural('member', $department->members->count()) }}</span>
            </p>
        </div>
        @if ($canManage)
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-toggle="dept-edit"
                    aria-expanded="false">Edit</button>
                <form method="POST" action="{{ route('departments.destroy', $department) }}"
                    data-confirm="Remove the {{ $department->name }} department? Its members stay on record — they simply stop being grouped here."
                    data-confirm-title="Remove department?" data-confirm-ok="Remove department">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Remove department</button>
                </form>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="cf-alert cf-alert--error" role="alert">
            <span class="cf-alert__icon"><x-ui.icon name="alert" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">There is a problem with your submission.</p>
                <ul class="cf-alert__list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($department->description)
        <div class="cf-card" style="margin-bottom:1.2rem">
            <div class="cf-card__body">
                <p class="cf-small cf-muted" style="margin:0">{{ $department->description }}</p>
            </div>
        </div>
    @endif

    @if ($canManage)
        <div data-panel="dept-edit" hidden style="margin-bottom:1.2rem">
            <x-form.card title="Edit this department"
                description="Rename it, or move it to another branch. Members stay attached."
                :action="route('departments.update', $department)" method="PUT" submit="Save changes"
                submit-icon="check" layout="inline">
                <x-form.input name="name" label="Department name" required :value="$department->name" />

                @if ($branches->isNotEmpty())
                    <x-form.select name="organizational_unit_id" label="Branch"
                        :value="$department->organizational_unit_id" placeholder="Whole church"
                        :options="$branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name])->all()" />
                @endif

                <x-form.textarea name="description" label="What this department does" rows="2"
                    :value="$department->description" wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        {{-- Members of this department --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Department members</h2>
                <span class="cf-tiny cf-muted">{{ $department->members->count() }}
                    {{ \Illuminate\Support\Str::plural('member', $department->members->count()) }}</span>
            </div>

            @if ($department->members->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No members in this department yet</p>
                    <p class="cf-small cf-muted">
                        @if ($canManage)
                            Add members from the panel beside this one.
                        @else
                            Members added by your team will appear here.
                        @endif
                    </p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Role</th>
                                @if ($canManage)
                                    <th style="text-align:right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($department->members as $member)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:.55rem">
                                            <span class="cfd-user__avatar"
                                                style="width:30px;height:30px;font-size:.72rem"
                                                aria-hidden="true">{{ $initials($member->full_name) }}</span>
                                            <div>
                                                <a
                                                    href="{{ route('members.show', $member) }}">{{ $member->full_name }}</a>
                                                <div class="cf-tiny cf-muted cf-mono">{{ $member->membership_number }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="cf-small cf-muted">
                                        {{ $member->pivot->role ? ucfirst($member->pivot->role) : '—' }}
                                    </td>
                                    @if ($canManage)
                                        <td style="text-align:right">
                                            <form method="POST"
                                                action="{{ route('departments.members.detach', [$department, $member]) }}"
                                                data-confirm="Remove {{ $member->full_name }} from {{ $department->name }}? Their member record is kept."
                                                data-confirm-title="Remove from department?" data-confirm-ok="Remove">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="cf-btn cf-btn--secondary cf-btn--sm">Remove</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Add a member --}}
        @if ($canManage)
            <div class="cf-card">
                <div class="cf-card__head">
                    <h2>Add a member to this department</h2>
                </div>
                <div class="cf-card__body">
                    @if ($availableMembers->isEmpty())
                        <p class="cf-small cf-muted" style="margin:0">
                            Every member is already in this department.
                            <a href="{{ route('members.index') }}">Add a new member</a> first if someone is missing.
                        </p>
                    @else
                        <form method="POST" action="{{ route('departments.members.attach', $department) }}"
                            class="cf-form">
                            @csrf
                            <div class="cf-field">
                                <label class="cf-label" for="dept-member">Member <span class="cf-label__req"
                                        aria-hidden="true">*</span></label>
                                <select name="member_id" id="dept-member" class="cf-select" required>
                                    <option value="" disabled selected>Choose a member…</option>
                                    @foreach ($availableMembers as $candidate)
                                        <option value="{{ $candidate->id }}" @selected(old('member_id') == $candidate->id)>
                                            {{ $candidate->full_name }} ({{ $candidate->membership_number }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('member_id')
                                    <p class="cf-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="cf-form__actions" style="display:flex;justify-content:flex-end">
                                <button type="submit" class="cf-btn cf-btn--primary">
                                    <x-ui.icon name="users" class="h-4 w-4" /> Add to department
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                    <p class="cf-tiny cf-muted" style="margin:0">
                        Department membership drives announcements and SMS targeting — only people listed here are
                        counted as members of this department.
                    </p>
                </div>
            </div>
        @endif
    </div>
    @if ($canManage)
        @push('scripts')
            <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
                (function() {
                    var btn = document.querySelector('[data-toggle="dept-edit"]');
                    var panel = document.querySelector('[data-panel="dept-edit"]');
                    if (!btn || !panel) return;
                    btn.addEventListener('click', function() {
                        var isOpen = !panel.hasAttribute('hidden');
                        if (isOpen) {
                            panel.setAttribute('hidden', '');
                        } else {
                            panel.removeAttribute('hidden');
                            panel.scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }
                        btn.setAttribute('aria-expanded', String(!isOpen));
                    });
                    // A failed save must not leave its messages behind a
                    // collapsed panel.
                    if (panel.querySelector('[aria-invalid="true"], .cf-error')) {
                        panel.removeAttribute('hidden');
                        btn.setAttribute('aria-expanded', 'true');
                    }
                })();
            </script>
        @endpush
    @endif
</x-layout>
