<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Laravel 11+ ships a deliberately bare base Controller; the package was
    // written against Laravel 10 where these traits were included by default.
    // MemberController/FamilyController/etc. call $this->authorize(), so the
    // traits must be pulled in explicitly.
    use AuthorizesRequests, ValidatesRequests;
}
