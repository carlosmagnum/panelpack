<?php

namespace Decoweb\Panelpack\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Decoweb\Panelpack\Models\SysCoreSetup as Table;
use Decoweb\Panelpack\Models\Image as Poza;
use Image;
use Illuminate\Support\Facades\Storage;

class ImagesController extends Controller
{

    public function __construct()
    {
        $this->middleware('web');
        $this->middleware('auth');
    }

    /**
     * Displays a page for adding a new image for a specified table record
     *
     * @param $tabela
     * @param $recordId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function create($tabela, $recordId)
    {
        $recordId = (int)$recordId;
        $table = Table::table($tabela);

        if( $table === false || $recordId == 0){
            return redirect()->back();
        }

        $settings = unserialize($table->settings);
        if((int)$settings['config']['functionImages'] != 1){
            return redirect()->back();
        }

        $modelName = $table->model;
        $model = '\App\\'.$modelName;
        $record = $model::find($recordId);

        //dd($record);
        if(null == $record){
            return redirect()->back();
        }
        $poze = Poza::where('table_id', $table->id)
            ->where('record_id',$recordId)->orderBy('ordine','asc')->get();

        $imagesMax = (int)$settings['config']['imagesMax'];
        $name = $settings['config']['displayedName'];
        $pageName =  $settings['config']['pageName'];
        $noPics = $settings['messages']['no_images'];
        return view('decoweb::admin.images.create',[
            'imagesMax' => $imagesMax,
            'record'    => $record,
            'name'      => $name,
            'tabela'    => $tabela,
            'idTabela'  => $table->id,
            'pageName'  => $pageName,
            'poze'      => $poze,
            'noImages'  => $noPics,
        ]);
    }

    /**
     * Stores an images for a specified table record
     *
     * @param Request $request
     * @param $tabela
     * @param $recordId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request, $tabela, $recordId)
    {
        $this->validate($request,[
            'description'   => 'nullable|max:55',
            'pic'           => 'required|image'
        ]);

        $table = Table::table($tabela);
        # Check 1 - if table exists
        if($table === false){
            $request->session()->flash('mesaj','Acesta tabela nu exista.');
            return redirect()->back();
        }

        # Check 2 - if record exists in the table
        $modelName = $table->model;
        $model = '\App\\'.$modelName;
        $record = $model::find((int)$recordId);

        if(null == $record){
            $request->session()->flash('mesaj','Acesta inregistrare nu exista.');
            return redirect()->back();
        }

        # Check 3 - if record accepts images, and how many($imagesMax)
        $settings = unserialize($table->settings);
        if((int)$settings['config']['functionImages'] != 1){
            $request->session()->flash('mesaj','Acesta inregistrare nu accepta imagini.');
            return redirect('admin/core/'.$tabela.'/addPic/'.$recordId);
        }else{
            $imagesMax = (int)$settings['config']['imagesMax'];
        }

        //Check 4 - compare number of pics in Images for the record with $imagesMax
        $ordine = Poza::where('table_id', $table->id)->where('record_id',$recordId)->max('ordine');
        $nrPoze = Poza::where('table_id', $table->id)->where('record_id',$recordId)->count();


        if($imagesMax == (int)$nrPoze){
            $request->session()->flash('mesaj',"Numarul maxim de poze a fost deja atins ($nrPoze).");
            return redirect('admin/core/'.$tabela.'/addPic/'.$recordId);
        }

        //Store picture info in Images table
        $time = strval(time());
        $picName = $tabela.'_ID'.$table->id.'_'.$recordId.'_'.$time.'.'.$request->file('pic')->getClientOriginalExtension();

        $pic = new Poza();
        $pic->table_id = $table->id;
        $pic->record_id = (int)$recordId;
        $pic->ordine = ++$ordine;
        $pic->name = $picName;
        $pic->description = (empty($request->description))?null:$request->description;
        $pic->save();

        // Store file on disk
        $this->resizeAndStore($request->file('pic'),$picName,$table->table_name);

        $request->session()->flash('mesaj','Poza a fost adugata.');
        return redirect('admin/core/'.$tabela.'/addPic/'.$recordId);
    }

    private function resizeAndStore( $pic, $picName, $tableName )
    {
        list($width, $height, $compression) = config('imagesize.'.$tableName);
        
        $img = Image::make($pic);
        if( !is_null($width) ){
            $img->fit($width, $height, function ($constraint) {
                $constraint->upsize();
            });
        }elseif( $img->height() > $height ){
            $img->resize(null, $height, function ($constraint) {
                $constraint->aspectRatio();
            });
        }
        $img->save(storage_path('app/uploads/'.$picName), $compression);
        $img->resize(null,90,function ($constraint) {
            $constraint->aspectRatio();
        })->save(storage_path('app/uploads/thumb_'.$picName), 75);
    }

    /**
     * Deletes an image
     *
     * @param $picId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function delete($picId)
    {
        $picId = (int)trim($picId);
        $pic = Poza::find($picId);

        if(null == $pic){
            return redirect()->back();
        }

        $tableName = $pic->table->table_name;
        $recordId = $pic->record_id;

        Storage::disk('uploads')->delete($pic->name);
        Storage::disk('uploads')->delete('thumb_'.$pic->name);
        $pic->delete();

        return redirect('admin/core/'.$tableName.'/addPic/'.$recordId);
    }

    /**
     * Updates the order and the description for an image
     *
     * @param Request $request
     * @param $tableId
     * @param $recordId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request, $tableId, $recordId)
    {
        if( !ctype_digit($tableId) || !ctype_digit($recordId) ){
            return redirect('/');
        }
        $tableId = (int)$tableId;
        $recordId = (int)$recordId;

        $recordPics = Poza::where('table_id', $tableId)->where('record_id', $recordId)->orderBy('ordine')->get();

        if ( null === $recordPics ){
            return redirect()->back();
        }

        foreach($recordPics as $recordPic){
            $ordine = 'ordine_'.$recordPic->id;
            $description = 'description_'.$recordPic->id;
            if( $request->has($ordine) ) {
                if(strcmp($recordPic->description, $request->$description) !== 0 || $recordPic->ordine != $request->$ordine){

                    if (ctype_digit((string)trim($request->$ordine)) !== true) {
                        continue;
                    }

                    $this->validate($request,[
                        $description => 'nullable|max:55'
                    ]);

                    $recordPic->description = (empty($request->$description))?null:$request->$description;
                    $recordPic->ordine = $request->$ordine;
                    $recordPic->save();
                }
            }
        }

        return redirect()->back();
    }
}
