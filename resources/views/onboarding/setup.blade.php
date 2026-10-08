<x-layout>
    <h1>Let's get your church ready.</h1>
    <p>You're {{ $percent }}% complete.</p>
    <ul>
    @foreach($progress as $step)
        <li>
            {{ $step['completed'] ? '●' : '○' }} {{ $step['label'] }}
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
