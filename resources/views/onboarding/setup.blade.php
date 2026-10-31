<x-layout>
    <h1>Let's get your church ready.</h1>
    <p>You're {{ $percent }}% complete.</p>
    <ul>
    @foreach($progress as $step)
        <li>
            {{ $step['completed'] ? '●' : '○' }} {{ $step['label'] }}
            @if($step['key'] === 'church_profile' && ! $step['completed'])
                {{-- §11/§34: the ONE required input this step actually needs.
                     Without this, "Mark complete" recorded the step while the
                     church kept the placeholder name from activation. --}}
                <form method="POST" action="{{ route('setup.profile') }}" style="display:inline">@csrf
                    <label for="setup-church-name">Church name</label>
                    <input id="setup-church-name" type="text" name="name"
                           value="{{ old('name', auth()->user()->church?->name) }}"
                           required minlength="2" maxlength="120">
                    <button>Save name</button>
                </form>
            @endif
            @unless($step['completed'])
                <form method="POST" action="{{ route('setup.complete') }}" style="display:inline">@csrf
                    <input type="hidden" name="step" value="{{ $step['key'] }}">
                    <button>Mark complete</button>
                </form>
                @unless($step['required'])
                    <form method="POST" action="{{ route('setup.skip') }}" style="display:inline">@csrf
                        <input type="hidden" name="step" value="{{ $step['key'] }}">
                        <button>Skip for now</button>
                    </form>
                @endunless
            @endunless
        </li>
    @endforeach
    </ul>
</x-layout>
