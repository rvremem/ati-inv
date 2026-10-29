<x-mail::message>
# Hello {{ $requester?->name ?? '' }},

Your {{ $type }} request was **declined** by **{{ $decliner->name }}**.

<x-mail::panel>
**Reason:** {{ $reason }}
</x-mail::panel>

@include('emails.partials.request-table')

<x-mail::button :url="$url">
View My Requests
</x-mail::button>

{{-- The app clock is UTC; readers are in the Philippines. --}}
Declined {{ now()->timezone('Asia/Manila')->format('M d, Y h:i A') }}.

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
