# DI 依赖注入

## 使用 

在 controller 的 __construct 和 action 方法上，可以通过类名的方式直接注入需要使用的 model 和其他类

形如：

```php
public function __construct(ModelAccountCustomer $modelAccountCustomer) {}
```

对于注入 model 需要注意以下问题：

  - 变量名字不可任意起，有以下要求
  - 最简单的形式 A: `model/account/customer.php` => `$modelAccountCustomer`
  - 文件名存在下划线的情况 B: `model/account/customer_user.php` => `$modelAccountCustomerUser`
  - 存在大写的情况 C: `model/account/CustomerUser.php` => `$model_account_CustomerUser`
  - 下划线在目录上的情况 D: `model/account_user/customer` => 无法直接匹配
  
对于 C 和 D 的情况，可以（且建议）直接人工去 config/_di_map.php 下定义映射关系解决

## 后续需求跟进

- 暂无