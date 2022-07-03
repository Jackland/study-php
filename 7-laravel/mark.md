## laravel 常见函数速记

1- data_get(); Arr::get()

```php
$my_arr = [
'a' => ['lower' => 'aa', 'upper' => 'AAA',],
'b' => ['lower' => 'bbb', 'upper' => 'BBBBB',],
];
//因此，我可以通过这样做得到更低的a。

data_get($my_arr, 'a.lower');
//而且你也做了以下事情。

Arr::get('a.lower');

```

2-laravel包 下载排行 https://learnku.com/laravel/t/2530/the-highest-amount-of-downloads-of-the-100-laravel-extensions-recommended
