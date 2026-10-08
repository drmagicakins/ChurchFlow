<x-layout title="Announcements · ChurchFlow">
    @php $canManage = auth()->user()->hasPermission('announcements.manage'); @endphp

    <div class="cf-page-head">
        <div>
            <h1>Announcements</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Post once and reach the right people — email is included with your plan.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="ann-create" aria-expanded="false">
                <x-ui.icon name="megaphone" class="h-4 w-4" /> New Announcement
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
        <div data-panel="ann-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Post an announcement"
                description="Choosing an audience decides who sees this and who is emailed — church-wide goes to everyone with an email address."
                :action="route('announcements.store')"
                submit="Post announcement"
                submit-icon="megaphone"
                layout="inline"
            >
                <x-form.input name="title" label="Title" required placeholder="e.g. Service time change this Sunday" />

                <x-form.select name="audience_type" label="Audience" :value="'church_wide'" :options="[
                    'church_wide' => 'Entire church',
                    'branch' => 'A specific branch',
                    'department' => 'A department',
                    'group' => 'A group',
                ]" hint="Emails are sent to every member in this audience who has an address." />

                <x-form.textarea name="body" label="Message" rows="5" required
                    placeholder="Write the announcement as you would say it out loud." wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($announcements->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No announcements yet</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Use <strong>New Announcement</strong> above to reach your congregation.
                    @else
                        Announcements posted by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Audience</th>
                            <th>Posted</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($announcements as $a)
                            <tr>
                                <td><a href="{{ route('announcements.show', $a) }}">{{ $a->title }}</a></td>
                                <td><span class="cf-badge">{{ str_replace('_', ' ', $a->audience_type) }}</span></td>
                                <td class="cf-small cf-muted" style="white-space:nowrap">{{ $a->created_at?->diffForHumans() }}</td>
                                <td style="text-align:right">
                                    <a href="{{ route('announcements.show', $a) }}" class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
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
                var btn = document.querySelector('[data-toggle="ann-create"]');
                var panel = document.querySelector('[data-panel="ann-create"]');
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
