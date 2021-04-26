<?php


namespace Decoweb\Panelpack\Controllers\Admin;

use App\Http\Controllers\Controller;

class HelpController extends Controller
{

    public function __construct()
    {
        $this->middleware('web');
        $this->middleware('auth');
    }

    public function __invoke()
    {
        return view('decoweb::admin.help');
    }
}
