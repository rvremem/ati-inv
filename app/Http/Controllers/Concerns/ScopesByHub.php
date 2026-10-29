<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

/**
 * Limits what a controller shows to the signed-in user's hub.
 *
 * The rule lives on User::hubScope(): administrative roles get null (no
 * limit), everyone else gets their hub id, and a restricted user with no hub
 * set gets 0 — an id nothing matches — so an unconfigured account sees nothing
 * rather than everything.
 *
 * Every screen goes through this one helper so the roles that bypass scoping
 * are defined in a single place. Scattering `if ($user->isSA())` through the
 * controllers is how one screen ends up leaking a branch the others hide.
 */
trait ScopesByHub
{
    /** Null means unrestricted; an int is the only hub this user may see. */
    protected function hubScope(): ?int
    {
        return auth()->user()?->hubScope();
    }

    protected function seesAllHubs(): bool
    {
        return (bool) auth()->user()?->seesAllHubs();
    }

    /**
     * Applies the hub limit to a query, when there is one to apply.
     *
     * $column is qualified by the caller because most of these queries join,
     * and an unqualified `hub_id` is ambiguous the moment two tables have one.
     */
    protected function applyHubScope($query, string $column = 'hub_id')
    {
        $hubId = $this->hubScope();

        if ($hubId !== null) {
            $query->where($column, $hubId);
        }

        return $query;
    }

    /**
     * Applies the hub limit to a table that has no hub of its own.
     *
     * part_requests is the case this exists for: it carries no hub_id, so a
     * request belongs to the hub of whoever raised it. Kept here rather than
     * repeated inline so Stock Levels, Parts Location, the dashboard and the
     * reports all attribute a request the same way.
     */
    protected function applyRequesterHubScope($query, string $column = 'requested_by')
    {
        $hubId = $this->hubScope();

        if ($hubId !== null) {
            $query->whereIn($column, User::where('hub_id', $hubId)->select('id'));
        }

        return $query;
    }

    /**
     * The hub a report has been asked to narrow to, or null for no narrowing.
     *
     * This is a convenience on top of the enforced scope, never a way around
     * it: a restricted user asking for a branch that is not theirs gets the
     * filter ignored, and the global scopes still confine them to their own.
     * So the worst a tampered hub_id can do is show the user what they were
     * already allowed to see.
     *
     * Validated as a strict integer rather than cast, for the same reason as
     * Stock Levels: a cast would turn "1 OR 1=1" into hub 1 and quietly serve
     * that branch's figures.
     */
    protected function hubFilterId($request): ?int
    {
        $requested = filter_var($request->input('hub_id'), FILTER_VALIDATE_INT);

        if (! $requested || $requested <= 0) {
            return null;
        }

        $allowed = $this->hubScope();

        if ($allowed !== null && $requested !== $allowed) {
            return null;
        }

        return $requested;
    }

    /**
     * The catalogue as it should appear on a stock-facing form.
     *
     * Item Parts itself stays global — a part number is a definition, not
     * stock — but a request form that lists parts which have never been in
     * your branch invites requests nobody can fill, and was how a Cebu
     * engineer came to be offered Manila's parts.
     *
     * "In this hub" means the same thing here as on Stock Levels: receiving
     * against that hub, or a request raised by someone in it.
     */
    protected function partsInHubScope()
    {
        $query = \App\Models\ItemPart::query();
        $hubId = $this->hubScope();

        if ($hubId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($hubId) {
            $q->whereExists(fn ($sub) => $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('receiving')
                    ->whereColumn('receiving.item_part_id', 'item_parts.id')
                    ->where('receiving.hub_id', $hubId))
              ->orWhereExists(fn ($sub) => $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('part_requests')
                    ->join('users', 'users.id', '=', 'part_requests.requested_by')
                    ->whereColumn('part_requests.item_part_id', 'item_parts.id')
                    ->where('users.hub_id', $hubId));
        });
    }

    /**
     * Units of a part this user's hub can still request: received stock less
     * what is already issued or waiting to be.
     *
     * Pending requests are subtracted as well as Out ones. They have not left
     * the shelf, but they are spoken for — counting only Out let two people
     * each request the same five units and both be accepted.
     *
     * Shared by the Request Parts form's "available" figure and the check on
     * save, so what the form shows and what the server accepts cannot differ.
     */
    protected function requestableQuantity(int $itemPartId): int
    {
        $hubId = $this->hubScope();

        $received = \Illuminate\Support\Facades\DB::table('receiving')
            ->where('item_part_id', $itemPartId)
            ->whereIn('status', \App\Http\Controllers\InventoryController::ON_HAND_STATUSES)
            ->when($hubId !== null, fn ($q) => $q->where('hub_id', $hubId))
            ->sum('quantity');

        $claimed = \Illuminate\Support\Facades\DB::table('part_requests')
            ->join('users', 'users.id', '=', 'part_requests.requested_by')
            ->where('part_requests.item_part_id', $itemPartId)
            ->whereIn('part_requests.status', ['Pending', 'Out'])
            ->when($hubId !== null, fn ($q) => $q->where('users.hub_id', $hubId))
            ->sum('part_requests.quantity');

        return max(0, (int) $received - (int) $claimed);
    }

    /**
     * Serials of a part this user's hub can still request: received into the
     * hub, and whose latest movement leaves them on the shelf. A serial whose
     * newest row is a Pending or Out request is spoken for; a Denied request
     * is ignored, so the serial is offered again.
     *
     * The serial counterpart of requestableQuantity(), and shared the same
     * way: the Request Parts dropdown lists these, and the save refuses any
     * serial not among them.
     *
     * @return array<int,string>
     */
    protected function requestableSerials(int $itemPartId): array
    {
        $hubId = $this->hubScope();
        $hubClause = $hubId === null ? '' : ' AND hub_id = ?';
        $avail = "'" . implode("','", \App\Http\Controllers\InventoryController::ON_HAND_STATUSES) . "'";

        $serials = \Illuminate\Support\Facades\DB::select("
            WITH combined AS (
                SELECT serial_no, status, created_at
                FROM receiving
                WHERE item_part_id = ? AND serial_no IS NOT NULL{$hubClause}
                UNION ALL
                SELECT serial_no, status, created_at
                FROM part_requests
                WHERE serial_no IS NOT NULL AND status != 'Denied'
            ),
            ranked AS (
                SELECT *,
                       ROW_NUMBER() OVER (PARTITION BY serial_no ORDER BY created_at DESC) as rn
                FROM combined
            )
            SELECT serial_no
            FROM ranked
            WHERE rn = 1 AND status IN ($avail)
        ", $hubId === null ? [$itemPartId] : [$itemPartId, $hubId]);

        return array_map(fn ($s) => $s->serial_no, $serials);
    }

    /**
     * Refuses a record that belongs to another hub.
     *
     * List filtering alone is not access control — the detail and edit routes
     * take an id straight from the URL, so without this a restricted user can
     * still read another branch's machine by typing its id.
     */
    protected function authorizeHubAccess($model, string $attribute = 'hub_id'): void
    {
        $hubId = $this->hubScope();

        if ($hubId === null) {
            return;
        }

        abort_unless((int) ($model->{$attribute} ?? 0) === $hubId, 403,
            'That record belongs to another hub.');
    }

    /** Hubs the user may choose between on a filter or form. */
    protected function selectableHubs()
    {
        $query = \App\Models\Hub::orderBy('hub_name');
        $hubId = $this->hubScope();

        if ($hubId !== null) {
            $query->whereKey($hubId);
        }

        return $query->get();
    }
}
