<?php

namespace App\Http\Middleware;

use App\Models\Outlet;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolve outlet scope for every request.
 *
 * Rules:
 * - Cashier/manager with outlet_id: locked to that outlet (ignore any requested outlet id)
 * - Admin (outlet_id null): can select outlet via header X-Outlet-Id
 *   - missing/"ALL" => scope null (All outlets)
 *   - ULID => validated outlet id
 */
class ResolveOutletScope
{
    public const HEADER = 'X-Outlet-Id';
    public const ALL = 'ALL';

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $lockedOutletId = $user->outlet_id;
        if (!empty($lockedOutletId)) {
            $request->attributes->set('outlet_scope_id', $lockedOutletId);
            $request->attributes->set('outlet_scope_locked', true);
            $request->attributes->set('outlet_scope_mode', 'ONE');
            return $next($request);
        }

        // Admin/manager without outlet_id => can pick scope
        $raw = $request->header(self::HEADER);
        $raw = is_string($raw) ? trim($raw) : null;

        if ($raw === null || $raw === '' || strtoupper($raw) === self::ALL) {
            $request->attributes->set('outlet_scope_id', null);
            $request->attributes->set('outlet_scope_locked', false);
            $request->attributes->set('outlet_scope_mode', 'ALL');
            return $next($request);
        }

        $exists = Outlet::query()->whereKey($raw)->exists();
        if (!$exists) {
            return response()->json([
                'message' => 'Invalid outlet scope',
                'errors' => [
                    'outlet_id' => ['Outlet not found.'],
                ],
            ], 422);
        }

        $request->attributes->set('outlet_scope_id', $raw);
        $request->attributes->set('outlet_scope_locked', false);
        $request->attributes->set('outlet_scope_mode', 'ONE');

        return $next($request);
    }
}
