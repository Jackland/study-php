# Url

## 使用

以下写法结果一致：

```php
$url = url(); // 推荐

echo $url->link('account/account', 'a=b&c=f'); // 极不建议使用
echo $url->link('account/account', ['a' => 'b', 'c' => 'f']);
echo $url->to(['account/account', 'a' => 'b', 'c' => 'f']); // 建议使用
echo url(['account/account', 'a' => 'b', 'c' => 'f']); // 建议
// 构建形式
echo $url
    ->withRoute('account/account')
    ->withCurrentQueries()
    ->withQueries(['a' => 'b', 'x' => 'y'])
    ->withoutQueries(['a', 'x'])
    ->build();
```

twig 中使用

```twig
# 不带参数
{{ url('account/account') }}
带参数
{{ url('account/account', {'a': 'b'}) }}
```

## 后续需求跟进

- url::current() 方法目前未获取到完整的 url 地址，可能会存在部分地方的影响

- 系统中存在很多使用 link 时U对 url 进行 `str_replace('&amp;', '&', $url)` 的操作，考虑是否有存在的必要