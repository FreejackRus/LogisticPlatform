<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function disclaimer(): Response
    {
        return Inertia::render('Freight/Legal', [
            'title' => 'Правовой дисклеймер',
            'disclaimer' => config('freight.legal_disclaimer'),
        ]);
    }

    public function terms(): Response
    {
        return Inertia::render('Freight/Legal', [
            'title' => 'Условия использования',
            'disclaimer' => config('freight.legal_disclaimer'),
        ]);
    }
}
