# dev

- 新增前端代码库分离的方式介绍，详见【框架使用/23.前端代码库分离-yzc_front.md】

# 2021-07-16

- console 的执行进行安全控制，详见【config/__safe_command.php】
- 新增 PDF 和 条形码（barcode）生成器，详见【框架使用/22.PDF-条形码Barcode生成器.md】
- 新增前端动态组件，详见【代码规范/15.前端视图(Twig)中组件的使用规范.md】
- request 支持批量获取 get/post/json/file/header/server 中的值，详见【框架使用/4.请求响应-Request-Response】
- 新增远程接口调用使用规范，详见【代码规范/16.远程接口调用(RemoteAPI)使用规范.md】

# 2021-07-01
- 新增前端组件示范文件夹
- 新增前端通用新组件示例
- 规范前端新页面创建模板
- 规范前端Nav导航加载方式，默认统一使用Ajax方式加载Tab

# 2021-05-26

- twig 中的对 js 变量的优化使用，详见【代码规范/14.前端视图(Twig)中js中的变量使用规范.md】
- request()->isAjax() 可以完全判断是否来自前端的 ajax 请求
- console make:model 现在会自动调用一次 ide-helper:models 将注释更新到模型上，无需再次调用
- redis 固定使用 predis，防止在不同环境下 redis 命令的不统一
- 新增唯一值生成器组件，详见【框架使用/20.唯一值生成器-UniqueGenerator.md】
- 统一修改 render 第三个参数的 layout 的配置到 LayoutFactory，配置详见 `AppServiceProvider::solvingView`
- 新增短信组件，详见【框架使用/21.短信发送-SMS.md】

# 2021-05-13

- log 支持同时拆分写入和统一写入同一个文件，同时增加 uid，便于 ES 收集
- 调整 date_timezone 的设置到应用启动前，保证系统中所有时区使用正确
- Eloquent 支持通过 queryRead() 直接查询或操作读库，例如：`Customer::queryRead()`

# 2021-04-19

- 所有的 Provider 改为 ServiceProvider，重新优化了所有 ServiceProvider 的加载形式，
  支持 defer 加载，解决各组件之间的依赖问题
- 调整了 framework.php 和 console 的入口代码（Kernel-Application 形式）
- 新的 Http 路由的处理逻辑迁移到 Framework\Route\OcRouter 下
- 重构 Application，框架中的 Framework::App 废弃，旧的暂时保留，后续不再使用，
  所有使用类似 App::session() 或 App::orm() 形式的代码全部改用 session() 或 db() 形式直接使用
- console 修改为同时支持 laravel artisan 和 symfony console，默认为 artisan，一般的 laravel 命令可以迁移到当前项目下 ，
  通过在 App\Commands\Kernel::commands() 可以配置第三方的 laravel 命令（可能部分需要做适配）
- ide-helper 的 command 支持 ide-helper:meta 和 ide-helper:models，
  app() 获取组件的形式可以做到代码提示，新增服务后记得重新执行 ide-helper:meta 更新 .phpstorm.meta.php
- 视图组件 Widget 优化，增加 make:widget 命令，支持 render 视图的形式，详见【框架使用/17.视图组件-Widget.md】
- 新增静态资源包的使用说明，详见【框架使用/18.静态资源包-AssetBundle.md】
- debugbar 支持查看历史
- 优化 debugbar 加载的 js/css
- 优化 Url，增强 url 的使用，详见【框架使用/19.路由地址-URL.md】

# 2021-02-26

- 新增 twig 视图的 layout 模式，重新整理 twig 的使用规范，详见【代码规范/13.前端视图(Twig)的使用规范.md】
- 新增 laravel 事件的组件，详见【框架使用/16.事件-Event.md】
