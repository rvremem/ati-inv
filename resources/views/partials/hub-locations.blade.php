{{--
    Narrows the Location dropdowns to the hub chosen on the same form.

    A shelf exists at one site, so receiving into Cebu must not offer Manila's
    bins. The whole list is sent to the page once and filtered here rather than
    fetched per hub — there are few enough locations that a round trip on every
    hub change would be slower and add a failure mode for no benefit.

    Expects $locations in scope (with hub_id) and a select with id="hub_id".
--}}
<script>
const locationsData = @json($locations);

const locationsForHub = (hubId) =>
    hubId ? locationsData.filter(l => String(l.hub_id) === String(hubId)) : [];

/**
 * Options for one Location select. The empty-state text says which of the two
 * reasons applies, because "no options" with no explanation reads as a bug.
 */
function locationOptionsHtml(hubId, selected = '') {
    if (!hubId) {
        return '<option value="">-- Select a hub first --</option>';
    }

    const list = locationsForHub(hubId);

    if (!list.length) {
        return '<option value="">-- No locations in this hub --</option>';
    }

    return '<option value="">-- Select Location --</option>' + list.map(l =>
        `<option value="${l.id}"${String(l.id) === String(selected) ? ' selected' : ''}>${l.code}</option>`
    ).join('');
}

/**
 * Repaints every Location dropdown on the page for the chosen hub.
 *
 * The current choice is kept when it still belongs to that hub and dropped when
 * it does not — leaving a Manila shelf selected under Cebu would submit a
 * location the branch does not have.
 */
function refreshLocationSelects() {
    const hubId = document.getElementById('hub_id')?.value || '';

    document.querySelectorAll('select[name="location_id"], select[name="location_id[]"]').forEach(sel => {
        const keep = sel.value;
        sel.innerHTML = locationOptionsHtml(hubId, keep);

        // select2 renders its own box from the options, so it has to be told
        // the list underneath it changed.
        if (window.jQuery && $(sel).hasClass('select2-hidden-accessible')) {
            $(sel).trigger('change.select2');
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const hub = document.getElementById('hub_id');

    if (!hub) {
        return;
    }

    hub.addEventListener('change', refreshLocationSelects);
    $(hub).on('change', refreshLocationSelects);   // select2 fires jQuery events

    // Also on load, so a form bounced back by validation reopens consistent.
    refreshLocationSelects();
});
</script>
