<?php
namespace Decoweb\Panelpack\Helpers\Traits;

use Decoweb\Panelpack\Models\SysCoreSetup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

trait Core
{
    /**
     * Gets TABLE DATA from database.sys_core_setups & caches it forever
     *
     * @param string $tableName
     * @return mixed
     */
    public function getTableData(string $tableName)
    {
        $tableName = trim($tableName);

        $data = Cache::rememberForever('core_'.$tableName, function() use ($tableName) {

            $data = SysCoreSetup::table( $tableName );

            if( ! $data instanceof SysCoreSetup ) return false;

            return $data;
        });

        return $data;
    }

    public function findOrFail($id)
    {
        return SysCoreSetup::findOrfail($id);
    }

    /**
     * @param $data
     * @return false|mixed
     */
    protected function decodeSettings($data)
    {
        if($data === false) return false;
        return json_decode($data->settings, true);
    }

    protected function updateSettings(array $settings)
    {
        return json_encode($settings);
    }

    /**
     * @param string $tableName
     * @return false|mixed
     */
    public function getSettings(string $tableName)
    {
        return $this->decodeSettings($this->getTableData($tableName));
    }

    /**
     * Clear the cache of a table
     *
     * @param $tableName
     * @return bool
     */
    private function clearTableCoreCache($tableName)
    {
        $keys = [
            'core_'.trim($tableName),
        ];

        $count = 0;

        foreach ($keys as $key) {
            if (Cache::has($key)) {
                Cache::forget($key);
                $count++;
            }
        }
        return $count;
    }


    /**
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName)
    {
        return Schema::hasTable($tableName);
    }


    /**
     * @param string $tableName
     * @return bool
     */
    public function tableHasParentColumn(string $tableName)
    {
      return array_key_exists('parent', $this->getSettings($tableName)['elements'] );
    }

    /**
     * @param string $tableName
     * @return bool
     */
    public function tableHasImages(string $tableName)
    {
        $settings = $this->getSettings($tableName);
        return (bool)$settings['config']['functionImages'];
    }

    public function tableHasFiles( string $tableName)
    {
        $settings = $this->getSettings($tableName);
        return (bool)$settings['config']['functionFile'];
    }
}
