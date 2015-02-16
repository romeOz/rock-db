<?php
namespace rock\db;


use rock\base\ObjectInterface;
use rock\base\ObjectTrait;
use rock\components\Model;
use rock\helpers\ArrayHelper;
use rock\helpers\Helper;
use rock\helpers\Instance;
use rock\helpers\Pagination;
use rock\response\Response;
use rock\url\Url;

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
 *         'pageCurrent' => (int)$_GET['page']
 *     ],
 * ]);
 *
 * $provider->get(); // returns list items in the current page
 * $provider->getPagination(); // returns data pagination
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
 *         'pageCurrent' => (int)$_GET['page'],
 *     ],
 * ]);
 *
 * $provider->get(); // returns list items in the current page
 * $provider->getPagination(); // returns data pagination
 * ```
 *
 */
class ActiveDataProvider implements ObjectInterface
{
    use ObjectTrait;

    /**
     * Source. Can be array or Model.
     * @var array|QueryInterface
     */
    public $query;
    /** @var  \rock\db\Connection|\rock\mongodb\Connection */
    public $connection;
    /**
     * List data pagination.
     * @var array
     */
    public $pagination;
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
    /** @var  Response|string|array */
    public $response = 'response';
    /**
     * List data pagination.
     * @var array
     */
    protected $dataPagination = [];
    /**
     * @var int
    */
    protected $totalCount = 0;
    private $_keys;

    public function init()
    {
        if (!isset($this->pagination['pageCurrent'])) {
            $this->pagination['pageCurrent'] = 0;
        }
        
        $this->response = Instance::ensure($this->response, '\rock\response\Response', false);
    }

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
     * @param bool $subAttributes
     * @return array
     */
    public function get($subAttributes = false)
    {
        if (empty($this->query)) {
            return [];
        }

        $result = [];
        if (is_array($this->query)) {
            $result = $this->prepareArray();
        } elseif ($this->query instanceof QueryInterface) {
            $result = $this->prepareModels($subAttributes);
        }

        return $this->prepareDataWithCallback($result);
    }

    public function toArray($subAttributes = false)
    {
        if (empty($this->query)) {
            return [];
        }

        $firstElement = null;
        if (is_array($this->query)) {
            $data = $this->prepareArray();
        } elseif ($this->query instanceof QueryInterface) {
            $data = $this->prepareModels($subAttributes);
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
     * Returns data pagination.
     *
     * @return array
     */
    public function getPagination()
    {
        return $this->dataPagination;
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
            return $this->query;
        }
        $this->calculatePagination();

        $result = array_slice($this->query, $this->dataPagination['offset'], $this->dataPagination['limit'], true);
        if ($this->_keys === null) {
            $this->_keys = $this->prepareKeys($result);
        }
        return $result;
    }


    /**
     * @param bool       $subAttributes
     * @return ActiveRecord|\rock\sphinx\ActiveRecord
     */
    protected function prepareModels($subAttributes = false)
    {
        if (!$this->totalCount = $this->calculateTotalCount()) {
            return [];
        }
        $this->calculatePagination();
        $this->addHeaders($this->totalCount, $this->dataPagination);

        $result = $this->query
            ->limit($this->dataPagination['limit'])
            ->offset($this->dataPagination['offset'])
            ->all($this->connection, $subAttributes);
        if ($this->_keys === null) {
            $this->_keys = $this->prepareKeys($result);
        }
        return $result;
    }

    protected function calculatePagination()
    {
        $this->dataPagination = Pagination::get(
            $this->totalCount,
            $this->pagination['pageCurrent'],
            Helper::getValue($this->pagination['limit'], Pagination::LIMIT),
            Helper::getValue($this->pagination['sort'], Pagination::SORT),
            Helper::getValue($this->pagination['pageLimit'],Pagination::PAGE_LIMIT)
        );
        if (isset($this->pagination['pageArgUrl'])) {
            $this->dataPagination['pageArgUrl'] = $this->pagination['pageArgUrl'];
        }
    }

    protected function addHeaders($total, array $data)
    {
        if (!$this->response instanceof Response || $this->response->format == Response::FORMAT_HTML || empty($data)) {
            return;
        }

        $absoluteUrl = class_exists('\rock\url\Url')
            ? Url::set()->removeAllArgs()->getAbsoluteUrl(true)
            : $_SERVER['REQUEST_URI'] . '?' . $_SERVER['QUERY_STRING'];
        $links = [];
        $links[] = "<{$absoluteUrl}?{$data['pageArgUrl']}={$data['pageCurrent']}>; rel=self";
        $this->response->content['_links']['self'] = "{$absoluteUrl}?{$data['pageArgUrl']}={$data['pageCurrent']}";
        if (!empty($data['pagePrev'])) {
            $links[] = "<{$absoluteUrl}?{$data['pageArgUrl']}={$data['pagePrev']}>; rel=prev";
            $this->response->content['_links']['prev'] = "{$absoluteUrl}?{$data['pageArgUrl']}={$data['pagePrev']}";
        }
        if (!empty($data['pageNext'])) {
            $links[] = "<{$absoluteUrl}?{$data['pageArgUrl']}={$data['pageNext']}>; rel=next";
            $this->response->content['_links']['next'] = "{$absoluteUrl}?{$data['pageArgUrl']}={$data['pageNext']}";
        }
        if (!empty($data['pageFirst'])) {
            $links[] = "<{$absoluteUrl}?{$data['pageArgUrl']}={$data['pageFirst']}>; rel=first";
            $this->response->content['_links']['first'] = "{$absoluteUrl}?{$data['pageArgUrl']}={$data['pageFirst']}";
        }
        if (!empty($data['pageLast'])) {
            $links[] = "<{$absoluteUrl}?{$data['pageArgUrl']}={$data['pageLast']}>; rel=last";
            $this->response->content['_links']['last'] = "{$absoluteUrl}?{$data['pageArgUrl']}={$data['pageLast']}";
        }

        $this->response->getHeaders()
            ->set('X-Pagination-Total-Count', $total)
            ->set('X-Pagination-Page-Count', $data['pageCount'])
            ->set('X-Pagination-Current-Page', $data['pageCurrent'])
            ->set('X-Pagination-Per-Page', $data['limit'])
            ->set('Link', implode(', ', $links));
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

            return $keys;
        } else {
            return array_keys($models);
        }
    }
}
