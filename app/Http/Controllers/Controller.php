<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Give a typed website a scheme ("club.ph" -> "https://club.ph") so it can be validated as an http(s) link.
     * Other schemes (javascript:, data:, ...) are left alone and then fail validation.
     */
    protected function normalizeWebsite(Request $request, string $field = 'website'): void
    {
        $value = trim((string) $request->input($field));

        if ($value !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*:#i', $value)) {
            $value = 'https://'.ltrim($value, '/');
        }

        $request->merge([$field => $value === '' ? null : $value]);
    }
}
