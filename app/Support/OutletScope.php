<?php

namespace App\Support;

use Illuminate\Http\Request;

class OutletScope
{
    public static function id(?Request $request = null): ?string
    {
        $request ??= request();
        $id = $request->attributes->get('outlet_scope_id');
        return is_string($id) ? $id : null;
    }

    public static function isAll(?Request $request = null): bool
    {
        $request ??= request();
        return ($request->attributes->get('outlet_scope_mode') === 'ALL');
    }

    public static function isLocked(?Request $request = null): bool
    {
        $request ??= request();
        return (bool) $request->attributes->get('outlet_scope_locked', false);
    }
}
