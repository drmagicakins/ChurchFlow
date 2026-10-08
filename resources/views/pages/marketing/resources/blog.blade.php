{{--
 | /resources/blog
 |
 | There are no published posts yet, so this renders an honest empty state rather
 | than three fabricated articles with invented dates and authors. Fake content is
 | the fastest way to lose trust on a first visit, and a blog with three obviously
 | placeholder posts reads as an abandoned project.
 |
 | The `$posts` array is the seam: when real posts exist — from a model, a CMS or
 | markdown on disk — populate it and the list renders with no other change.
--}}

@php
    // Empty on purpose. Populate with real posts; do not invent them.
    $posts = [];

    $topics = [
        'Church administration',
        'Finance and giving',
        'Attendance and follow-up',
        'Communication',
        'Multi-branch structure',
        'Subvention and governance',
    ];
@endphp

<x-marketing-layout title="Blog — ChurchFlow"
    description="Notes on church administration, giving, attendance, communication and running a multi-branch church well.">
    <x-marketing.page-header eyebrow="Blog" title="Notes on running" accent="a church well."
        lede="Practical writing on administration, finance, attendance and communication — the parts of church life that software can carry." />

    @if (empty($posts))
        <section class="mk-section" id="posts">
            <div class="mk-shell">
                <div class="mk-empty" data-reveal>
                    <span class="mk-empty__icon">
                        <x-ui.icon name="doc" class="h-6 w-6" />
                    </span>
                    <h2 class="mk-h3">No articles published yet</h2>
                    <p class="mk-empty__text">
                        We have not published anything here instead of filling the page with
                        placeholder articles. In the meantime, the Help Center and Guides cover
                        the practical ground.
                    </p>
                    <div class="mk-empty__actions">
                        <a href="{{ route('resources.help-center') }}" class="mk-btn mk-btn--primary">
                            Visit the Help Center
                        </a>
                        <a href="{{ route('resources.guides') }}" class="mk-btn mk-btn--secondary">
                            Browse guides
                        </a>
                    </div>
                </div>
            </div>
        </section>
    @else
        <section class="mk-section" id="posts">
            <div class="mk-shell">
                <div class="mk-posts">
                    @foreach ($posts as $post)
                        <article class="mk-card mk-card--interactive mk-post" data-reveal>
                            <p class="mk-post__meta">{{ $post['date'] }} &middot; {{ $post['category'] }}</p>
                            <h2 class="mk-post__title">
                                <a href="{{ $post['url'] }}">{{ $post['title'] }}</a>
                            </h2>
                            <p class="mk-post__excerpt">{{ $post['excerpt'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="mk-section mk-section--tint" id="topics">
        <div class="mk-shell">
            <div class="mk-head mk-head--center" data-reveal>
                <p class="mk-eyebrow" style="justify-content: center">Topics</p>
                <h2 class="mk-h2" style="margin-top: 0.85rem">What we write about</h2>
            </div>
            <div class="mk-topics" data-reveal>
                @foreach ($topics as $topic)
                    <span class="mk-badge">{{ $topic }}</span>
                @endforeach
            </div>
        </div>
    </section>
</x-marketing-layout>
