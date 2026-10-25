<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Go back to the previous page with a toast message.
     *
     * @param  'success'|'info'|'warning'|'error'  $type
     */
    protected function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
