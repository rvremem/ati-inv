@props(['width' => 'col-md-3'])

{{--
    Hub filter for the report screens.

    Resolves its own options rather than being handed them, so adding it to a
    report is one tag and no controller change — with ten reports carrying it,
    a version that needed data passed in would eventually be added to a screen
    that forgot to pass it, and render an empty dropdown.

    Renders nothing at all for a user who can only see one hub: the control
    would have a single option and change nothing, and the enforced scope
    already confines them. Administrators, who see every branch, get the filter.

    Selecting a hub only ever narrows. ScopesByHub::hubFilterId() ignores a hub
    the reader is not entitled to, so this cannot be used to reach one.
--}}
@php
    $hubScope = auth()->user()?->hubScope();

    $hubFilterOptions = \App\Models\Hub::orderBy('hub_name')
        ->when($hubScope !== null, fn ($q) => $q->whereKey($hubScope))
        ->get();
@endphp

@if ($hubFilterOptions->count() > 1)
    <div class="{{ $width }}">
        <label class="form-label" for="hub_id">Hub</label>
        <select name="hub_id" id="hub_id" class="form-control no-select2">
            <option value="">-- All hubs --</option>
            @foreach ($hubFilterOptions as $hubOption)
                <option value="{{ $hubOption->id }}" @selected(request('hub_id') == $hubOption->id)>
                    {{ $hubOption->hub_name }}
                </option>
            @endforeach
        </select>
    </div>
@endif
