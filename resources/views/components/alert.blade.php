{{-- Messages flashed by the server, shown top-right through notify() — see
     partials/notify, which the layout loads. --}}
<script>
@if ($message = Session::get('success'))
    notify(@json($message), 'success');
@endif

@if ($message = Session::get('error'))
    notify(@json($message), 'error');
@endif

@if ($message = Session::get('warning'))
    notify(@json($message), 'warning');
@endif

@if ($message = Session::get('info'))
    notify(@json($message), 'info');
@endif

@if ($errors->any())
    notify(@json($errors->all()), 'error', 'Validation Errors:');
@endif
</script>
