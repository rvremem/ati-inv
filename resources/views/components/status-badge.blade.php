@props(['status'])

@php
    $map = [
        // Green — positive/complete states
        'Active'      => ['bg' => '#d1fae5', 'color' => '#065f46'],
        'Received'    => ['bg' => '#d1fae5', 'color' => '#065f46'],
        'Issued'      => ['bg' => '#d1fae5', 'color' => '#065f46'],
        'Delivered'   => ['bg' => '#d1fae5', 'color' => '#065f46'],
        'Available'   => ['bg' => '#d1fae5', 'color' => '#065f46'],
        'In Stock'    => ['bg' => '#d1fae5', 'color' => '#065f46'],

        // Blue — in-progress states
        'Receiving'   => ['bg' => '#dbeafe', 'color' => '#1e40af'],
        'Approved'    => ['bg' => '#dbeafe', 'color' => '#1e40af'],
        'Out'         => ['bg' => '#dbeafe', 'color' => '#1e40af'],
        'Assembly'    => ['bg' => '#dbeafe', 'color' => '#1e40af'],

        // Yellow — pending/waiting states
        'Pending'     => ['bg' => '#fef3c7', 'color' => '#92400e'],

        // Orange — return/reversal states
        'Return'      => ['bg' => '#ffedd5', 'color' => '#9a3412'],
        'Returned'    => ['bg' => '#ffedd5', 'color' => '#9a3412'],

        // Red — negative/error states
        'Cancelled'   => ['bg' => '#fee2e2', 'color' => '#991b1b'],
        'Defective'   => ['bg' => '#fee2e2', 'color' => '#991b1b'],
        'Unavailable' => ['bg' => '#fee2e2', 'color' => '#991b1b'],

        // Gray — neutral/inactive
        'Inactive'    => ['bg' => '#f1f5f9', 'color' => '#64748b'],
    ];
    $style = $map[$status] ?? ['bg' => '#f1f5f9', 'color' => '#475569'];
@endphp

<span style="background:{{ $style['bg'] }}; color:{{ $style['color'] }}; font-size:0.72rem; font-weight:600; padding:3px 10px; border-radius:20px; display:inline-block; white-space:nowrap;">
    {{ $status }}
</span>
