{{-- Messages flashed by the server, shown top-right through notify() — see
     partials/notify, which the layout loads. --}}
@php
    // Blade's json directive prints nothing when the text is not valid UTF-8, leaving
    // notify(, ...) behind, which the browser drops as a syntax error. A bad
    // byte becomes a � instead, so the message still shows.
    $js = fn ($value) => json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
@endphp
<script>
@if ($message = Session::get('success'))
    notify({!! $js($message) !!}, 'success');
@endif

@if ($message = Session::get('error'))
    notify({!! $js($message) !!}, 'error');
@endif

@if ($message = Session::get('warning'))
    notify({!! $js($message) !!}, 'warning');
@endif

@if ($message = Session::get('info'))
    notify({!! $js($message) !!}, 'info');
@endif

@if ($errors->any())
    notify({!! $js($errors->all()) !!}, 'error', 'Validation Errors:');
@endif
</script>
