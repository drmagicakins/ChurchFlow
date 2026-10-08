{{--
 | Marketing home page.
 |
 | Section order follows the reference: nav, hero, trust, challenge, solution,
 | how it works, modules, product explorer, pricing, testimonials, FAQ, final CTA,
 | footer.
 |
 | Two additions to the reference order, both earning their place:
 |  - <x-marketing.trust-strip /> sits immediately under the hero, where a
 |    visitor's first question ("is this real?") is answered before they scroll.
 |  - <x-marketing.faq /> sits after pricing, because its questions are the ones
 |    the pricing rules create.
 |
 | Everything here is a component; this file is the table of contents, not the
 | page. That is what keeps the page editable without reading 1,500 lines of markup.
--}}

<x-marketing-layout
    :title="'ChurchFlow — The Operating Platform for Modern Churches'"
    :description="'ChurchFlow is an all-in-one church management platform for members, finance, events, attendance, communication, pastoral care, subvention and reporting. Manage. Connect. Grow.'"
>
    <x-marketing.hero />
    <x-marketing.trust-strip />
    <x-marketing.challenge />
    <x-marketing.solution />
    <x-marketing.how-it-works />
    <x-marketing.modules />
    <x-marketing.product-showcase />
    <x-marketing.pricing />
    <x-marketing.testimonials />
    <x-marketing.faq />
    <x-marketing.final-cta />
</x-marketing-layout>
