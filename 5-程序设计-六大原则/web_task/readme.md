本项目使用PHP的`laravel`框架搭建,中文文档在[这里](https://learnku.com/docs/laravel/5.5/)

## 代码提交
 - 如果是修改bug，提交的描述 要以 `BUGFIX:`开头
 - 如果是正常开发，提交的描述 以 `DEVELOP:`开头

## 本地部署
#### 1. Clone
```
git clone http://192.168.0.17:3000/lesteryou/yzc_task_work.git
```
#### 2. 安装组件
```
composer install
```
#### 3. 添加`.env`配置
在当前目录打开`cmd`或者终端
 - Windows-CMD
```
copy .env.example .env
```
 - Windows-powershell / Git-bash / Linux-终端
```
cp .env.example .env
```
然后添加以`DB_`开头的各项配置，其他配置可以在使用的时候再加。

#### 4. 生成`APP_KEY`
```cmd
php artisan key:generate
```

## 本地开发
#### 1. Controller
 - 生成命令
```cmd
php artisan make:controller Test/ExampleController
```
 - 控制器按照模块划分，一个模块一个文件夹
 
 不要直接在`app/Http/Controllers`目录下创建单独的控制器，最好根据模块创建一个文件夹，然后在这个文件夹下创建控制器。比如 上一步中`Test`模块一个文件，然后创建一个Example控制器
 - 控制器文件、类都要以`Controller`结尾，比如`TestController`

#### 2. Model
 - 路径 `app/Models`,之所以放`app`目录下，是因为model不仅仅在`http`下使用
 - 生成命令
 ```cmd
php artisan make:model Models/Test/Example
```
 - `model` 同样要按照模块划分，一个模块一个文件夹(和`controller`同理)

 
## 线上部署
#### 1. Clone
```
git clone http://192.168.0.17:3000/lesteryou/yzc_task_work.git
```
#### 2. 安装组件
```
composer install --optimize-autoloader --no-dev
composer dump-autoload --optimize
```
#### 3. 添加`.env`配置
在当前目录打开`cmd`或者终端
 - Windows-CMD
```
copy .env.example .env
```
 - Windows-powershell / Git-bash / Linux-终端
```
cp .env.example .env
```
然后添加以`DB_`开头的各项配置，其他配置可以在使用的时候再加。

#### 4. 生成`APP_KEY`
```cmd
php artisan key:generate
```

#### 5. 优化加载
```cmd
 php artisan clear-compiled
 php artisan config:cache 
 php artisan optimize
```