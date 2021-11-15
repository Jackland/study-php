## 部署

### 1.添加`env.php`配置文件
在当前目录打开`cmd`或者终端
 - Windows-CMD
```
copy env.example.php env.php
```
 - Windows-powershell / Git-bash / Linux-终端
```
cp env.example.php env.php
```

### 2.安装组件 (需要安装 composer )
在当前目录打开`cmd`或者终端
```cmd
cd system
composer install
```
然后添加以`DB_`开头的各项配置，其他配置可以在使用的时候再加。

注：composer 下载地址
 https://getcomposer.org/Composer-Setup.exe

## 前端

### 1、关于全局layui样式调整(Date: 2020-09-29)
```
        现有全局样式维护文件  catalog/view/javascript/layer/theme/yzc/style.css
    因产品要求统一全局弹框等layui样式，现统一使用catalog/view/javascript/layer/theme/yzc/layuiOris.css样式，请新增页面或者收到修改弹框等layui样式自行引入该文件。
        另外，维护layuiOris.css虚谨慎！
```

### 全局替换iconfont步骤（Date: 2021-01-19）
- iconfont官网下载资源 [link](https://www.iconfont.cn/manage/index?spm=a313x.7781069.1998910419.12&manage_type=myprojects&projectId=1474765&keyword=&project_type=&page=)
- 解压下载至本地资源库
- 替换文件
```
    替换文件路径 /yzc/public/fonts/giga/* (六个文件)
```
- 版本号标记
```
    修改文件：'yzc/config/common.php' 中 'APP_VERSION' 为最新日期年月日为版本号；
```
