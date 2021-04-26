<?php

namespace Decoweb\Panelpack\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Decoweb\Panelpack\Services\Records;
use Illuminate\Support\Facades\Schema;
use Decoweb\Panelpack\Helpers\Traits\Core;
use Decoweb\Panelpack\Models\Image as Poza;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class RecordsController1 extends Controller
{
    use Core;

    private $records;

    public function __construct(Records $records)
    {
        $this->middleware('web');
        $this->middleware('auth');
        $this->records = $records;
    }

    /**
     * Returns all records from a table
     *
     * @param $tableName
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function index($tableName)
    {
        $core = $this->getTableData( $tableName );

        if( $core === false ){
            $this->clearTableCoreCache($tableName);
            return redirect('admin/home')->with('aborted', 'Tabela nu exista in baza de date.');
        }

        $settings = $this->getSettings($tableName);

        $orderQueryUrl = false;
        $appends = null;
        $query = DB::table($tableName);
        $this->records->applyFilters($query, $settings);
        if( request()->has('order') && request()->has('dir')){
            $displayedName = $settings['config']['displayedName'];
            if( in_array(request('order'), ['order', 'visible', $displayedName ]) && in_array(request('dir'), ['asc','desc']) ){
                $query->orderBy(request('order'),request('dir'));
                $appends[] = 'order|'.request('order');
                $appends[] = 'dir|'.request('dir');
                if( request('order') == 'order' ) $orderQueryUrl = true;
            }
        }
        if($settings['config']['functionSetOrder'] == 1 && $orderQueryUrl == false ){
            $query->orderBy('order');
        }
        $query->orderBy('created_at');
        $records = $query->get();

        $recordsToArray = $records->toArray();
        // ptr recursive - trebuie rearanjate
        $result = $this->records->valuesToArray($recordsToArray);
        if ( $settings['config']['functionRecursive'] == 1 ){
            $tree = $this->records->drawTree($result, $settings['config']['displayedName'], $settings['config']['recursiveMax'] );
        }else{
            $tree = $result;
        }

        $paginated = $this->records->paginate($tree, $settings, $appends);
        $filters = $this->records->generateFilters($core->table_name);

        if($settings['config']['functionImages'] == 1){
            $poze = Poza::where('table_id', $core->id)->orderBy('ordine','asc')->get();
            $pics = [];
            foreach($poze as $poza ){
                $pics[$poza->record_id][] = $poza->name;
            }
        }else{
            $pics = null;
        }

        return view('decoweb::admin.records.index',
            [
                'tabela'        => $paginated,
                'core'          => $core,
                'settings'      => $settings,
                'pics'          => $pics,
                'filters'       => $filters,
                'spanActions'   => $this->records->getSpannedColumns($settings),
            ]
        );
    }

    public function resetFilters(Request $request, $tableName)
    {
        // FIX MEE - verifica existenta tabelei!
        $deleted = false;
        if( $request->session()->has('filters.'.$tableName) ){
            $request->session()->forget('filters.'.$tableName);
            $deleted = true;
        }
        $message = ($deleted)?'Filterele au fost sterse cu succes.':'Nu exista filtre setate.';
        return redirect('admin/core/'.$tableName)->with('mesaj',$message);
    }

    /**
     * Deletes a record in the specified table
     *
     * @param Request $request
     * @param $tabela
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function delete(Request $request, $tabela, $id)
    {
        $id = (int)$id; // $id of the record to delete
        $table = $this->getTableData($tabela);
        if(null == $table){
            $request->session()->flash('mesaj','Acesta tabela nu este exista.');
            return redirect()->back();
        }
        // $settings = unserialize($table->settings);
        $settings = $this->getSettings($tabela);
        $model = 'App\\'.$table->model;
        $record = $model::find($id);
        if( $this->records->recordHasChildren($table->table_name, $record) === true ){
            $name = strtoupper($record->{$settings['config']['displayedName']});
            $message = "EROARE: Categoria $name are subcategorii. Va rugam sa stergeti mai intai subcategoriile.";
            return redirect('admin/core/'.$table->table_name)->with('mesaj',$message);
        }
        //dd($record);
        $record->delete();
        $request->session()->flash('mesaj',$settings['messages']['deleted']);
        return redirect('admin/core/'.$table->table_name);
    }

    /**
     * Displays a page for creating a new record in the specified table
     *
     * @param $tableName
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function create($tableName)
    {
        if(!Schema::hasTable($tableName)){
            return redirect('admin/home');
        }

        $table = $this->getTableData($tableName);
        // $fields = unserialize($table->settings);
        $fields = $this->getSettings($tableName);
        //dd($settings);
        $settings = $this->records->getOptions($fields, $tableName);
        return view('decoweb::admin.records.create',['table'=>$table, 'settings'=>$settings]);
    }

    /**
     * Stores a new record in a table; $id - from SysCoreSetup
     *
     * @param Request $request
     * @param SysCoreSetup $table
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, SysCoreSetup $table)
    {

        $fields = $this->getSettings($table->table_name);
        $model = 'App\\'.$table->model;
        $newRecord = new $model();
        $rules = $this->records->generateRules($fields['elements'], $table->table_name);
        $this->validate($request,$rules);

        foreach($fields['elements'] as $column=>$data){
            if($data['type'] == 'checkbox'){
                $newRecord->$column = (!empty($request->$column) && $request->$column == 'on')?1:2;
            }else{
                $colType = explode('|',$data['colType']); # We need to set manually decimal columns to NULL if input is empty ("")
                if( $colType[0] == 'decimal' && trim($request->$column) == '' ){
                    $newRecord->$column = null;
                }else{
                    $newRecord->$column = $request->$column;
                }
            }
            # Storing the record's slug
            if($column === $fields['config']['displayedName']){
                $newRecord->slug = Str::slug($request->$column);
            }

        }
        if($fields['config']['functionVisible'] == 1){
            $newRecord->visible = (!empty($request->visible) && $request->visible == 'on')?1:2;
        }
        if($fields['config']['functionSetOrder'] == 1){
            $order = $model::max('order');
            $order = (int)$order + 1;
            $newRecord->order = $order;
        }
        $newRecord->save();

        $request->session()->flash('mesaj',$fields['messages']['added']);
        return redirect('admin/core/'.$table->table_name);
    }

    /**
     * @param $tableName
     * @param $id
     */
    public function edit($tableName, $id)
    {
        $id = (int)$id;
        $table = $this->getTableData( $tableName );
        $fields = $this->getSettings( $tableName );
        $settings = $this->records->getOptions($fields, $tableName, $id);

        $modelName = $table->model;
        $model = '\App\\'.$modelName;
        $record = $model::find($id);

        if( null === $record){
            return redirect('admin/core/'.trim($tableName))->with('aborted','Inregistrare inexistenta');
        }

        return view('decoweb::admin.records.edit',['record'=>$record, 'fields'=>$settings]);
    }

    /**
     * Updates a record
     *
     * @param Request $request
     * @param $tabela
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request, $tabela, $id)
    {
        $id = (int)$id;
        $tableName = trim($tabela);
        $tableData = $this->getTableData($tableName);
        $fields = $this->getSettings($tableName);

        $modelName = $tableData->model;
        $model = '\App\\'.$modelName;
        $record = $model::find($id);

        $rules = $this->records->generateRules($fields['elements'], $tableName);

        $this->validate($request,$rules);

        foreach($fields['elements'] as $column=>$data){

            if( $data['type'] == 'select' && $this->records->recordHasChildren($tableName,$record) ){
                if( (int)$request->$column > 0 ){
                    $request->session()->flash('aborted','Modificare nereusita. Acesta categorie are deja subcategorii.');
                    return redirect('admin/core/'.$tabela.'/edit/'.$id);
                }
            }
            if($data['type'] == 'checkbox'){
                $record->$column = (!empty($request->$column) && $request->$column == 'on')?1:2; // da,nu
            }else{
                $colType = explode('|',$data['colType']); # We need to set manually decimal columns to NULL if input is empty ("")
                if( $colType[0] == 'decimal' && trim($request->$column) == '' ){
                    $record->$column = null;
                }else{
                    $record->$column = $request->$column;

                    # Updates record's slug
                    if($column === $fields['config']['displayedName']){
                        $record->slug = Str::slug($request->$column);
                    }
                }
            }

        }
        if($fields['config']['functionVisible'] == 1){
            $record->visible = (!empty($request->visible) && $request->visible == 'on')?1:2;
        }
        $record->save();

        $request->session()->flash('mesaj','Schimbarea a fost realizata cu succes!');
        return redirect('admin/core/'.$tabela.'/edit/'.$id);
    }

    /**
     * Update records' order | Delete multiple records
     *
     * @param Request $request
     * @param         $tableName
     * @return \Illuminate\Http\RedirectResponse
     */
    public function recordsActions(Request $request, $tableName)
    {
        $tableName = (string)trim($tableName);
        $tableData = $this->getTableData($tableName);
        if( !$tableData ){
            return redirect('admin/core/'.$tableName)->with('aborted','Tabela nu exista. [EROARE GRAVA]');
        }
        $fields = $this->getSettings($tableName);

        if($request->has('changeOrder') && $fields['config']['functionSetOrder'] != 1){
            return redirect('admin/core/'.$tableName)->with('aborted','Ordinea nu poate fi setata pentru aceasta tabela.');
        }
        if($request->has('deleteItems') && $fields['config']['functionDelete'] != 1){
            return redirect('admin/core/'.$tableName)->with('aborted','Elementele nu pot fi sterse pentru aceasta tabela.');
        }

        $modelName = $tableData->model;
        $model = '\App\\'.$modelName;
        $message = '';
        if( $request->has('changeOrder') && $request->changeOrder == 1 ) {
            if( $request->has('orderId') && is_array($request->orderId) && count($request->orderId) > 0 ){
                foreach($request->orderId as $id=>$newOrder){
                    $record = $model::find((int)$id);
                    if( $record && $newOrder != $record->order && $newOrder >= 0 ){
                        if ( ctype_digit((string) trim($newOrder)) !== true ) {
                            continue;
                        }
                        $record->order = (int)$newOrder;
                        $record->save();
                    }else{
                        continue;
                    }
                }
                $message = 'Ordinea a fost schimbata cu succes!';
            }
        }
        if( $request->has('deleteItems') && $request->deleteItems == 1){
            if( $request->has('item') && is_array($request->item) && count($request->item) > 0){
                $toDelete = [];
                foreach($request->item as $itemKey=>$item){
                    $record = $model::find((int)$itemKey);
                    if( !is_null($record) && $this->records->recordHasChildren($tableName,$record) ){
                        continue;
                    }
                    $toDelete[] = $itemKey;
                }
                $howMany = count($toDelete);
                $model::whereIn('id',$toDelete)->delete();
                $message = "Un numar de $howMany de elemente au fost sterse.";
            }else{
                $message = "Niciun element nu a fost sters.";
            }
        }

        return redirect('admin/core/'.$tableName)->with('mesaj', $message);
    }

    public function limit(Request $request, SysCoreSetup $table)
    {
        $this->validate($request,[
            'perPage' => 'required|integer|min:5'
        ]);

        $settings = $this->getSettings($table->table_name);
        $settings['config']['limitPerPage'] = $request->perPage;
        $table->settings = $this->updateSiteSettings($settings);
        $table->save();

        $this->clearTableCoreCache($settings['config']['tableName']);

        return redirect()->back();
    }

}
