@php
    // A literal pipe or line break in a value would split the markdown cell.
    $cell = fn ($value) => str_replace(['|', "\r", "\n"], ['\|', ' ', ' '], (string) $value);
@endphp
<x-mail::message>
# Hello,

**{{ $requester->name }}** submitted a {{ $type }} request that needs approval.

@foreach ($details as $label => $value)
**{{ $label }}:** {{ $value }}<br>
@endforeach

<x-mail::table>
| {{ implode(' | ', $headers) }} |
|{{ str_repeat(' --- |', count($headers)) }}
@foreach ($rows as $row)
| {!! implode(' | ', array_map(fn ($v) => $cell(e($v)), $row)) !!} |
@endforeach
</x-mail::table>

<x-mail::button :url="$url">
Review Pending Approvals
</x-mail::button>

{{-- The app clock is UTC; readers are in the Philippines. --}}
Submitted {{ now()->timezone('Asia/Manila')->format('M d, Y h:i A') }}.

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
