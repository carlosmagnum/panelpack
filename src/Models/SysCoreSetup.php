<?php

namespace Decoweb\Panelpack\Models;

use Illuminate\Database\Eloquent\Model;

class SysCoreSetup extends Model
{
    protected $table = 'sys_core_setups';

    /**
     * @param       $query
     * @param       $tableName
     * @param array $columns
     * @return bool
     */
    public function scopeTable($query, $tableName, $columns = ['*'])
    {
        $tableName = (string)trim($tableName);

        if( !ctype_alpha($tableName)){
            return false;
        }
        return $query->where('table_name', $tableName)->first($columns);
    }
}
