<?php

namespace Decoweb\Panelpack\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Decoweb\Panelpack\Models\SysCoreSetup as Table;
use Illuminate\Support\Facades\Storage;
use Decoweb\Panelpack\Models\Image;
use Illuminate\Support\Facades\Cache;
class TablesController extends Controller
{
    private $table;
    private $forbiddenTables =[
        'brand_category',
        'brands',
        'category_option',
        'customers',
        'images',
        'invoices',
        'migrations',
        'options',
        'ordereditems',
        'orders',
        'password_resets',
        'proformas',
        'statuses',
        'sessions',
        'shoppingcart',
        'sys_core_options',
        'sys_settings',
        'transports',
        'users',
    ];
    private $configFields = [
        'string'=>[
            'tableName',
            'pageName',
            'model',
            'displayedName',
            'displayedFriendlyName',
            'filesExt',

        ],
        'limitPerPage',
        'functionAdd',
        'functionEdit',
        'functionDelete',
        'functionSetOrder',
        'functionImages',
        'imagesMax',
        'functionFile',
        'filesMax',
        'functionVisible',
        'functionCreateTable',
        'functionRecursive',
        'recursiveMax',
    ];
    private $messageFields = [
        'add',
        'edit',
        'no_images',
        'no_files',
        'added',
        'deleted',
    ];
    private $hasParents = 0;


    public function __construct()
    {
        $this->middleware('web');
        $this->middleware('auth');
    }

    /**
     * Displays all created tables
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $tabele = Table::orderBy('order')->orderBy('created_at')->get();
        return view('decoweb::admin.tables.index',['tabele'=>$tabele]);
    }

    /**
     * Displays the view for creating a new table in db
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        $options = $this->selectTablesOptions();
        $types = $this->selectTypes();
        return view('decoweb::admin.tables.create', ['tabele'=>$options,'types'=>$types]);
    }

    /**
     * Creates a new table, new Model & stores table's details in sys_core_setups
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        $table = [];

        foreach($this->configFields as $key=>$field){
            if(is_array($field) && $key == 'string'){
                foreach($field as $f){
                    $table['config'][$f] = trim($request->$f);
                }
            }else{
                if($field == null){
                    $table['config'][$field] = 0;
                }else{
                    $table['config'][$field] = (int)$request->$field;
                }
            }
        }

        foreach($this->messageFields as $message){
            $table['messages'][$message] = trim($request->$message);
        }

        if($request->has('filter')){
            $filters = explode(',',$request->filter);
            foreach($filters as $filter){
                $table['filter'][] = $filter;
            }
        }else{
            $table['filter']='';
        }

        $table = $this->storeColumnsInfo($request, $table);
        $this->table = $table;

        //dd($this->table);
        $tabela = new Table();
        $tabela->name = $table['config']['pageName'];
        $tabela->table_name = $table['config']['tableName'];
        $tabela->model = $table['config']['model'];
        $tabela->settings = serialize($table);
        $tabela->order = Table::max('order') + 1;
        $tabela->visible = $table['config']['functionVisible'];
        $tabela->save();

        /*try{
            $newTable = $this->createTable();
        }catch (\Exception $e){
            echo $e->getMessage();
            die();
        }*/
        $newTable = $this->createTable();

        $model = $this->makeModel($tabela->model);
        $modelSuccess = ($model !== false)?' (cu model)':' (fara model)';
        $mesaj = ($newTable === true)?"Tabela a fost creata $modelSuccess.":'Tabela NU a fost creata.';

