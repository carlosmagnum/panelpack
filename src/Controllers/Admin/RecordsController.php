<?php

namespace Decoweb\Panelpack\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use Decoweb\Panelpack\Models\SysCoreSetup;
use Decoweb\Panelpack\Models\Image as Poza;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RecordsController extends Controller
{

    public function __construct()
    {
        $this->middleware('web');
        $this->middleware('auth');
    }

    /**
     * Gets TABLE DATA from database.sys_core_setups & caches it forever
     *
     * @param string $tableName
     * @return mixed
     */
    private function tableCore(string $tableName)
    {
        $tableName = trim($tableName);

        $core = Cache::rememberForever('core_'.$tableName, function() use ($tableName) {

            $core = SysCoreSetup::table( $tableName );

            if( ! $core instanceof SysCoreSetup ) return false;

            return $core;
        });

        return $core;
    }

    /**
     * Returns all records from a table
     * 
     * @param $tableName
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function index($tableName)
    {
        $core = $this->tableCore( $tableName );

        if( $core === false ){
            $this->clearTableCoreCache($tableName);
            return redirect('admin/home')->with('aborted', 'Tabela nu exista in baza de date.');
        }

        $settings = unserialize($core->settings);

        $orderQueryUrl = false;
        $appends = null;
        $query = DB::table($tableName);
        $this->applyFilters($query, $settings);
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
        };
        $query->orderBy('created_at');
        $records = $query->get();

        $recordsToArray = $records->toArray();
        // ptr recursive - trebuie rearanjate
        $result = $this->valuesToArray($recordsToArray);
        if ( $settings['config']['functionRecursive'] == 1 ){
            $tree = $this->drawTree($result, $settings['config']['displayedName'], $settings['config']['recursiveMax'] );
        }else{
            $tree = $result;
        }

        $paginated = $this->paginate($tree, $settings, $appends);
        $filters = $this->generateFilters($core->table_name);

        if($settings['config']['functionImages'] == 1){
            $poze = Poza::where('table_id', $core->id)->orderBy('ordine','asc')->get();
            $pics = [];
            foreach($poze as $poza ){
                $pics[$poza->record_id][] = $poza->name;
            }
        }else{
            $pics = null;
        }

        $spanActions = (int)$settings['config']['functionImages'] +
            (int)$settings['config']['functionDelete'] +
            (int)$settings['config']['functionEdit'] +
            (int)$settings['config']['functionFile'];

        return view('decoweb::admin.records.index',
            [
                'tabela'        => $paginated,
                'core'          => $core,
                'settings'      => $settings,
                'pics'          => $pics,
                'filters'       => $filters,
                'spanActions'   => $spanActions,
            ]
        );
    }

    /**
     * @param array $array
     * @param       $displayedName
     * @param       $recursiveMax
     * @param int   $deep
     * @param int   $parent
     * @param array $result
     * @return array
     */
    private function drawTree(array $array, $displayedName, $recursiveMax, $deep = 0, $parent = 0, &$result = array()){
        if ($parent != 0){
            $deep++;
        }
        foreach ($array as $key => $data){
            if ( $data['parent'] == $parent ){
                if ($parent != 0){
                    $pad = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;',$deep);
                    $data[$displayedName] = $pad.$data[$displayedName];
                }
                $result[] = $data;
                if ($deep < $recursiveMax){
                    $this->drawTree($array, $displayedName, $recursiveMax, $deep, $data['id'], $result);
                }
            }
        }
        return $result;
    }

    private function getRecursiveIds($selectTable, $parentId, &$in = [])
    {
        $ids = DB::table( $selectTable )->where('parent', $parentId)->pluck('id')->toArray();
        foreach ($ids as $id){
            $in[] = $id;
            $this->getRecursiveIds($selectTable, $id, $in);
        }

        return $in;
    }

    private function applyFilters($query, $settings)
    {
        if( empty($settings['filter']) ) return null;

        foreach($settings['filter'] as $filter){
            if( request()->has($filter) && !empty(request($filter)) ){
                session( ['filters.'.$settings['config']['tableName'].'.'.$filter => trim(request($filter))] );
                // Merge si metoda urmatoare:
                //session()->put( 'filters.'.$settings['config']['tableName'].'.'.$filter, trim(request($filter)) );
            }
        }

        if( session()->has('filters.'.$settings['config']['tableName']) ){

            foreach( session('filters.'.$settings['config']['tableName']) as $filter =>$filterValue){

                if ( $settings['elements'][$filter]['type'] == 'select' ){
                    # Sa aflam daca categoriile au subcategorii
                    if( Schema::hasColumn( $settings['elements'][$filter]['selectTable'],'parent') ){
                        # daca au subcategorii, urmeaza sa le colectam id-urile
                        # $filterValue este id-ul categoriei careia ii cautam subcat.
                        $in = $this->getRecursiveIds($settings['elements'][$filter]['selectTable'],$filterValue);
                        $in[] = $filterValue;
                        $query->whereIn($filter,$in);
                    }else{
                        $query->where($filter,$filterValue);
                    }
                }

                if ( $settings['elements'][$filter]['type'] == 'text' ){
                    $query->where($filter,'like','%'.$filterValue.'%');
                }
            }
        }
        return $this;
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
        $table = $this->tableCore($tabela);
        if(null == $table){
            $request->session()->flash('mesaj','Acesta tabela nu este exista.');
            return redirect()->back();
        }
        $settings = unserialize($table->settings);
        $model = 'App\\'.$table->model;
        $record = $model::find($id);
        if( $this->recordHasChildren($table->table_name, $record) === true ){
            $name = strtoupper($record->{$settings['config']['displayedName']});
            $message = "EROARE: Categoria $name are subcategorii. Va rugam sa stergeti mai intai subcategoriile.";
            return redirect('admin/core/'.$table->table_name)->with('mesaj',$message);
        }
        //dd($record);
        $record->delete();
        $request->session()->flash('mesaj',$settings['messages']['deleted']);
        return redirect('admin/core/'.$table->table_name);
    }

    private function recordHasChildren($tableName, $record)
    {
        if( ! Schema::hasColumn($tableName,'parent') ){
            return false;
        }

        $parentIds = DB::table($tableName)->pluck('parent')->toArray();
        //dd($parentIds);
        if(in_array($record->id,$parentIds)){
            return true;
        }

        return false;
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

        $table = $this->tableCore($tableName);
        $fields = unserialize($table->settings);
        //dd($settings);
        $settings = $this->getOptions($fields, $tableName);
        return view('decoweb::admin.records.create',['table'=>$table, 'settings'=>$settings]);
    }

    /**
     * Stores a new record in a table; $id - from SysCoreSetup
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request, SysCoreSetup $table)
    {
        $fields = unserialize($table->settings);
        //dd($fields);
        $model = 'App\\'.$table->model;
        $newRecord = new $model();
        $rules = $this->generateRules($fields['elements'], $table->table_name);
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
     * Edits a record
     *
     * @param $tableName
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($tableName, $id)
    {
        $id = (int)$id;
        // $tableName = (string)trim($tableName);
        // $tableData = SysCoreSetup::select('name','model','settings')->where('table_name',$tableName)->first();
        $tableData = $this->tableCore( $tableName );
        $fields = unserialize($tableData->settings);
        $settings = $this->getOptions($fields, $tableName, $id);

        $modelName = $tableData->model;
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
        $tableData = $this->tableCore($tableName);
        $fields = unserialize($tableData->settings);

        $modelName = $tableData->model;
        $model = '\App\\'.$modelName;
        $record = $model::find($id);

        $rules = $this->generateRules($fields['elements'], $tableName);

        $this->validate($request,$rules);

        foreach($fields['elements'] as $column=>$data){

            if( $data['type'] == 'select' && $this->recordHasChildren($tableName,$record) ){
                if( (int)$request->$column > 0 ){
                    $request->session()->flash('aborted','Modificare nereusita. Acesta categorie are deja subcategorii.');
                    return redirect('admin/core/'.$tabela.'/edit/'.$id);
                }
            }
            if($data['type'] == 'checkbox'){
                $record->$column = (!empty($request->$column) && $request->$column == 'on')?1:2;
            }else{
                $colType = explode('|',$data['colType']); # We need to set manually decimal columns to NULL if input is empty ("")
                if( $colType[0] == 'decimal' && trim($request->$column) == '' ){
                    $record->$column = null;
                }else{
                    $record->$column = $request->$column;
                    
                    # Updates record's slug
                    if($column === $fields['config']['displayedName']){
                        $record->slug = str_slug($request->$column);
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
        $tableData = $this->tableCore($tableName);
        if( !$tableData ){
            return redirect('admin/core/'.$tableName)->with('aborted','Tabela nu exista. [EROARE GRAVA]');
        }
        $fields = unserialize($tableData->settings);

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
                    if( !is_null($record) && $this->recordHasChildren($tableName,$record) ){
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
    /**
     * Generates validation rules for storing a new record
     *
     * @param array $elements
     * @return array|bool
     */
    private function generateRules(array $elements, $tableName)
    {
        if(!is_array($elements) || empty($elements)){
            return false;
        }
        $rules = [];
        $colsWithLength = ['varchar','char'];
        $length = '';
        $decimal = '';
        foreach ($elements as $column=>$data){
            $required = ($data['required'] == 1 && $data['type'] != 'checkbox')?'required|':'';
            $colType = explode('|',$data['colType']);
            if( $colType[0] == 'decimal'){
                list($total,$decimals) = explode(',',$colType[1]);
                $total = str_repeat('9',$total - $decimals);
                $decimals = str_repeat('9',$decimals);
                $decimal = "numeric|max:{$total}.{$decimals}|";
            }elseif( in_array($colType[0], $colsWithLength)){
                $length = 'max:' . $colType[1] .'|';
            }
            if( $data['type'] == 'select'){
                $ids = DB::table($data['selectTable'])->pluck('id')->toArray();
                //dd($ids);
                $ids = implode(',',$ids);

                if($data['selectTable'] == $tableName){
                    $ids .= ',0';
                }
                /*dd($tableName);
                dd($data['selectTable']);*/
                $select = "integer|in:{$ids}|";
            }else{
                $select = '';
            }
            $rules[$column] = trim("{$required}{$select}{$decimal}{$length}",'|');
            if(empty($rules[$column])){
                unset($rules[$column]);
            }
            $length = '';
            $decimal = '';
        }

        //dd($rules);
        return $rules;
    }

    private function getOptions(array $settings, $table, $excludeCurrentRecordId = null)
    {
        foreach($settings['elements'] as &$field){
            if($field['type'] == 'select'){
                if ( $field['selectTable'] != $table) {
                    $parent = SysCoreSetup::where('table_name', $field['selectTable'])->first();
                    $parentSettings = unserialize($parent->settings);
                    $sameTable = false;
                }else{
                    $parentSettings = $settings;
                    $sameTable = true;
                }

                $orderBy = ($parentSettings['config']['functionSetOrder'] == 1)?'order':'created_at';
                // Check if parent table is recursive (if it has categories and subcategories)
                if(array_key_exists('parent',$parentSettings['elements'])){
                    $excludedId = ($excludeCurrentRecordId && $sameTable)?(int)$excludeCurrentRecordId:'';
                    $options = DB::table($field['selectTable'])->select('id','parent',$parentSettings['config']['displayedName'])
                        ->where('id','!=',$excludedId)->orderBy($orderBy)->get()->toArray();
                    $toArray = $this->valuesToArray($options);
                    // CREATE - Daca tabela este aceeasi: recursiveMax -= 1
                    // EDIT - Daca tabela este aceeasi: la fel. In plus, trebuie exclus ID-ul editat din lista de optiuni
                    $recursiveMax = ($sameTable)?$parentSettings['config']['recursiveMax'] - 1:$parentSettings['config']['recursiveMax'];
                    $options = $this->drawTree($toArray, $parentSettings['config']['displayedName'],$recursiveMax );
                    //$options = $this->drawTree($toArray, $parentSettings['config']['displayedName'],$parentSettings['config']['recursiveMax'] );
                }else{
                    $options = DB::table($field['selectTable'])->select('id', $parentSettings['config']['displayedName'])
                        ->orderBy($orderBy)->get()->toArray();
                    $options = $this->valuesToArray($options);
                }

                if($settings['config']['functionRecursive'] == 1 && $field['selectTable'] == $table){
                    $default = (empty($field['selectFirstEntry']))?'Categorie principala':$field['selectFirstEntry'];
                    $field['options'][] = $default;
                }else{
                    $default = (empty($field['selectFirstEntry']))?'':$field['selectFirstEntry'];
                    $field['options'][] = $default;
                }

                foreach($options as $option){
                    $field['options'][$option['id']] = $option[$parentSettings['config']['displayedName']];
                }
            }
        }
        //dd($settings);
        return $settings;
    }

    /**
     * @param $arrayOfObjects
     * @return array
     */
    private function valuesToArray(array $arrayOfObjects)
    {
        $result = array_map(function ($value) {
            return (array) $value;
        }, $arrayOfObjects);
        return $result;
    }

    /**
     * @param $tree
     * @param $settings
     * @return LengthAwarePaginator
     */
    private function paginate($tree, $settings, $appends = null)
    {
        #paginator START
        //Get current page form url e.g. &page=6
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        //Create a new Laravel collection from the array data
        $collection = new Collection($tree);

        //Define how many items we want to be visible in each page
        $perPage = $settings['config']['limitPerPage'];

        //Slice the collection to get the items to display in current page
        $currentPageSearchResults = $collection->slice(($currentPage - 1) * $perPage, $perPage)->all();

        //Create our paginator and pass it to the view
        $paginated = new LengthAwarePaginator($currentPageSearchResults, count($collection), $perPage);
        if( $appends !== null ) {
            foreach($appends as $request){
                list($key, $value) = explode('|',$request);
                $paginated->appends($key, $value);
            }
        }
        return $paginated;

        #paginator END
    }

    private function generateFilters($tableName)
    {
        $table = $this->tableCore($tableName);
        $settings = unserialize($table->settings);
        if( empty(array_filter($settings['filter'])) ) {
            return false;
        }

        $filterColumns = $settings['filter'];
        $elements = $settings['elements'];

        $filters = [];
        $filterKey = 0;
        foreach($filterColumns as $filterColumn){
            if(array_key_exists($filterColumn,$elements)){
                if( $elements[$filterColumn]['type'] == 'select'){
                    if( $elements[$filterColumn]['selectTable'] != $table->name){
                        $filters[$filterKey]['type'] = 'select';
                        $filters[$filterKey]['column'] = $filterColumn;
                        $filters[$filterKey]['name'] = $elements[$filterColumn]['friendlyName'];

                        $newSettings = $this->getOptions($settings, $table->table_name);
                        $filters[$filterKey]['options'] = $newSettings['elements'][$filterColumn]['options'];
                    }
                }
                if( $elements[$filterColumn]['type'] == 'text'){
                    $filters[$filterKey]['type'] = 'text';
                    $filters[$filterKey]['column'] = $filterColumn;
                    $filters[$filterKey]['name'] = $elements[$filterColumn]['friendlyName'];
                }
            }
            ++$filterKey;
        }
        return $filters;
    }

    public function limit(Request $request, SysCoreSetup $table)
    {
        $this->validate($request,[
            'perPage' => 'required|integer|min:5'
        ]);
        $settings = unserialize($table->settings);
        $settings['config']['limitPerPage'] = $request->perPage;
        $table->settings = serialize($settings);
        $table->save();

        $this->clearTableCoreCache($settings['config']['tableName']);

        return redirect()->back();
    }

    /**
     * Clear the cache of a table
     *
     * @param $tableName
     * @return bool
     */
    private function clearTableCoreCache($tableName)
    {
        $key = 'core_'.trim($tableName);
        if (Cache::has( $key )){
            Cache::forget( $key );
            return true;
        }
        return false;
    }
}