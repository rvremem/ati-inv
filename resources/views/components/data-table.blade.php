@props(['headers', 'rows', 'actions' => true])

<div>
    <table class="table mb-0" style="width:100%">
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
                @if ($actions)
                    <th style="width:150px;">Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
