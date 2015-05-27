<?php
namespace rock\db;


use rock\base\ObjectInterface;
use rock\base\ObjectTrait;
use rock\components\Model;
use rock\helpers\ArrayHelper;

/**
 * ActiveDataProvider implements a data provider based on {@see \rock\db\Query} and {@see \rock\db\ActiveQuery}.
 *
 * ActiveDataProvider provides data by performing DB queries using {@see \rock\db\ActiveDataProvider::$query }.
 *
 * The following is an example of using ActiveDataProvider to provide ActiveRecord instances:
 *
 * ```php
 * $provider = new ActiveDataProvider([
 *     'query' => Post::find(),
 *     'pagination' => [
 *         'limit' => 20,
 *         'sort' => SORT_DESC,
 *         'pageLimit' => 5,
 *         'page' => (int)$_GET['page']
 *     ],
 * ]);
 *
 * $provider->get(); // returns list items in the current page
 * $provider->getPagination(); // returns \rock\db\ActiveDataPagination
 * ```
 *
 * And the following example shows how to use ActiveDataProvider without ActiveRecord:
 *
 * ```php
 * $query = new Query;
 * $provider = new ActiveDataProvider([
 *     'query' => $query->from('post'),
 *     'pagination' => [
 *         'limit' => 20,
 *         'sort' => SORT_DESC,
 *         'pageLimit' => 5,
 *         'page' => (int)$_GET['page'],
 *     ],
 * ]);
 *
 * $provider->get(); // returns list items in the current page
 * $provider->getPagination(); // returns \rock\db\ActiveDataPagination
 * ```
 *
 */
class ActiveDataProvider implements ObjectInterface
{
    use ObjectTrait;

    /**
     * Source. Can be array or Model.
     * @var Query
     */
    public $query;
    /** @var  \rock\db\Connection|\rock\mongodb\Connection */
    public $connection;
    /**
     * List data pagination.
     * @var array
     */
    public $pagination = [];
    /**
     * Prepare list items.
     * @var callable
     */
    public $callback;
    public $only = [];
    public $exclude = [];
    public $expand = [];
    /**
     * @var string|callable the column that is used as the key of the data models.
     * This can be either a column name, or a callable that returns the key value of a given data model.
     *
     * If this is not set, the following rules will be used to determine the keys of the data models:
     *
     * - If {@see \rock\db\ActiveDataProvider::$query} is an {@see \rock\db\ActiveQuery} instance, the primary keys of {@see \rock\db\ActiveQuery::$modelClass} will be used.
     *
     * @see getKeys()
     */
    public $key;
    /**
     * Calculate sub-attributes (e.g `category.id => [category][id]`).
     * @var bool
     */
    public $subattributes = true;
    /**
     * @var int $fetchMode the result fetch mode. Please refer to [PHP manual](http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes. If this parameter is null, the value set in {@see \rock\db\Command::$fetchMode} will be used.
     */
    public $fetchMode;
    /**
     * Total count items.
     * @var int
     */
    protected $totalCount;
    /** @var  int[] */
    private $_keys;

    /**
     * Source as array.
     * @param array $array list items.
     * @return $this
     */
    public function setArray(array $array)
    {
        $this->query = $array;
        return $this;
    }

    /**
     * @return array
     */
    public function get()
    {
        if (empty($this->query)) {
            return [];
        }

        $result = [];
        if (is_array($this->query)) {
            $result = $this->prepareArray();
        } elseif ($this->query instanceof QueryInterface) {
            $result = $this->prepareModels($this->subattributes);
        }

        return $this->prepareDataWithCallback($result);
    }

    public function toArray()
    {
        if (empty($this->query)) {
            return [];
        }

        $firstElement = null;
        if (is_array($this->query)) {
            $data = $this->prepareArray();
        } elseif ($this->query instanceof QueryInterface) {
            $data = $this->prepareModels($this->subattributes);
        } elseif ($this->query instanceof ActiveRecordInterface) {
            $result = $this->prepareDataWithCallback($this->query->toArray($this->only, $this->exclude, $this->expand));
            if ($this->_keys === null) {
                $this->_keys = $this->prepareKeys($result);
            }
            return $result;
        } else {
            throw new DbException('Var must be of type array or instances ActiveRecord.');
        }

        if (!is_array($data)) {
            throw new DbException('Var must be of type array or instances ActiveRecord.');
        }

        reset($data);
        $firstElement = current($data);
        // as ActiveRecord[]
        if (is_array($data) && $firstElement instanceof ActiveRecordInterface) {
            return $this->prepareDataWithCallback(
                array_map(
                    function(Model $value){
                        return $value->toArray($this->only, $this->exclude, $this->expand);
                    },
                    $data
                )
            );
        }

        // as Array
        if (ArrayHelper::depth($data, true) === 0) {
            return $this->prepareDataWithCallback(ArrayHelper::only($data, $this->only, $this->exclude));
        }

        if (!empty($this->only) || !empty($this->exclude)) {
            return $this->prepareDataWithCallback(
                array_map(
                    function($value){
                        return ArrayHelper::only($value, $this->only, $this->exclude);
                    },
                    $data
                )
            );
        }

        return $this->prepareDataWithCallback($data);
    }