        //dd($this->table);
        //echo '<pre>',print_r($table),'</pre>';
        $request->session()->flash('mesaj',$mesaj);
        return redirect('admin/table-settings');
    }

    /**
     * Function for creating new table in DB
     *
     * @return bool
     */
    private function createTable()
    {
        $tableName = $this->table['config']['tableName'];
        if( $this->table['config']['functionCreateTable'] != 1 || Schema::hasTable($tableName) ) {
            return false;
        }

        Schema::create($tableName, function (Blueprint $tab) {
            $tab->engine = 'InnoDB';
            $tab->increments('id');

            foreach ($this->table['elements'] as $column => $property) {
                $type = '';
                $length = '';

                $this->hasParents += ($property['type'] == 'select')? 1 : 0;

                $param = explode('|', $property['colType']);
                switch (trim($param[0])) {
                    case 'varchar':
                        $type = 'string';
                        $length = (int)$param[1];
                        break;
                    case 'text':
                        $type = 'text';
                        break;
                    case 'int':
                        $type = 'integer';
                        break;
                    case 'decimal':
                        $type = 'decimal';
                        $decimal = explode(',',$param[1]);
                        $decimal[0] = (int)trim($decimal[0]);
                        $decimal[1] = (int)trim($decimal[1]);
                        break;
                    case 'enum':
                        $type = 'enum';
                        $enum = explode(',',$param[1]);
                        $enum[0] = (string)trim($enum[0]);
                        $enum[1] = (string)trim($enum[1]);
                        break;
                    default: throw new \Exception("Tipul de date pentru coloana '$column' este invalid.");
                }

                if ($property['required'] == 1) {
                    if (isset($length) && !empty($length)) {
                        $tab->$type($column, $length);
                        unset($length);
                    }elseif(isset($decimal) && !empty($decimal) && is_array($decimal)){
                        $tab->$type($column,$decimal[0],$decimal[1]);
                        unset($decimal);
                    }elseif(isset($enum) && !empty($enum) && is_array($enum)){
                        $tab->$type($column,$enum)->default($enum[0]);
                        unset($enum);
                    }else {
                        if($property['type'] == 'select'){
                            if ($this->table['config']['functionRecursive'] == 1){
                                $column = 'parent';
                            }
                            $tab->integer($column, false,true)->nullable();
                        }else{
                            $tab->$type($column);
                        }
                    }
                } else {
                    if (isset($length) && !empty($length)) {
                        $tab->$type($column, $length)->nullable();
                        unset($length);
                    }elseif(isset($decimal) && !empty($decimal) && is_array($decimal)){
                        $tab->$type($column,$decimal[0],$decimal[1])->nullable();
                        unset($decimal);
                    }elseif(isset($enum) && !empty($enum) && is_array($enum)){
                        $tab->$type($column,[ $enum[0], $enum[1] ])->nullable();
                        unset($enum);
                    }else {
                        if($property['type'] == 'select'){
                            $tab->$type($column, false,true)->nullable();
                        }else{
                            $tab->$type($column)->nullable();
                        }
                    }
                }
            }
            if($this->table['config']['functionSetOrder'] === 1){
                $tab->integer('order',false,true)->nullable();
            }
            if($this->table['config']['functionVisible'] === 1){
                $tab->tinyInteger('visible')->default(1);
            }
            $tab->string('slug',350);
            $tab->timestamps();
        });

        if($this->hasParents > 0){
            if(Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {

                    foreach($this->table['elements'] as $column => $property){
                        if($property['type'] == 'select' && $this->table['config']['functionRecursive'] != 1) {
                            $table->foreign($column)->references('id')->on($property['selectTable'])->onDelete('set null');
                        }
                    }

                    $table->index('slug');

                });
            }
        }

        return true;
    }

    /**
     * Creates a Model for the specified table
     *
     * @param $model
     * @return bool|int
     */
    private function makeModel($model)
    {
        $model = (string)trim($model);
        if ( ctype_alpha($model) !== true ){
            return false;
        }
        $model = ucfirst( strtolower($model) );
        $draft =<<<BOB
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class $model extends Model
{
    //
}
BOB;
        return file_put_contents("../app/$model".".php", $draft);
    }

    /**
     * Drops the specified table and deletes the corresponding settings in sys_core_setups
     *
     * @param Request $request
     * @param $idTable
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function delete(Request $request, $idTable)
    {
        $id = (int)trim($idTable);
        $tabela = Table::find($id);

        if( null == $tabela ){
            return redirect('admin/table-settings')
                ->with('aborted', "Definitia tabelei cu id-ul #$id nu exista in 'sys_core_setups'.");
        }

        $flag = $tabela->name.' NU';
        $deSters = trim($tabela->table_name);
        if( Schema::hasTable($deSters) && !in_array($deSters, $this->forbiddenTables)){
            $flag = $tabela->name;
            Schema::dropIfExists($deSters);
        }

        if (Cache::has('core_'.trim($deSters))) {
            Cache::forget( 'core_'.trim($deSters) );
        }

        $model = "../app/".trim($tabela->model).".php";
        if(file_exists($model)){
            if(unlink($model) === true){
                $flag .= ' (si modelul sau)';
            }
        }

        $tableImages = Image::where('table_id',$id)->pluck('name')->toArray();
        foreach( $tableImages  as $imageName ){
            if( ! Storage::disk('uploads')->exists($imageName) ) continue;
            Storage::disk('uploads')->delete($imageName);
        }

        $tabela->delete();

        $request->session()->flash('mesaj', "Tabela $flag a fost distrusa.");
        return redirect('admin/table-settings');
    }

    /**
     * Updates the order of tables
     *
     * @param Request $request
     * @param Table $tb
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function updateOrder(Request $request, Table $tb)
    {
        $tables = $tb::select('id', 'order')->get();
        $flag = 0;
        foreach ($tables as $table) {
            $order = 'order_'.$table->id;

            if ($request->has($order) && $table->order != $request->$order && $request->$order >= 0){
                if (ctype_digit((string)trim($request->$order)) !== true) {
                    continue;
                }
                $table->order = (int)trim($request->$order);
                $table->save();
                ++$flag;
            }
        }
        $mesaj = ($flag === 0)? 'Ordinea tabelelor a ramas identica.':"{$flag} tabele au ordinea schimbata";
        $request->session()->flash('mesaj',$mesaj);
        return redirect('admin/table-settings');

    }

    /**
     * Returns a view for editing a table
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function edit(Request $request, $id)
    {
        if (filter_var($id, FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])) {
            $id = (int)$id;
        }else{
            $request->session()->flash('mesaj','Id-ul tabelei nu are formatul corespunzator.');
            return redirect('admin/table-settings');
        }

        $table = Table::findOrFail($id);
        $settings = unserialize($table->settings);

        //dd($settings);

        $options = $this->selectTablesOptions();
        $types = $this->selectTypes();

        return view('decoweb::admin.tables.edit',[
            'table'     => $table,
            'settings'  => $settings,
            'tabele'    => $options,
            'types'     => $types,
        ]);
    }

    /**
     * 1) Updates the configuration of the specified table in sys-core-setups
     * 2) Modifies the column structure of the table (by calling a private method)
     *
     * @param Request $request
     * @param         $id
     */
    public function update(Request $request, Table $table)
    {
        //dd($request->all());
        # 1) Identify the table existence in sys-core-setups
        if( ! $table ){
            return 'Tabela nu exista in sys_core_setups';
        }

        # 2) Unserialize table's settings
        $setup = unserialize($table->settings);

        $tableColumns = array_keys($setup['elements']); // stabilim care sunt coloanele existente
            // Daca in $request apar coloane noi, trebuie adaugate
            // Daca in $request nu apar dintre cele vechi, trebuie

        foreach( $setup as $section => $attributes){
            if( $section == 'config' || $section == 'messages'){
                foreach($attributes as $attribute=>$value) {
                    if ( $request->has($attribute) ) {
                        $setup[$section][$attribute] = $request->$attribute;
                    }
                }
            }elseif( $section == 'filter' ){
                if( $request->has('filter') && ! empty($request->filter) ){
                    $setup['filter'] = explode(',',trim(str_replace(' ','',$request->filter)));
                }
            }elseif( $section == 'elements'){
                if( $request->has('elements') && count($request->elements) != 0 ){
                    $setup = $this->storeColumnsInfo($request, $setup);
                }
            }
        }
        dd($setup);
        dd($tableColumns);
        dd($request->all());
    }

    private function selectTablesOptions()
    {
        $allTables = Table::select('id','name','table_name')->get();
        $selectOptions[] = '';
        foreach($allTables as $t){
            $selectOptions[$t->table_name] = $t->name . " ($t->table_name)";
        }
        return $selectOptions;
    }

    private function selectTypes()
    {
        $types = ['text','textarea','editor','select','checkbox','calendar'];
        $options = [];
        foreach ($types as $type){
            $options[$type] = $type;
        }
        return $options;
    }

    /**
     * @param Request $request
     * @param         $table
     * @return mixed
     */
    private function storeColumnsInfo(Request $request, $table)
    {
        foreach ($request->elements as $element) {
            $required = (int) $element['required'];
            $table['elements'][$element['databaseName']] = ['friendlyName' => $element['friendlyName'], 'type' => $element['type'], 'required' => $required, 'colType' => $element['colType'],];
            if ( trim($element['type']) == 'text' ) {
                $readonly = (isset($element['readonly'])) ? (int) $element['readonly'] : 0;
                $table['elements'][$element['databaseName']]['readonly'] = $readonly;
            }
            if ( trim($element['type']) == 'select' ) {
                $multiple = (isset($element['selectMultiple'])) ? (int) $element['selectMultiple'] : 0;
                $first = (isset($element['selectFirstEntry'])) ? trim($element['selectFirstEntry']) : '';
                $extra = (isset($element['selectExtra'])) ? trim($element['selectExtra']) : '';

                $table['elements'][$element['databaseName']]['selectMultiple'] = $multiple;
                $table['elements'][$element['databaseName']]['selectFirstEntry'] = $first;
                $table['elements'][$element['databaseName']]['selectTable'] = trim($element['selectTable']);
                $table['elements'][$element['databaseName']]['selectExtra'] = $extra;
            }
        }
        return $table;
    }
}


























