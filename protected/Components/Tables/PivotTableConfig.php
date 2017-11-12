<?php
/**
 * Created by PhpStorm.
 * User: Dmitry
 * Date: 09.11.2017
 * Time: 11:21
 */

namespace App\Components\Tables;


use App\Components\Sql\SqlFilter;
use T4\Core\Config;
use T4\Core\Exception;
use T4\Core\Std;
use T4\Orm\Model;

class PivotTableConfig extends Config
    implements PivotTableConfigInterface
{
    const BASE_CONF_PATH = ROOT_PATH . DS . 'Configs' . DS;

    protected $columnPropertiesTemplate = [
        'id' => '',
        'title' => '',
        'width' => 0,
        'sortable' => false,
        'filterable' => false
    ];
    /**
     * @var array
     *
     * 'preFilter' - preFilter for pivot column values
     * 'sortBy' - sort columns and directions for pivot column ['column_1' => 'asc|desc', 'column_N' => 'asc|desc']
     * 'width' - width for each column from pivot columns set.
     *      If set in 'px' (ie '20px'), pivot column's width from columns properties will be ignored.
     *      If set in percents (ie 20), width for each column will be calculated by width from columns properties
     */
    protected $pivotColumnPropertiesTemplate = [
        'preFilter' => [], //preFilter for pivot column values
        'sortBy' => [], //sort columns and directions for pivot column ['column_1' => 'asc|desc', 'column_N' => 'asc|desc']
        'width' => 0, //width for each column from pivot values.
    ];

    public function __construct(string $tableName, string $class = null)
    {
        parent::__construct();
        if (empty($tableName)) {
            throw new Exception('Table name can not be empty');
        }
        $path = self::BASE_CONF_PATH . $tableName;
        /* if class is not set try to load existing config */
        if (empty($class)) {
            $this->load($path);
        } elseif (! class_exists($class)) {
            throw new Exception('Class ' . $class . ' is not exists');
        } elseif (get_parent_class($class) != Model::class) {
            throw new Exception('Class for table must extends Model class');
        } else {
            $this->setPath($path);
            $this->className = $class;
        }
    }

    /**
     * @param array $columns only columns names
     * All columns names have to belong a class that specified in construct method
     * @return mixed if $columns is null - return columns array for current table
     *
     * if $columns is null - return columns array for current table
     * if $columns is array - set columns from this array for current table
     * this method should be called first
     * @throws Exception
     */
    public function columns(array $columns = null)
    {
        if (is_null($columns)) {
            return $this->columns;
        }
        $classColumns = array_keys($this->className::getColumns());
        $diff = array_diff($columns, $classColumns);
        if (count($diff) > 0) {
            throw new Exception('columns have to belong ' . $this->className::getTableName() . ' table!');
        }
        $columns = array_fill_keys($classColumns, $this->columnPropertiesTemplate);
        $this->columns = new Config($columns);
        return $this;
    }


    /**
     * @param string $column
     * @return Std set column as pivot / get params this column
     * set column as pivot / get params this column
     * @throws Exception
     */
    public function setPivotColumn(string $column)
    {
        if (! $this->isColumnSet($column)) {
            throw new Exception('Before set column as pivot define it as table column');
        }
        $this->pivot = new Config([$column => $this->pivotColumnPropertiesTemplate]);
        return $this->pivot->$column;
    }

    /**
     * @param string $pivotColumn
     * @param SqlFilter|null $preFilter
     * @return Std return summary prefilter for column
     * set/get prefilter for decided pivot column
     */
    public function pivotPreFilter(string $pivotColumn, SqlFilter $preFilter = null) :Std
    {
        $this->validatePivotColumn($pivotColumn);
        if (is_null($preFilter)) {
            return $this->pivot->$pivotColumn->preFilter;
        }
        $this->pivot->$pivotColumn->preFilter = new Config($preFilter->toArray());
        return $this->pivot->$pivotColumn->preFilter;
    }

    /**
     * @param string $pivotColumn
     * @param array $sortColumns
     * @param string $direction
     * @return Std sort columns as property, direction as values
     * set/get sort columns and direction
     * @throws Exception
     */
    public function pivotSortBy(string $pivotColumn, array $sortColumns = null, string $direction = '') :Config
    {
        $this->validatePivotColumn($pivotColumn);
        if (is_null($sortColumns)) {
            return $this->pivot->$pivotColumn->sortBy;
        }
        $this->validateSortDirection($direction);
        $this->isAllColumnsDefined($sortColumns);
        $this->pivot->$pivotColumn->sortBy = new Config(array_fill_keys($sortColumns, strtolower($direction)));
        return $this->pivot->$pivotColumn->sortBy;
    }

    /**
     * @param string $pivotColumn
     * @param string|integer $width
     * @return int|string width each item of columns based on pivot column
     * @throws Exception
     */
    public function widthPivotItems(string $pivotColumn, $width = null)
    {
        $this->validatePivotColumn($pivotColumn);
        if (is_null($width)) {
            return $this->pivot->$pivotColumn->width;
        }
        if(!is_string($width) && !is_int($width)) {
            throw new Exception('Width has to be int like 10 or string like 10px');
        }
        if(is_numeric($width)) {
            //width set in percents
            $this->pivot->$pivotColumn->width = intval($width);
            return $this->pivot->$pivotColumn->width;
        } elseif(is_string($width) && substr(trim(strtolower($width)), -2) == 'px') {
            $this->pivot->$pivotColumn->width = trim(strtolower($width));
            return $this->pivot->$pivotColumn->width;
        } else {
            //Incorrect value of width
            throw new Exception('Width has to be int like 10 or string like 10px');
        }
    }

    /**
     * @return mixed
     * return columns config
     */
    public function allColumnsConfig(): Std
    {
    }

    /**
     * @param string $column
     * @param Std|null $config
     * @return Config if $config is null - return current config $column column
     * if $config is null - return current config $column column
     * if $config is Std - set config for $column column
     * @throws Exception
     */
    public function columnConfig(string $column, Std $config = null)
    {
        $this->isColumnSet($column);
        if (is_null($config)) {
            return $this->columns->$column;
        }
        $diff = array_diff(array_keys($config->toArray()), array_keys($this->columnPropertiesTemplate));
        if (count($diff) > 0) {
            throw new Exception('Some config parameters are not correct');
        }
        foreach ($config as $param => $value) {
            $this->validateConfigParam($param, $value);
            $config->$param = $this->sanitizeConfigParam($param, $value);
        }
        foreach ($config as $param => $value) {
            $this->columns->$column->$param = $value;
        }
        return $this->columns->$column;
    }

    public function sortOrderSets($sortSets = null)
    {
        // TODO: Implement sortOrderSets() method.
    }

    public function sortBy(string $sortTemplate, string $direction = '')
    {
        // TODO: Implement sortBy() method.
    }

    public function setTablePreFilter(SqlFilter $condition)
    {
        // TODO: Implement setPreFilter() method.
    }

    public function setFilter(SqlFilter $condition)
    {
        // TODO: Implement setFilter() method.
    }

    public function addFilter(SqlFilter $condition, $appendMode)
    {
        // TODO: Implement addFilter() method.
    }

    public function isPivot($column) :bool
    {
        return isset($this->pivot->$column);
    }
    public function isColumnSet($column) :bool
    {
        return isset($this->columns->$column);
    }

    protected function validatePivotColumn($column)
    {
        if (! $this->isPivot($column)) {
            throw new Exception('Column \'' . $column . '\' doesn\'t set as pivot');
        }
        return true;
    }

    protected function isAllColumnsDefined(array $columns)
    {
        $classColumns = array_keys($this->className::getColumns());
        $diff = array_diff($columns, $classColumns);
        if (count($diff) > 0) {
            throw new Exception('columns have to belong ' . $this->className::getTableName() . ' table!');
        }
        return true;
    }
    protected function isColumnDefined(array $columns)
    {
        $classColumns = array_keys($this->className::getColumns());
        $diff = array_diff($columns, $classColumns);
        if (count($diff) > 0) {
            throw new Exception('columns have to belong ' . $this->className::getTableName() . ' table!');
        }
        return true;
    }

    protected function validateSortDirection($direct)
    {
        $direct = strtolower($direct);
        if ('asc' == $direct || 'desc' == $direct || '' == $direct) {
            return true;
        } else {
            throw new Exception('Allowed sort direction is: \'asc\' or \'desc\'');
        }
    }

    protected function validateConfigParam($param, $val)
    {
        switch($param) {
            case 'id':
                if (! is_string($val)) {
                    throw new Exception('Not valid id');
                }
                break;
            case 'title':
                if (! is_string($val)) {
                    throw new Exception('Not valid title');
                }
                break;
            case 'width':
                if(is_numeric($val)) {
                    //width set in percents
                    return true;
                } elseif(is_string($val) && substr(trim(strtolower($val)), -2) == 'px') {
                    $val = substr($val, 0, strlen($val) - 2);
                    if (is_numeric($val)) {
                        return true;
                    }
                }
                throw new Exception('Invalid width value');
                break;
            case 'sortable':
                if (! is_bool($val)) {
                    throw new Exception('Invalid sortable value');
                }
                break;
            case 'filterable':
                if (! is_bool($val)) {
                    throw new Exception('Invalid filterable value');
                }
                break;
            default:
                throw new Exception('Unknown parameter \'' . $param . '\'');
        }
        return true;
    }

    protected function sanitizeConfigParam($param, $val)
    {
        switch($param) {
            case 'id':
                return trim($val);
                break;
            case 'title':
                return $val;
                break;
            case 'width':
                if(is_numeric($val)) {
                    //width set in percents
                    return intval($val);
                } elseif(is_string($val) && substr(trim(strtolower($val)), -2) == 'px') {
                    return trim(strtolower($val));
                } else {
                    return $val;
                }
                break;
            case 'sortable':
                return $val;
                break;
            case 'filterable':
                return $val;
                break;
            default:
                return $val;
        }
    }
}