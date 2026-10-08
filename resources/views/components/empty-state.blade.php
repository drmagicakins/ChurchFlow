{{--
    §34: "Never show 'No data.'" — a reusable empty state that always pairs
    a plain-language explanation with a next action.
    Usage: <x-empty-state message="You haven't added any members yet." action-label="Add Member" :action-url="route('members.index')" />
--}}
<div class="empty-state">
    <p>{{ $message }}</p>
    @isset($actionLabel)
        <a href="{{ $actionUrl ?? '#' }}">{{ $actionLabel }}</a>
    @endisset
</div>
