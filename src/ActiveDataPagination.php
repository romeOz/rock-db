<?php

namespace rock\db;


use rock\base\ObjectInterface;
use rock\base\ObjectTrait;
use rock\helpers\Instance;
use rock\helpers\Link;
use rock\helpers\Pagination;
use rock\request\Request;
use rock\sanitize\Sanitize;
use rock\url\Url;

/**
 * @property-read int $pageCount total count pages
 * @property-read int $pageStart page start
 * @property-read int $pageEnd page end
 * @property-read int $pagePrev prev page
 * @property-read int $pageNext next page
 * @property-read int $pageFirst first page
 * @property-read int $pageLast last page
 * @property-read array $pageDisplay list display pages
 * @property-read int $countMore count more items
 * @property-read int $offset integer the offset of the data. This may be used to set the
 * OFFSET value for a SQL statement for fetching the current page of data.
 *
 * @package rock\db
 */
class ActiveDataPagination implements ObjectInterface, \ArrayAccess
{
    use ObjectTrait;

    const LINK_NEXT = 'next';
    const LINK_PREV = 'prev';
    const LINK_FIRST = 'first';
    const LINK_LAST = 'last';

    /**
     * @var string name of the argument storing the current page index.
     */
    public $pageArg = 'page';
    /**
     * Current page.
     * @var int
     */
    public $page;
    /**
     * @var integer total number of items.
     */
    public $totalCount = 0;
    /**
     * Items limit.
     * @var int
     */
    public $limit = Pagination::LIMIT;
    /**
     * Sorting pages.
     * @var int
     */
    public $sort = Pagination::SORT;
    /**
     * Page limits.
     * @var int
     */
    public $pageLimit = Pagination::PAGE_LIMIT;
    /**
     * @var Request
     */
    public $request = 'request';
    /**
     * @var \rock\url\Url
     */
    public $urlBuilder = 'url';

    protected $data = [];

    public function init()
    {
        $this->request = Instance::ensure($this->request, '\rock\request\Request', false);
        if (!$this->urlBuilder instanceof Url) {
            if (class_exists('\rock\di\Container')) {
                $this->urlBuilder =  \rock\di\Container::load($this->urlBuilder);
            } elseif(class_exists('\rock\url\Url')) {
                $this->urlBuilder = new Url(null, is_array($this->urlBuilder) ? $this->urlBuilder : []);
            }
        }
        $this->calculate();
    }

    public function __get($name)
    {
        if ($name === 'pageDisplay') {
            return isset($this->data[$name]) ? $this->data[$name] : [];
        }
        return isset($this->data[$name]) ? $this->data[$name] : 0;
    }

    public function offsetGet($name)
    {
        return $this->$name;
    }

    public function offsetSet($name, $value)
    {
        $this->$name = $value;
    }

    public function offsetExists($name)
    {
        return isset($this->data[$name]);
    }

    public function offsetUnset($name)
    {
        throw new DbException(DbException::SETTING_READ_ONLY_PROPERTY, ['class' => __CLASS__, 'property' => $name]);
    }

    /**
     * Returns the zero-based current page number.
     * @param boolean $recalculate whether to recalculate the current page based on the page size and item count.
     * @return integer the zero-based current page number.
     */
    public function getPage($recalculate = false)
    {
        if ($this->page === null || $recalculate) {
            if (isset($this->request) && class_exists('\rock\rock\sanitize')) {
                $page = Request::get($this->pageArg, 0, Sanitize::positive()->int());
            } else {
                $page = (int)isset($_GET[$this->pageArg]) ? $_GET[$this->pageArg] : 0;
                if ($page < 0) {
                    $page = 0;
                }
            }
            $this->page = $page;
        }

        return $this->page;
    }

    /**
     * Sets the current page number.
     * @param integer $value the zero-based index of the current page.
     */
    public function setPage($value)
    {
        $this->page = $value;
        $this->calculate(true);
    }

    /**
     * Creates the URL suitable for pagination with the specified page number.
     * This method is mainly called by pagers when creating URLs used to perform pagination.
     * @param integer $page the zero-based page number that the URL should point to.
     * @param boolean $absolute whether to create an absolute URL. Defaults to `false`.
     * @return string the created URL
     */
    public function createUrl($page, $absolute = false)
    {
        if (!$this->urlBuilder instanceof \rock\url\Url) {
            return '';
        }
        $page = (int)$page;
        $this->urlBuilder->addArgs([$this->pageArg => $page]);
        if ($absolute) {
            return $this->urlBuilder->getAbsoluteUrl(true);
        }

        return $this->urlBuilder->getRelativeUrl(true);

    }

    /**
     * Returns a whole set of links for navigating to the first, last, next and previous pages.
     * @param boolean $absolute whether the generated URLs should be absolute.
     * @return array the links for navigational purpose.
     * The array keys specify the purpose of the links (e.g. {@see \rock\db\ActiveDataPagination::LINK_FIRST}),
     * and the array values are the corresponding URLs.
     */
    public function getLinks($absolute = false)
    {
        if (!$this->urlBuilder instanceof \rock\url\Url) {
            return [];
        }
        return [
            Link::REL_SELF => $this->createUrl($this->getPage(),$absolute),
            self::LINK_FIRST => $this->createUrl($this->pageFirst, $absolute),
            self::LINK_PREV =>$this->createUrl($this->pagePrev, $absolute),
            self::LINK_NEXT =>$this->createUrl($this->pageNext,$absolute),
            self::LINK_LAST => $this->createUrl($this->pageLast, $absolute),
        ];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }

    protected function calculate($recalculate = false)
    {
        if (empty($this->data) || $recalculate) {
            $this->data = Pagination::get(
                $this->totalCount,
                $this->getPage(),
                $this->limit,
                $this->sort,
                $this->pageLimit
            );
        }
    }
}