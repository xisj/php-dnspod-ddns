# php-dnspod-ddns
一个可以直接用的单文件DNSpod 实现ddns 动态域名解析的php 文件

# 2024-01-19 更新
php版本的api太古老了， 用最新的api重写了一个纯shell的版本， 方便在路由等环境里使用

https://github.com/xisj/dnspod-ddns

## 使用方法


### 1 编辑文件开头的

api_id

api_token


### 2 添加自己的域名
$data[] = array('domain' => '你的域名', 'sub_domain' => '你的二级域名');


### 3 在命令行下直接执行一次， 看是否正常运行


### 4 使用crontab , 定时运行本脚本
