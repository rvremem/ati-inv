@php
    // A literal pipe or line break in a value would split the markdown cell.
    $cell = fn ($value) => str_replace(['|', "\r", "\n"], ['\|', ' ', ' '], (string) $value);
@endphp
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
