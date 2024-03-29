<?php

namespace App\Components\Tables;

use App\Components\Sql\SqlFilter;
use T4\Core\Std;

interface TableInterface
{
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
     * @param string|null $class
     * @param bool $distinct
     * @return mixed return set of records (like array or Collection?)
     *
     * return set of records (like array or Collection?)
     */
    public function getRecords(int $limit = null, int $offset = null, string $class = null, $distinct = false);
    public function getRecordsByPage(int $pageNumber, string $class = null, $distinct = false);
    public function selectStatement(int $offset = null, int $limit = null, $distinct = false);

    /**
     * @param int|null $pageNumber
     * @return mixed
     * get/set current page number
     */
    public function currentPage(int $pageNumber = null);

    /**
     * @param int|null $rows
     * @return mixed
     * get/set rows per page
     */
    public function rowsOnPage(int $rows = null);

    public function numberOfPages();

    /**
     * @param $currentPage
     * @param $rowsOnPage
     * @return TableInterface
     *
     * set pagination parameters and recalculate
     */
    public function paginationUpdate($currentPage = null, $rowsOnPage = null);

    /**
     * @return array return current sort order
     */
    public function currentSortOrder();
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

    public function countStatement();

    /**
     * @return int return number of records in table with current settings of filters
     */
    public function countAll();
}