    /**
     * @var ActiveDataPagination
     */
    protected $activePagination;

    /**
     * Returns data pagination.
     *
     * @return ActiveDataPagination
     */
    public function getPagination()
    {
        if (!isset($this->activePagination)) {
            if (!isset($this->totalCount)) {
                $this->toArray();
            }
            $config = $this->pagination;
            $config['totalCount'] = (int)$this->totalCount;

            $this->activePagination = new ActiveDataPagination($config);
        }

        return $this->activePagination;
    }

    /**
     * Get total count items
     *
     * @return int
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }

    /**
     * Returns the key values associated with the data models.
     * @return array the list of key values corresponding.
     * is uniquely identified by the corresponding key value in this array.
     */
    public function getKeys()
    {
        if (!isset($this->_keys)) {
            $this->get();
        }
        return $this->_keys;
    }

    /**
     * @return array
     */
    protected function prepareArray()
    {
        if (!$this->totalCount = count($this->query)) {
            $this->totalCount = 0;
            return [];
        }
        if (empty($this->pagination)) {
            if ($this->_keys === null) {
                $this->_keys = $this->prepareKeys($this->query);
            }
            return $this->query;
        }
        $activePagination = $this->getPagination();

        $result = array_slice($this->query, $activePagination->offset, $activePagination->limit, true);
        if ($this->_keys === null) {
            $this->_keys = $this->prepareKeys($result);
        }
        return $result;
    }

    /**
     * @return ActiveRecord|\rock\sphinx\ActiveRecord
     */
    protected function prepareModels()
    {
        if (!$this->totalCount = $this->calculateTotalCount()) {
            return [];
        }
        $activePagination = $this->getPagination();

        $this->query
            ->limit($activePagination->limit)
            ->offset($activePagination->offset);
        $result = $this->fetchMode
            ? $this->query->createCommand($this->connection)->queryAll($this->fetchMode, $this->subattributes)
            : $this->query->all($this->connection, $this->subattributes);
        if ($this->_keys === null) {
            $this->_keys = $this->prepareKeys($result);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function calculateTotalCount()
    {
        $query = clone $this->query;

        return (int)$query->limit(-1)
            ->offset(-1)
            ->orderBy([])
            ->count('*', $this->connection);
    }

    protected function prepareDataWithCallback(array $data)
    {
        if (!$this->callback instanceof \Closure || empty($data)) {
            return $data;
        }
        foreach ($data as $name => $value) {
            $data[$name] = ArrayHelper::map($value, $this->callback);
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function prepareKeys($models)
    {
        $keys = [];
        if ($this->key !== null) {
            foreach ($models as $model) {
                if (is_string($this->key)) {
                    $keys[] = $model[$this->key];
                } else {
                    $keys[] = call_user_func($this->key, $model);
                }
            }

            return $keys;
        } elseif ($this->query instanceof ActiveQueryInterface) {
            /* @var $class \rock\db\ActiveRecord */
            $class = $this->query->modelClass;
            $pks = $class::primaryKey();
            if (count($pks) === 1) {
                $pk = $pks[0];
                foreach ($models as $model) {
                    if (!isset($model[$pk])) {
                        continue;
                    }
                    $keys[] = $model[$pk];
                }
            } else {
                foreach ($models as $model) {
                    $kk = [];
                    foreach ($pks as $pk) {
                        if (!isset($model[$pk])) {
                            continue;
                        }
                        $kk[$pk] = $model[$pk];
                    }
                    if (!empty($kk)) {
                        $keys[] = $kk;
                    }
                }
            }
        }
        return $keys ? : array_keys($models);
    }
}