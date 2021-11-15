# Controller 控制器

## 使用

分 BaseController 和 AuthController，分别用于不需要登录和需要登录的授权的

```php
// 以下 $this 指代 Controller

// 渲染视图，附带 load controller
return $this->render('account/guide', $data, [
    'footer' => 'common/footer',
    'header' => 'common/header',
    'column_left' => 'common/column_left',
    'column_notice' => 'information/notice/column_notice',
]);

// 单独渲染控制器
$data['footer'] = $this->renderCotroller('common/footer');

// 重定向
return $this->redirect(['account/account']);

// 返回json
return $this->json($data);
return $this->jsonSuccess('ok');
return $this->jsonFailed('error');
```

twig 中使用

```twig
# 不带参数
{{ url('account/account') }}
带参数
{{ url('account/account', {'a': 'b'}) }}
```

## 可以通过 Command 自动创建 Controller 和 view

详见 【框架使用/8.命令脚本-Command】

## 后续需求跟进

- 移除 load

- layout ？

- breadcrumbs

- document

- AuthController 下部分 method 不需要授权的情况