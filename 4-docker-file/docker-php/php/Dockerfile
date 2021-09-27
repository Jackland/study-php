FROM php:7.2.26-fpm
USER root 
COPY ./sources.list /etc/apt/sources.list
RUN apt-get update && apt-get install -y apt-utils zip unzip wget curl supervisor vim procps net-tools \
    libxml2-dev \
    && docker-php-source extract \
    #bz2 很少用到 bz2压缩文件的解压缩和读写
    #&& apt install -y libbz2-dev && docker-php-ext-install bz2 \
    #bcmath 偶尔使用 任意精度的数字操作
    && docker-php-ext-install bcmath \
    #calendar 日历扩展包含了简化不同日历格式间转换的函数
    && docker-php-ext-install calendar \
    #ctype 偶尔使用 默认安装 检测在当前的区域设定下（参见 setlocale()），一个字符或者字符串 是否仅包含指定类型的字符
    #&& docker-php-ext-install ctype \
    #curl 比较常用 默认安装 能够连接通讯各种服务器、使用各种协议。libcurl 目前支持的协议有 http、https、ftp、gopher、telnet、dict、file、ldap。 
    #libcurl 同时支持 HTTPS 证书、HTTP POST、HTTP PUT、 FTP 上传(也能通过 PHP 的 FTP 扩展完成)、HTTP 基于表单的上传、代理、cookies、用户名+密码的认证
    #&& apt install -y libcurl4-openssl-dev && docker-php-ext-install curl \
    #dba 很少用到 .DB文件的操作，数据库管理
    #&& docker-php-ext-install dba \
    #dom 较少用到 默认安装 通过dom api在xml、html等标记语言文档上进行操作
    #&& apt install -y libxml2-dev && docker-php-ext-install dom \
    #enchant 很少用到 各种拼写库的抽象层
    #&& apt install -y libenchant-dev libpspell-dev \
    #&& docker-php-ext-install enchant \
    #exif 很少用到 可交换图像信息 通过使用 exif 扩展，你可以操作图像元数据，从数码相机拍摄的图片文件中读取元数据。 通常 JPEG 和 TIFF 格式的图像文件都包含元数据
    #&& docker-php-ext-install exif \
    #fileinfo 比较少用 默认安装 文件信息 获取文件的内容类型以及编码
    #&& docker-php-ext-install fileinfo \
    #filter 比较常用 默认安装 数据过滤 
    #&& docker-php-ext-install filter \
    #gettext 几乎用不到 实现了NLS (Native Language Support) API，他可以用来国际化您的PHP程序.
    #&& docker-php-ext-install gettext \
    #gmp 很少使用 GNU多精度 多进制转换 如2进制转换为10进制 
    #&& apt install -y libgmp-dev && docker-php-ext-install gmp \
    #hash 比较常用 默认安装 信息摘要（哈希）引擎。允许使用各种哈希算法直接或增量处理任意长度的信息。
    #&& docker-php-ext-install hash \
    #iconv 比较常用 默认安装 包含了 iconv 字符集转换功能的接口
    #&& docker-php-ext-install iconv \
    #imap 几乎用不到 提供了可以操作 IMAP 以及 NNTP，POP3 和本地邮箱的方法
    #&& docker-php-ext-install imap \
    #interbase 几乎用不到 interbase数据库操作扩展
    #&& docker-php-ext-install interbase \
    #gd 图片裁切等操作库
    && apt install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd  \
    #intl 比较少用 国际化扩展（进一步被称为Intl）是ICU 库的一个包装，使PHP程序员能够执行各种语言环境感知操作，包括但不限于格式化，音译，编码转换，日历操作， 
    #UCA一致整理，文本定位边界和使用区域设置标识符，时区和字形，它倾向于密切关注ICU API，因此拥有使用C / C ++或Java的ICU经验的人员可以轻松使用PHP API。
    #此外，这样ICU文件将有助于理解各种ICU功能
    #&& apt install -y libicu-dev && docker-php-ext-install -j$(nproc) intl \
    #json 很常用 默认安装 json数据转换格式。
    #&& docker-php-ext-install json \
    #ldap 几乎用不到 轻型目录访问协议。
    #&& apt install -y libldap2 && docker-php-ext-install ldap \
    #mbstring 比较常用 默认已安装 多字节字符串 如中文在utf-8中是4个字节。
    #&& docker-php-ext-install mbstring \
    #mysqli 比较常用 连接mysql数据库扩展。
    && docker-php-ext-install mysqli \
    #oci8 比较少用 连接oracle数据库扩展。
    #&& docker-php-ext-install oci8 \
    #odbc 比较常用 连接各种数据库数据库的抽象层扩展。
    #&& docker-php-ext-install odbc \
    #opcache 比较常用 把 php 代码编译成的 Opocdes 缓存起来，每次请求的时候就不需要前面的编译步骤了。代替以前的 apc
    && docker-php-ext-install opcache \
    #pcntl 比较少用 进程操作库
    && docker-php-ext-install pcntl \
    #pdo 比较常用 默认已安装 连接各种数据库数据库的抽象层扩展
    #&& docker-php-ext-install pdo \
    #pdo_dblib 比较少用 使用pdo操作Microsoft SQL Server和Sybase 数据库的
    #&& docker-php-ext-install pdo_dblib \
    #pdo_firebird 比较少用 使用pdo操作firebird数据库的
    #&& docker-php-ext-install pdo_firebird \
    #pdo_mysql 比较常用 使用pdo操作mysql数据库的
    && docker-php-ext-install pdo_mysql \
    #pdo_oci 比较少用 使用pdo操作oracle数据库的。
    #&& docker-php-ext-install pdo_oci \
    #pdo_pgsql 比较常用 使用pdo操作pgsql数据库的
    #&& docker-php-ext-install pdo_pgsql \
    #pdo_sqlite 比较少用 使用pdo操作sqlite数据库的
    #&& docker-php-ext-install pdo_sqlite \
    #pgsql 比较少用 操作pgsql数据库的
    #&& docker-php-ext-install pgsql \
    #phar 比较少用 PHP5.3 之后支持了类似 Java 的 jar 包，名为 phar。用来将多个 PHP 文件打包为一个文件。这个特性使得 PHP 
    #也可以像 Java 一样方便地实现应用程序打包和组件化。一个应用程序可以打成一个 Phar 包，直接放到 PHP-FPM 中运行
    #&& docker-php-ext-install phar \
    #posix 比较少用 默认安装 用于进程控制 
    #&& docker-php-ext-install posix \
    #pspell 比较少用 拼写检查
    #&& docker-php-ext-install pspell \
    #readline 比较少用 默认安装 readline 扩展函数实现了访问 GNU Readline 库的接口. 这些函数提供了可编辑的命令行
    #&& apt install -y libedit-dev && docker-php-ext-install readline \
    #recode 比较少用 GNU 重新编码库
    #&& apt install -y librecode-dev && docker-php-ext-install recode \
    #reflection 比较少用 默认安装 它是用来导出或提取出关于类、方法、属性、参数等的详细信息，包括注释。
    #&& docker-php-ext-install reflection \
    #session 比较常用 默认安装 它是用来操作session。
    #&& docker-php-ext-install session \
    #shmop 几乎不用 共享内存。
    #&& docker-php-ext-install shmop \
    #snmp 几乎不用 简单网络访问协议。
    #&& docker-php-ext-install snmp \
    #soap 比较少用 简单对象访问协议 rpc 微服务等
    && docker-php-ext-install soap  \
    #sockets 比较少用 Socket扩展是基于流行的BSD sockets，实现了和socket通讯功能的底层接口，它可以和客户端一样当做一个socket服务器
    && docker-php-ext-install sockets  \
    #sodium 比较常用 默认安装 php 7.2之后集成到php扩展中，之前只能额外独立安装，加密库扩展
    #&& apt install -y libsodium-dev && docker-php-ext-install sodium  \
    #spl 比较常用 默认安装 SPL是用于解决典型问题(standard problems)的一组接口与类的集合。如堆、栈、队列、自动加载等
    #&& docker-php-ext-install spl  \
    #standard 极为常用 默认安装 php标准库
    #&& docker-php-ext-install standard  \
    #sysvmsg 几乎不用 System V信号量 
    #&& docker-php-ext-install sysvmsg  \
    #sysvsem 几乎不用 消息队列 
    #&& docker-php-ext-install sysvsem  \
    #sysvshm 几乎不用 提供了一个内存共享方案
    #&& docker-php-ext-install sysvshm  \
    #tidy 几乎不用 是一个整洁的HTML结合清理和修复工具，让您不仅干净，不单止能操作HTML文件，而且还可以遍历文档树。
    #&& apt install -y libtidy-dev && docker-php-ext-install tidy  \
    #tokenizer 几乎不用 okenizer函数提供了一个内嵌在Zend引擎的”PHP tokenizer”的调用接口。使用这些函数，你可以写出你自己的PHP源码分析或者修改工具，而无需处理词法分析级别上的语言规范
    #&& docker-php-ext-install tokenizer \
    #wddx 几乎不用 Web分布式数据交换(WDDX) php 7.4 已经不集成了，建议抛弃
    #&& docker-php-ext-install wddx \
    #xml 比较常用 默认安装 XML 解析器
    #&& docker-php-ext-install xml \
    #xmlreader 比较常用  默认安装 XMLReader扩展是一个XML Pull解析器。 
    #&& docker-php-ext-install xmlreader \
    #xmlrpc 比较少用 用来读写和创建 xml-rpc 服务端和客户端，实验性特性，未来版本肯能有大的变动
    #&& docker-php-ext-install xmlrpc \
    #xmlwriter 比较常用  默认安装 这是XMLWriter扩展。它包装了libxml xmlWriter API。 这个扩展表示一个作者，它提供了一个非缓存的，只能转发的方式来生成包含XML数据的流或文件 
    #&& docker-php-ext-install xmlwriter \
    #xsl 比较少用 指扩展样式表语言
    #&& apt install -y libxslt1-dev && docker-php-ext-install xsl \
    #zip 比较常用 此扩展可以让你透明地读写ZIP压缩文档以及它们里面的文件。
    && docker-php-ext-install zip \
    #openssl 比较少用
    && mv /usr/src/php/ext/openssl/config0.m4 /usr/src/php/ext/openssl/config.m4 \
    && apt install -y libssl-dev && docker-php-ext-install openssl \
    #-----------------
    && pecl update-channels && pecl config-set php_ini $PHP_INI_DIR/php.ini \
    #mcrypt sodium 被集成之前的php加密库
    && apt install -y libmcrypt-dev && echo -e "\n" | pecl install mcrypt && docker-php-ext-enable mcrypt \
    #Imagick 是用 ImageMagic API 来创建和修改图像的PHP官方扩展。比GD效率高，速度快, 占用空间大，内存高，程序不经常操作图片不建议安装
    #&& echo -e "\n" | pecl install imagick && docker-php-ext-enable imagick \
    #swoole 比较常用 Swoole是使用事件驱动，异步，非阻塞I/O模型的高性能网络框架。 
    #它可以用于开发高性能，可扩展的并发TCP，UDP，Unix套接字，HTTP，Websocket服务。
    && echo -e "\n" | pecl install swoole && docker-php-ext-enable swoole \
    && echo -e "\n" | pecl install igbinary && docker-php-ext-enable igbinary \
    && echo -e "\n" | pecl install redis && docker-php-ext-enable redis \
    && echo -e "\n" | pecl install xlswriter && docker-php-ext-enable xlswriter \
    && pecl clear-cache && apt clean && apt autoclean \
    && docker-php-source delete \
    #安装composer 和设置composer 源
    && wget https://mirrors.aliyun.com/composer/composer.phar && mv composer.phar /usr/local/bin/composer && chmod +x /usr/local/bin/composer \
    && composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/ \
    #设置用户
    && usermod -u 1000 www-data \
    #设置系统时区
    && ln -snf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && echo Asia/Shanghai > /etc/timezone
#supervisor 开机启动
#COPY ./conf.d/laravel.ini /usr/local/etc/php/conf.d/laravel.ini
#COPY ./conf.d/php.ini /usr/local/etc/php/conf.d/php.ini
COPY ./docker-php-entrypoint /usr/local/bin/docker-php-entrypoint
ENV TZ=Asia/Shanghai
EXPOSE 9000 9001 9501

