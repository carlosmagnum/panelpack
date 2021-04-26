<?php


namespace Decoweb\Panelpack\Services;


use Decoweb\Panelpack\Helpers\Contracts\PicturesContract;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Decoweb\Panelpack\Helpers\Traits\Core;
class Records
{
    use Core;

    /**
     * @param array $array
     * @param       $displayedName
     * @param       $recursiveMax
     * @param int   $deep
     * @param int   $parent
     * @param array $result
     * @return array
     */
    public function drawTree(array $array, $displayedName, $recursiveMax, $deep = 0, $parent = 0, &$result = array()){
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

    public function getRecursiveIds($selectTable, $parentId, &$in = [])
    {
        $ids = DB::table( $selectTable )->where('parent', $parentId)->pluck('id')->toArray();
        foreach ($ids as $id){
            $in[] = $id;
            $this->getRecursiveIds($selectTable, $id, $in);
        }

        return $in;
    }


    /**
     * @param $query
     * @param $settings
     * @return $this|null
     */
    public function applyFilters($query, $settings)
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

    /**
     * Verifica daca id-ul inregistrarii se afla in coloana
     * "parent". Daca da, inseamna ca aceasta inregistrare
     * are cel putin o subcategorie.
     *
     * @param $tableName
     * @param $record
     * @return bool
     */
    public function recordHasChildren($tableName, $recordId)
    {
        if(!$this->tableHasParentColumn($tableName) ) return false;
        $parentIds = $this->getParents($tableName);
        return in_array((int)$recordId, $parentIds);
    }


    public function getParents($tableName)
    {
        $parentIds = DB::table($tableName)->pluck('parent')->toArray();
        return array_filter($parentIds);
    }

    /**
     * Generates validation rules for storing a new record
     *
     * @param array $elements
     * @param $tableName
     * @return array|bool
     */
    public function generateRules(array $elements, $tableName)
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


    /**
     * @param array $settings
     * @param string $tableName
     * @param null $excludeCurrentRecordId
     * @return array
     */
    public function getOptions(array $settings, string $tableName, $excludeCurrentRecordId = null)
    {
        foreach($settings['elements'] as &$field){
            if($field['type'] == 'select'){
                if ( $field['selectTable'] != $tableName) {
                    $parent = $this->getTableData($field['selectTable']);
                    $parentSettings = $this->getSettings($field['selectTable']);
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

                if($settings['config']['functionRecursive'] == 1 && $field['selectTable'] == $tableName){
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
     * @param array $arrayOfObjects
     * @return array[]
     */
    public function valuesToArray(array $arrayOfObjects)
    {
        return array_map(function ($value) {
            return (array) $value;
        }, $arrayOfObjects);
    }

    /**
     * @param $tree
     * @param $settings
     * @param null $appends
     * @return LengthAwarePaginator
     */
    public function paginate($tree, $settings, $appends = null)
    {
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
    }


    /**
     * Generates filters data
     *
     * @param string $tableName
     * @return array|false
     */
    public function generateFilters(string $tableName)
    {
        $table = $this->getTableData($tableName);
        $settings = $this->getSettings($tableName);

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


    /**
     * Calculates how many columns must be spanned
     * in the records table head
     *
     * @param array $settings
     * @return int
     */
    public function getSpannedColumns(array $settings): int
    {
        return (int)$settings['config']['functionImages'] +
            (int)$settings['config']['functionDelete'] +
            (int)$settings['config']['functionEdit'] +
            (int)$settings['config']['functionFile'];
    }

    /**
     * @param $tableName
     * @param $itemsPerPage
     * @return bool
     */
    public function updateLimitPerPage($tableName, $itemsPerPage)
    {
        $table = $this->getTableData($tableName);
        $settings = $this->getSettings($table->table_name);

        if( $settings['config']['limitPerPage'] == $itemsPerPage ){
            return false;
        }

        $settings['config']['limitPerPage'] = $itemsPerPage;
        $table->settings = $this->updateSiteSettings($settings);;
        $table->save();
        $this->clearTableCoreCache($table->table_name);
        return true;
    }

    /**
     * @param $tableName
     * @return bool
     * @throws \Throwable
     */
    public function tableAcceptsReordering($tableName)
    {
        $fields = $this->getSettings($tableName);

        throw_if( !$fields,
            \Exception::class,
            "Tabela $tableName nu exista in baza de date." );

        throw_if( $fields['config']['functionSetOrder'] != 1,
            \Exception::class,
            "Ordinea nu poate fi setata pentru aceasta tabela." );

        return true;
    }

    /**
     * @param $request
     * @return mixed
     * @throws \Throwable
     */
    public function validateBeforeReordering($request)
    {
        $records_to_update = array_filter(array_diff_assoc($request->orderId, $request->oldOrderId), function($k, $v){
            return ctype_digit(trim($k)) && ctype_digit(trim($v)) && $k > 0 && $v > 0;
        }, ARRAY_FILTER_USE_BOTH);

        throw_if( !count( $records_to_update ) > 0,
            \Exception::class,
            'Ordinea inregistrarilor a ramas neschimbata.');

        return $records_to_update;
    }


    /**
     * @param $records_to_update
     * @param $tableName
     */
    public function updateOrder($records_to_update, $tableName): void
    {
        foreach ($records_to_update as $record_id => $record_order) {
            DB::table($tableName)
                ->select(['id', 'order'])
                ->where('id', $record_id)
                ->update(['order' => $record_order]);
        }
    }

    public function deleteImages()
    {

    }


    /**
     * Drops existing filters of a table.
     *
     * @param $request
     * @param string $tableName
     * @return string
     */
    public function dropFilters($request, string $tableName): string
    {
        if( $request->session()->has('filters.'.$tableName) ){
            $request->session()->forget('filters.'.$tableName);
            return 'Filtrele au fost sterse cu succes!';
        }
         return 'Filtre inexistente.';
    }

    /**
     * @param $settings
     * @return null
     */
    public function getMainImages($settings)
    {
        if( $settings['config']['functionImages'] != 1 ){
            return null;
        }

        $images = App::make(PicturesContract::class);
        return $images->setModel($settings['config']['model'])->recordsFirstPics();
    }
}
