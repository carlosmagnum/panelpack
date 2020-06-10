<?php

namespace Decoweb\Panelpack\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Sitemap;
use Decoweb\Panelpack\Models\Sitemap as Map;
use Illuminate\Support\Facades\Storage;
class SitemapController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
        $this->middleware('auth');
    }
    public function index()
    {
        $last = Map::first();
        //dd($last->updated_at);
        return view('decoweb::admin.sitemap.index',[
            'last' => $last->updated_at
        ]);
    }

    public function regenerate(Request $request)
    {
        $sitemap = Map::find(1);
        $sitemap->updated_at = Carbon::now();
        $sitemap->save();
        if($request->exists('regenerate')){

            Sitemap::addSitemap(url('/'));
            Sitemap::addTag(url('/'), Carbon::now(), 'daily', '0.8');
            Sitemap::addTag(url('/contact'), Carbon::now(), 'daily', '0.8');
            Storage::disk('www')->put('sitemap.xml', Sitemap::xml() );

        }
        return redirect('admin/sitemap')->with('mesaj','Regenerare reusita!');
    }
}