<?php
namespace Decoweb\Panelpack\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Decoweb\Panelpack\Models\SysCoreSetup as Table;
use Decoweb\Panelpack\Models\File;
use Illuminate\Support\Facades\Storage;
class FilesController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
        $this->middleware('auth');
    }

    /**
     * Returns table info(collection) form SysCoreSetup or FALSE if table not found
     *
     * @param $tabela
     * @return bool
     */
    private function tableGet($tabela)
    {
        $tabela = (string)trim($tabela);

        if( !ctype_alpha($tabela)){
            return false;
        }

        $table = Table::where('table_name', $tabela)->first();

        if (null == $table){
            return false;
        }

        return $table;
    }

    /**
     * Displays a page for adding a new file for a specified table record
     *
     * @param $tabela
     * @param $recordId
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function create($tabela, $recordId)
    {
        $recordId = (int)$recordId;
        $table = $this->tableGet($tabela);

        if( $table === false || $recordId == 0){
            return redirect()->back();
        }

        $settings = unserialize($table->settings);
        if((int)$settings['config']['functionFile'] != 1){
            return redirect()->back();
        }

        $modelName = $table->model;
        $model = '\App\\'.$modelName;
        $record = $model::find($recordId);

        //dd($record);
        if(null == $record){
            return redirect()->back();
        }
        $files = File::where('table_id', $table->id)
            ->where('record_id',$recordId)->orderBy('ordine','asc')->get();

        $filesMax = (int)$settings['config']['filesMax'];
        $name = $settings['config']['displayedName'];
        $pageName =  $settings['config']['pageName'];
        $noFiles = $settings['messages']['no_files'];
        return view('decoweb::admin.files.create',[
            'filesMax'  => $filesMax,
            'record'    => $record,
            'name'      => $name,
            'tabela'    => $tabela,
            'idTabela'  => $table->id,
            'pageName'  => $pageName,
            'files'     => $files,
            'noFiles'   => $noFiles,
        ]);
    }

    /**
     * Stores a file for a specified table record
     *
     * @param Request $request
     * @param $tabela
     * @param $recordId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request, $tabela, $recordId)
    {
        $this->validate($request,[
            'title'   => 'required|max:100',
            'file'           => 'required|file|mimetypes:application/pdf',
        ]);

        $table = $this->tableGet($tabela);
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

        # Check 3 - if record accepts files, and how many($filesMax)
        $settings = unserialize($table->settings);
        if((int)$settings['config']['functionFile'] != 1){
            $request->session()->flash('mesaj','Acesta inregistrare nu accepta fisiere.');
            return redirect('admin/core/'.$tabela.'/addFile/'.$recordId);
        }else{
            $filesMax = (int)$settings['config']['filesMax'];
        }

        //Check 4 - compare number of files in Files for the record with $filesMax
        $ordine = File::where('table_id', $table->id)->where('record_id',$recordId)->max('ordine');
        $filesNumber = File::where('table_id', $table->id)->where('record_id',$recordId)->count();


        if($filesMax == (int)$filesNumber){
            $request->session()->flash('mesaj',"Numarul maxim de fisiere a fost deja atins ($filesNumber).");
            return redirect('admin/core/'.$tabela.'/addFile/'.$recordId);
        }

        //Store file info in Files table
        $time = strval(time());
        $fileName = $tabela.'_ID'.$table->id.'_'.$recordId.'_'.$time.'.'.$request->file('file')->getClientOriginalExtension();

        $file = new File();
        $file->table_id = $table->id;
        $file->record_id = (int)$recordId;
        $file->ordine = ++$ordine;
        $file->name = $fileName;
        $file->title = (empty($request->title))?null:$request->title;
        $file->save();

        // Store file on disk
        $disk = ( config('app.env') == 'production' )?'files_p':'files';
        Storage::disk($disk)->putFileAs('files', $request->file('file'), $fileName);
        $request->session()->flash('mesaj','Fisierul a fost adugat cu succes!');
        return redirect('admin/core/'.$tabela.'/addFile/'.$recordId);
    }

    /**
     * Deletes a file
     *
     * @param $fileId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function delete(Request $request, $fileId)
    {
        $fileId = (int)trim($fileId);
        $file = File::find($fileId);

        if(null == $file){
            return redirect()->back();
        }

        $tableName = $file->table->table_name;
        $recordId = $file->record_id;

        Storage::disk('files')->delete('files/'.$file->name);
        $file->delete();

        return redirect('admin/core/'.$tableName.'/addFile/'.$recordId)->with('mesaj','Fisierul a fost sters.');
    }

    /**
     * Updates the order and the description for a file
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

        $recordFiles = File::where('table_id', $tableId)->where('record_id', $recordId)->orderBy('ordine')->get();

        if ( null === $recordFiles ){
            return redirect()->back();
        }

        foreach($recordFiles as $recordFile){
            $ordine = 'ordine_'.$recordFile->id;
            $title = 'title_'.$recordFile->id;
            if( $request->has($ordine) ) {
                if(strcmp($recordFile->title, $request->$title) !== 0 || $recordFile->ordine != $request->$ordine){

                    if (ctype_digit((string)trim($request->$ordine)) !== true) {
                        continue;
                    }

                    $this->validate($request,[
                        $title => 'required|max:100'
                    ]);

                    $recordFile->title = (empty($request->$title))?null:$request->$title;
                    $recordFile->ordine = $request->$ordine;
                    $recordFile->save();
                }
            }
        }

        return redirect()->back();
    }
}
