Active data provider
----------------------

ActiveDataProvider provides data by performing DB queries using `rock\db\Query` and `rock\db\ActiveQuery`.


The following is an example of using it to provide ActiveRecord instances:

```php
$provider = new ActiveDataProvider([
    'query' => Post::find(),
    'pagination' => [
        'limit' => 20,
        'sort' => SORT_DESC,
        'pageLimit' => 5,
        'page' => (int)$_GET['page']
    ],
]);

$provider->get(); // returns list items in the current page
$provider->getPagination(); // returns data pagination
```

And the following example shows how to use ActiveDataProvider without ActiveRecord:

```php
$query = new Query;
$provider = new ActiveDataProvider([
     'query' => $query->from('post'),
     'pagination' => [
         'limit' => 20,
         'sort' => SORT_DESC,
         'pageLimit' => 5,
         'page' => (int)$_GET['page'],
     ],
]);

$provider->getModels(); // returns list items in the current page
$provider->getPagination(); // returns data pagination
```

Array data provider
-------------------

ArrayDataProvider implements a data provider based on a data array.


```php
$query = (new Query())->from('users');
$provider = new ActiveDataProvider([
     'allModels' => $query->all(),
     'pagination' => [
         'limit' => 20,
         'sort' => SORT_DESC,
         'pageLimit' => 5,
         'page' => (int)$_GET['page'],
     ],
]);

$provider->getModels(); // returns list items in the current page
$provider->getPagination(); // returns data pagination
```