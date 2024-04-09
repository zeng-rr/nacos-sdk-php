## 阿里云nacos配置监控组件
> 本组件是基于 `nacos-group/nacos-sdk-php`基础上开发而来： https://github.com/nacos-group/nacos-sdk-php 
> 在原有组件的基础上增加了账密鉴权, 这样既能支持云服务Nacos也能支持自建Nacos

* 服务参考地址：https://help.aliyun.com/product/123350.html
* nacos开发文档：https://nacos.io/zh-cn/docs/open-api.html
* 本组件可以用于laravel 框架，也可以用于非laravel框架。

### 安装
* 首先在项目`composer.json` 文件的根节点下添加`repositories`对象,如：
```javascript
{
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "alicloud/config-monitor",
                "version": "1.0.0",
                "dist": {
                    "url": "https://github.com/zeng-rr/nacos-sdk-php/archive/refs/tags/v1.0.0.zip",
                    "type": "zip"
                }
            }
        }
    ],
    "autoload": {
        "psr-4": {
            "Alicloud\\ConfigMonitor\\": "vendor/alicloud/config-monitor/src/"
        }
    }
    
    .....
}
```

* 执行组件安装命令：`composer require alicloud/config-monitor`
    * 如果报无安装权限，则执行 `composer config secure-http false`,表示关闭Https访问限制。
    * 如果`guzzlehttp/guzzle`版本低于`6.5`请升级 `composer update guzzlehttp/guzzle 6.5`

### 启用
    
#### 编写 console 命令
* Laravel 项目参考：
```PHP

/**
 * 阿里云 MES nacos 配置监控命令
 * https://packagist.org/packages/verystar/aliyun-acm
 */
Artisan::command('alicloud-mes:listenconfig', function(){
    $this->info('阿里云MES配置监控');
    
    //下面两个路径如果不使用默认，则需要确保存在
    $changeToEnvFile = base_path('.env_test');      //env文件路径(确定没问题后修改为 .env)
    
    //实例化，并启动监听
    try {
        $monitorInstance  = app(\Alicloud\ConfigMonitor\MonitorHandle::class, [
            'host'  => '127.0.0.1:8848', // 服务地址, 需带端口
            'nameSpaceId' => '4321543215-54325435423-5432', // 命名空间ID
            'dataId' => '.env', // 配置集id
            'changeToEnvFile' => $changeToEnvFile,
            'group' => 'DEFAULT_GROUP', // 配置分组名
            'pullingSenonds' => 30, // 长轮询时间
            'env'           => env('APP_ENV'),
        ]);
        // 登录鉴权(可选)
        NacosConfig::setUsername('username');
        NacosConfig::setPassword('password');
        // 阿里云AK鉴权(可选)
        NacosConfig::setAk('阿里云MES服务的AccessKey');
        NacosConfig::setSk('阿里云MES服务的SecretKey');
        $monitorInstance->listenNotify();  //开启监控
    } catch(\Exception $err) {
        $errorinfo = $err->getMessage();
        \Illuminate\Support\Facades\Log::error($errorinfo);
    }
    
})->describe('阿里云MES配置监控');


```

#### 运行
```PHP
    JZTech-xxx: huangwh$ php artisan alicloud-mes:listenconfig
    阿里云MES配置监控
    [2022-01-07 11:42:48] nacos-client.INFO: 配置监听中，长轮询时间为10秒 ...   [] []
    [2022-01-07 11:42:48] nacos-client.INFO: 监听到配置有变更，已经更新到本地ENV文件...   [] []
    [2022-01-07 11:42:48] nacos-client.INFO: listener loop count: 1 [] []
    [2022-01-07 11:42:58] nacos-client.INFO: 配置无变化，监听中...  [] []
    [2022-01-07 11:42:58] nacos-client.INFO: listener loop count: 2 [] []
    ...
```

#### 进程常驻
* 建议使用 `supervisor`维护进程常驻。参考：
```$xslt
    [program:monitor]
    directory=/var/www/html
    command=php artisan alicloud-mes:listenconfig
    autostart=true
    autorestart=true
    redirect_stderr=true
    numprocs=2
    stdout_logfile=./storage/logs/n8config-listenconfig.log
    stdout_logfile_maxbytes = 50MB
    stdout_logfile_backups = 3
    process_name=%(program_name)s_%(process_num)02d

```

