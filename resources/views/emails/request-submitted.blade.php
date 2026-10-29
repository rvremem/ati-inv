<x-mail::message>
# Hello,

**{{ $requester->name }}** submitted a {{ $type }} request that needs approval.

@include('emails.partials.request-table')

<x-mail::button :url="$url">
Review Pending Approvals
</x-mail::button>

{{-- The app clock is UTC; readers are in the Philippines. --}}
Submitted {{ now()->timezone('Asia/Manila')->format('M d, Y h:i A') }}.

Regards,<br>
{{ config('app.name') }}
</x-mail::message>
