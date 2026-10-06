@props(['href'])

{{--
    The one back control for the whole app.

    Sits at the foot of the page on purpose: back is what you reach for once
    you are done reading, so it belongs after the content rather than above it,
    where it used to push the heading down and compete with the page's real
    actions.

    Styling lives in the layout (.page-back / .btn-back) rather than here, so
    every screen changes together and nothing has to be restyled twice.

    Label defaults to "Back"; pass a slot for something more specific, e.g.
    <x-back-link :href="route('machines.index')">Back to Machines</x-back-link>
--}}
<div class="page-back">
    <a href="{{ $href }}" class="btn-back">
        <i class="fas fa-arrow-left"></i>
        {{ $slot->isNotEmpty() ? $slot : 'Back' }}
    </a>
</div>
