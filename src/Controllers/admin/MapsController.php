<?php

namespace App\Http\Controllers\admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Map;
class MapsController extends Controller
{
    private $map;
    public function __construct()
    {
        $this->middleware('auth');
        $this->map = Map::first();
    }
    public function index()
    {
        return view('admin.maps.index',['map'=>$this->map]);
    }

    public function update(Request $request)
    {
        $this->validate($request,[
            'latitude'  => 'required|numeric',
            'longitude'  => 'required|numeric',
            'company'  => 'required',
            'region'  => 'required',
            'city'  => 'required',
            'address'  => 'required',
        ]);

        $this->map->latitude = $request->latitude;
        $this->map->longitude = $request->longitude;
        $this->map->company = $request->company;
        $this->map->region = $request->region;
        $this->map->city = $request->city;
        $this->map->address = $request->address;
        $this->map->save();

        return redirect('admin/maps');
    }
}
