<?php

namespace App\Http\Controllers\hrm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmployeeSessionController extends Controller
{
    public function attendance_by_employee(Request $request, $id)
    {
        return response()->json(['success' => true]);
    }
}
