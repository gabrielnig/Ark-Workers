<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    /**
     * Public and unauthenticated on purpose, the sign-up form needs
     * this list before the worker has any account at all.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
