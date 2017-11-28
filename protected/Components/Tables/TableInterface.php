<?php

namespace App\Components\Tables;

use App\Components\Sql\SqlFilter;
use T4\Core\Std;

interface TableInterface
{
    /**
     * @return TableConfigInterface
     * return entire table config
     */
    public function tableConfig() :TableConfigInterface;

    /**
     * @return Std
     * return Std config for jqTable script
     */
    public function buildTableConfig();

    /**
     * @return mixed
     */
    public function buildJsonTableConfig();

    /**
     * @return Std
     */
    public function columnsConfig() :Std;
    public function columnsNames() :array;
    public function columnsTitles() :array;

    /**
     * @param int|null $limit
     * @param int|null $offset
     * @return mixed
     *
     * return set of records (like array or Collection?)
     */
    public function getRecords(int $limit = null, int $offset = null);
    public function getRecordsByPage(int $pageNumber);

    /**
     * @param int|null $pageNumber
     * @return mixed
     * get/set current page number
     */
    public function currentPageNumber(int $pageNumber = null);

    /**
     * @param int|null $rows
     * @return mixed
     * get/set rows per page
     */
    public function rowsOnPage(int $rows = null);

    /**
     * @return self
     *
     * recalculate pagination according current pagination settings
     */
    public function updatePagination();

    /**
     * @return array return current sort order
     */
    public function currentSortOrder() :array ;
    public function setSortOrder(string $columnName, $direction);

    /**
     * @param SqlFilter $filter
     * @param $appendMode - 'replace', 'append' or 'ignore'
     * @return mixed
     * add operation filter. It doesn't  save in config. and can't rewrite table's preFilter
     *
     */
    public function addFilter(SqlFilter $filter, string $appendMode);
    public function removeFilter(SqlFilter $filter);
    public function clearFilters();

    /**
     * @param string $column
     * @param string $method
     * @return mixed
     *
     * calculate by column using $method. Useful for gather stat info per column
     */
    public function calculateByColumn(string $column, string $method);
}