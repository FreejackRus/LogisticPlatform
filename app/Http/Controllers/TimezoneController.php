<?php

namespace App\Http\Controllers;

use App\Http\Resources\TimezoneResource;
use App\Models\Timezone;
use Illuminate\Http\Request;

class TimezoneController extends Controller
{
    public function search(Request $request)
    {
        $query = Timezone::query()->orderBy('name');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return TimezoneResource::collection($query->get());
    }
}
