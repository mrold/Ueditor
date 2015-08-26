# Ueditor
Ueditor for Laravel。这是一个在laravel5上使用的Ueditor扩展包。
第一步：安装
--------------------------
#### 在项目`composer.json`文件中引入Ueditor包：
```javascript
{
    "require": {
        "laravel/framework": "5.*",
        "mrold/ueditor": "~1.0"
    }
}
```

#### 在命令行运行composer更新：
```
composer update
```
#### 在项目配置文件`app.php`中添加`UeditorServiceProvider`:
```php
return [
    // .........
    'providers' => [
        // ..........
        'Leona\UeditorServiceProvider',
    ]
];
```
第二步：配置
-------------------------
#### 运行Laravel的artisan命令,把扩展包中的配置文件和前端资源文件复制到项目中：
```
php artisan vendor:publish
```
配置文件名为：`ueditor.php`。配置项与官方原版一致，只是转换成php格式。具体如何配置请参考官方文档：http://fex.baidu.com/ueditor/
>##### 提醒
>为了便于查看原版的php代码，默认保留了php文件夹下的所有文件。基于安全考虑，实际部署项目时请自行删除吧。

第三步：使用
-----------------
#### 前端配置不再详细说明。简要贴出代码：
```html
<!-- 加载编辑器的容器 -->
<script id="container" name="content" type="text/plain">
    这里写你的初始化内容
</script>

<!-- 配置文件 -->
<script type="text/javascript" src="{{ asset('leona/ueditor/ueditor.config.js') }}"></script>
<!-- 编辑器源码文件 -->
<script type="text/javascript" src="{{ asset('leona/ueditor/ueditor.all.js') }}"></script>
<!-- 实例化编辑器 -->
<script type="text/javascript">
    var serverUrl = "{{ url('test') }}";
    var csrf_token = "{{ csrf_token() }}";
    var ue = UE.getEditor('container', {
        serverUrl: serverUrl
    });
    ue.ready(function () {
        ue.execCommand('serverparam', {
            "_token": csrf_token
        });
    });
</script>
```
#### 后台只需提供一个用于前端请求的url路由即可，也就是上面代码中的`serverUrl`。
下面试着在laravle的路由文件`routes.php`中添加一条路由，请求类型必须设置为`any`：
```php
Route::any('test', function () {
    $Ue = app('ueditor');  // 从app容器中解析ueditor实例
    $Ue->response();
});
```
如果你想要记录上传成功的文件信息，你可以继续按照以下方法来获取，这将返回一个数组或者null。
```php
    $files = $Ue->getUploadedFiles();
```
结束语：
--------------------
这是本人在github上的第一个项目，各方面还不是很熟悉，再加上英文也马马虎虎，望各位前辈多指教！
