<?php namespace Ueditor;

class Ueditor {

    /**
     * Ueditor后台配置
     * @var array
     */
    private $configs;

    /**
     * 请求动作配置
     * @var array
     */
    private $actionConfig;

    /**
     * 请求状态信息
     * @var string
     */
    private $stateInfo;

    /**
     * 原始文件名
     * @var string
     */
    private $oriName;

    /**
     * 文件大小
     * @var int
     */
    private $fileSize;

    /**
     * 文件类型
     * @var string
     */
    private $fileType;

    /**
     * 文件全名（包含相对路径） 由原始文件名按照配置文件中的保存规则解析得到
     * @var string
     */
    private $fullName;

    /**
     * 文件完整路径 物理路径
     * @var string
     */
    private $filePath;

    /**
     * 新文件名
     * @var string
     */
    private $fileName;

    /**
     * 请求结果
     * @var array
     */
    /**
     * 得到上传文件所对应的各个参数,数组结构
     * array(
     *     "state" => "",          //上传状态，上传成功时必须返回"SUCCESS"
     *     "url" => "",            //返回的地址
     *     "title" => "",          //新文件名
     *     "original" => "",       //原始文件名
     *     "type" => ""            //文件类型
     *     "size" => "",           //文件大小
     * )
     */
    private $result;

    /**
     * 上传成功的文件数组
     * @var array
     */
    private $uploadedFiles;

    /**
     * 创建Ueditor实例
     */
    public function __construct()
    {
        $this->getConfig();
        $this->switchAction();
    }

    /**
     * 获取Ueditor配置信息
     */
    private function getConfig()
    {
        $default = $this->getConfigFromFile();
        $configs = config('ueditor');
        $this->configs = empty($configs) ? $default : array_merge($default, $configs);
    }

    /**
     * 从配置文件获取配置信息
     * @return mixed
     */
    private function getConfigFromFile()
    {
        return require __DIR__.'/../../config/ueditor.php';
    }

    /**
     * 动作选择，根据action参数调用方法
     */
    private function switchAction()
    {
        $action = htmlspecialchars($_GET['action']);

        switch ($action) {
            /* 检测配置文件 */
            case 'config':
                $this->result = $this->configs;
                break;

            /* 上传图片 */
            case 'uploadimage':
            /* 上传视频 */
            case 'uploadvideo':
            /* 上传文件 */
            case 'uploadfile':
                $this->upFile($action);
                break;

            /* 上传涂鸦 */
            case 'uploadscrawl':
                $this->upBase64();
                break;

            /* 列出图片 */
            case 'listimage':
            /* 列出文件 */
            case 'listfile':
                $this->listFiles($action);
                break;

            /* 抓取远程文件 */
            case 'catchimage':
                $this->crawler();
                break;

            default:
                $this->result = array(
                    'state' => '请求地址出错'
                );
                break;
        }
    }

    /**
     * 上传文件
     * @param string $action 请求类型
     * @return void
     */
    private function upFile($action)
    {
        switch ($action) {
            case 'uploadimage':
                $config = array(
                    "pathFormat" => $this->configs['imagePathFormat'],
                    "maxSize" => $this->configs['imageMaxSize'],
                    "allowFiles" => $this->configs['imageAllowFiles']
                );
                $fieldName = $this->configs['imageFieldName'];
                break;
            case 'uploadvideo':
                $config = array(
                    "pathFormat" => $this->configs['videoPathFormat'],
                    "maxSize" => $this->configs['videoMaxSize'],
                    "allowFiles" => $this->configs['videoAllowFiles']
                );
                $fieldName = $this->configs['videoFieldName'];
                break;
            case 'uploadfile':
            default:
                $config = array(
                    "pathFormat" => $this->configs['filePathFormat'],
                    "maxSize" => $this->configs['fileMaxSize'],
                    "allowFiles" => $this->configs['fileAllowFiles']
                );
                $fieldName = $this->configs['fileFieldName'];
                break;
        }
        // 当前动作配置
        $this->actionConfig = $config;

        // 上传文件常规检查
        $file = $_FILES[$fieldName];

        // 上传文件是否存在
        if (empty($file)) {
            $this->stateInfo = $this->getStateInfo('ERROR_FILE_NOT_FOUND');
            return;
        }
        // 是否成功上传 根据error状态码判断
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->stateInfo = $this->getStateInfo($file['error']);
            return;
        }
        // 检查上传临时文件是否存在
        if (!is_file($file['tmp_name'])) {
            $this->stateInfo = $this->getStateInfo("ERROR_TMP_FILE_NOT_FOUND");
            return;
        }
        // 函数检查  是否是上传文件
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->stateInfo = $this->getStateInfo("ERROR_TMPFILE");
            return;
        }

        // 赋值文件属性
        $this->oriName = $file['name'];
        $this->fileSize = $file['size'];
        $this->fileType = $this->getFileExt();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->fileName = $this->getFileName();

        $dirName = dirname($this->filePath);

        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return;
        }

        //检查是否不允许的文件格式
        if (!$this->checkType()) {
            $this->stateInfo = $this->getStateInfo("ERROR_TYPE_NOT_ALLOWED");
            return;
        }

        //创建目录失败
        if (!file_exists($dirName) && !mkdir($dirName, 0777, true)) {
            $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
            return;
        } else if (!is_writeable($dirName)) {
            $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
            return;
        }

        //移动文件
        if (!(move_uploaded_file($file["tmp_name"], $this->filePath) && is_file($this->filePath))) { //移动失败
            $this->stateInfo = $this->getStateInfo("ERROR_FILE_MOVE");
        } else { //移动成功
            $this->stateInfo = $this->getStateInfo(UPLOAD_ERR_OK);
            $this->uploadedFiles[] = $this->getFileInfo();
        }

        $this->result = $this->getFileInfo();
    }

    private function upBase64()
    {
        $config = array(
            "pathFormat" => $this->configs['scrawlPathFormat'],
            "maxSize" => $this->configs['scrawlMaxSize'],
            "allowFiles" => $this->configs['scrawlAllowFiles'],
            "oriName" => "scrawl.png"
        );
        $fieldName = $this->configs['scrawlFieldName'];

        $this->actionConfig = $config;
        $base64Data = $_POST[$fieldName];
        if (empty($base64Data)) {
            $this->stateInfo = $this->getStateInfo('ERROR_FILE_NOT_FOUND');
            return;
        }

        $img = base64_decode($base64Data);

        $this->oriName = $this->actionConfig['oriName'];
        $this->fileSize = strlen($img);
        $this->fileType = $this->getFileExt();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->fileName = $this->getFileName();
        $dirName = dirname($this->filePath);

        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return;
        }

        //创建目录失败
        if (!file_exists($dirName) && !mkdir($dirName, 0777, true)) {
            $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
            return;
        }

        if (!is_writeable($dirName)) {
            $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
            return;
        }

        //移动文件
        if (!(file_put_contents($this->filePath, $img) && file_exists($this->filePath))) { //移动失败
            $this->stateInfo = $this->getStateInfo("ERROR_WRITE_CONTENT");
        } else { //移动成功
            $this->stateInfo = $this->getStateInfo(UPLOAD_ERR_OK);
            $this->uploadedFiles[] = $this->getFileInfo();
        }

        $this->result = $this->getFileInfo();
    }

    private function crawler()
    {
        /* 上传配置 */
        $config = array(
            "pathFormat" => $this->configs['catcherPathFormat'],
            "maxSize" => $this->configs['catcherMaxSize'],
            "allowFiles" => $this->configs['catcherAllowFiles'],
            "oriName" => "remote.png"
        );
        $fieldName = $this->configs['catcherFieldName'];

        $this->actionConfig = $config;


        /* 抓取远程图片 */
        $list = array();
        if (isset($_POST[$fieldName])) {
            $source = $_POST[$fieldName];
        } else {
            $source = $_GET[$fieldName];
        }
        foreach ((array)$source as $imgUrl) {
            $this->saveRemote($imgUrl);
            $info = $this->getFileInfo();
            array_push($list, array(
                "state" => $info["state"],
                "url" => $info["url"],
                "size" => $info["size"],
                "title" => htmlspecialchars($info["title"]),
                "original" => htmlspecialchars($info["original"]),
                "source" => htmlspecialchars($imgUrl)
            ));
        }

        $this->result = [
            'state'=> count($list) ? 'SUCCESS':'ERROR',
            'list'=> $list,
        ];
    }

    private function saveRemote($url)
    {
        $imgUrl = htmlspecialchars($url);
        $imgUrl = str_replace("&amp;", "&", $imgUrl);

        //http开头验证
        if (strpos($imgUrl, "http") !== 0) {
            $this->stateInfo = $this->getStateInfo("ERROR_HTTP_LINK");
            return;
        }
        //获取请求头并检测死链
        $heads = get_headers($imgUrl);
        if (!(stristr($heads[0], "200") && stristr($heads[0], "OK"))) {
            $this->stateInfo = $this->getStateInfo("ERROR_DEAD_LINK");
            return;
        }
        //格式验证(扩展名验证和Content-Type验证)
        $fileType = strtolower(strrchr($imgUrl, '.'));
        if (!in_array($fileType, $this->actionConfig['allowFiles']) || stristr($heads['Content-Type'], "image")) {
            $this->stateInfo = $this->getStateInfo("ERROR_HTTP_CONTENTTYPE");
            return;
        }

        //打开输出缓冲区并获取远程图片
        ob_start();
        $context = stream_context_create(
            array('http' => array(
                'follow_location' => false // don't follow redirects
            ))
        );
        readfile($imgUrl, false, $context);
        $img = ob_get_contents();
        ob_end_clean();
        preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/", $imgUrl, $m);

        $this->oriName = $m ? $m[1]:"";
        $this->fileSize = strlen($img);
        $this->fileType = $this->getFileExt();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->fileName = $this->getFileName();
        $dirname = dirname($this->filePath);

        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return;
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
            return;
        } else if (!is_writeable($dirname)) {
            $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
            return;
        }

        //移动文件  此处原作者这样写，叫人无可奈何，细看也就明白了 两步作一步
        if (!(file_put_contents($this->filePath, $img) && file_exists($this->filePath))) { //移动失败
            $this->stateInfo = $this->getStateInfo("ERROR_WRITE_CONTENT");
        } else { //移动成功
            $this->stateInfo = $this->getStateInfo(UPLOAD_ERR_OK);
            $this->uploadedFiles[] = $this->getFileInfo();
        }
    }

    private function listFiles($action)
    {
        switch ($action) {
            /* 列出文件 */
            case 'listfile':
                $allowFiles = $this->configs['fileManagerAllowFiles'];
                $listSize = $this->configs['fileManagerListSize'];
                $path = $this->configs['fileManagerListPath'];
                break;
            /* 列出图片 */
            case 'listimage':
            default:
                $allowFiles = $this->configs['imageManagerAllowFiles'];
                $listSize = $this->configs['imageManagerListSize'];
                $path = $this->configs['imageManagerListPath'];
        }

        /* 获取参数 */
        $size = isset($_GET['size']) ? htmlspecialchars($_GET['size']) : $listSize;
        $start = isset($_GET['start']) ? htmlspecialchars($_GET['start']) : 0;
        $end = intval($start) + intval($size);

        $directory = $this->configs['rootPath'].(substr($path, 0, 1) == "/" ? "":"/").$path;
        $files = $this->getFileList($directory, $allowFiles);

        if (!count($files)) {
            $this->result = array(
                "state" => "no match file",
                "list" => array(),
                "start" => $start,
                "total" => count($files)
            );
        }

        /* 获取指定范围的列表 */
        $len = count($files);
        for ($i = min($end, $len) - 1, $list = array(); $i < $len && $i >= 0 && $i >= $start; $i--){
            $list[] = $files[$i];
        }
//        // 倒序
//        for ($i = $end, $list = array(); $i < $len && $i < $end; $i++){
//            $list[] = $files[$i];
//        }

        /* 返回数据 */
        $this->result = array(
            "state" => "SUCCESS",
            "list" => $list,
            "start" => $start,
            "total" => count($files)
        );

    }

    /**
     * 获取状态信息
     * @param mixed $errorCode 状态码
     * @return mixed
     */
    private function getStateInfo($errorCode)
    {
        $errors = [
            // 注释部分为常量的值
            UPLOAD_ERR_OK => 'SUCCESS', //其值为 0，没有错误发生，文件上传成功。
            UPLOAD_ERR_INI_SIZE => '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值', // 其值为 1，
            UPLOAD_ERR_FORM_SIZE => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',  // 其值为 2，
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',  // 其值为 3，
            UPLOAD_ERR_NO_FILE => '没有文件被上传',  // 其值为 4，
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹', // 其值为 6，PHP 4.3.10 和 PHP 5.0.3 引进。
            UPLOAD_ERR_CANT_WRITE => '文件写入失败', // 其值为 7，PHP 5.1.0 引进。
            'ERROR_TMP_FILE' => '临时文件错误',
            'ERROR_TMP_FILE_NOT_FOUND' => '找不到临时文件',
            'ERROR_SIZE_EXCEED' => '文件大小超出网站限制',
            'ERROR_TYPE_NOT_ALLOWED' => '文件类型不允许',
            'ERROR_CREATE_DIR' => '目录创建失败',
            'ERROR_DIR_NOT_WRITEABLE' => '目录没有写权限',
            'ERROR_FILE_MOVE' => '文件保存时出错',
            'ERROR_FILE_NOT_FOUND' => '找不到上传文件',
            'ERROR_WRITE_CONTENT' => '写入文件内容错误',
            'ERROR_UNKNOWN' => '未知错误',
            'ERROR_DEAD_LINK' => '链接不可用',
            'ERROR_HTTP_LINK' => '链接不是http链接',
            'ERROR_HTTP_CONTENTTYPE' => '链接contentType不正确',
        ];

        return isset($errors[$errorCode]) ? $errors[$errorCode] : $errors['ERROR_UNKNOWN'];
    }


    /**
     * 获取文件扩展名
     * @return string
     */
    private function getFileExt()
    {
        return strtolower(strrchr($this->oriName, '.'));
    }

    /**
     * 重命名文件
     * @return string
     */
    private function getFullName()
    {
        //替换日期事件
        $t = time();
        $d = explode('-', date("Y-y-m-d-H-i-s"));
        $format = $this->actionConfig["pathFormat"];
        $format = str_replace("{yyyy}", $d[0], $format);
        $format = str_replace("{yy}", $d[1], $format);
        $format = str_replace("{mm}", $d[2], $format);
        $format = str_replace("{dd}", $d[3], $format);
        $format = str_replace("{hh}", $d[4], $format);
        $format = str_replace("{ii}", $d[5], $format);
        $format = str_replace("{ss}", $d[6], $format);
        $format = str_replace("{time}", $t, $format);

        //过滤文件名的非法自负,并替换文件名
        $oriName = substr($this->oriName, 0, strrpos($this->oriName, '.'));
        $oriName = preg_replace("/[\|\?\"\<\>\/\*\\\\]+/", '', $oriName);
        $format = str_replace("{filename}", $oriName, $format);

        //替换随机字符串
        $randNum = rand(1, 10000000000) . rand(1, 10000000000);
        if (preg_match("/\{rand\:([\d]*)\}/i", $format, $matches)) {
            $format = preg_replace("/\{rand\:[\d]*\}/i", substr($randNum, 0, $matches[1]), $format);
        }

        $ext = $this->getFileExt();
        return $format . $ext;
    }

    /**
     * 获取文件名
     * @return string
     */
    private function getFileName () {
        return substr($this->filePath, strrpos($this->filePath, '/') + 1);
    }

    /**
     * 获取文件完整路径
     * @return string
     */
    private function getFilePath()
    {
        $fullname = $this->fullName;
        $rootPath = $_SERVER['DOCUMENT_ROOT'];

        if (substr($fullname, 0, 1) != '/') {
            $fullname = '/' . $fullname;
        }

        return $rootPath . $fullname;
    }

    /**
     * 文件类型检测
     * @return bool
     */
    private function checkType()
    {
        return in_array($this->getFileExt(), $this->actionConfig["allowFiles"]);
    }

    /**
     * 文件大小检测
     * @return bool
     */
    private function  checkSize()
    {
        return $this->fileSize <= ($this->actionConfig["maxSize"]);
    }

    /**
     * 获取当前文件上传的各项信息
     * @return array
     */
    private function getFileInfo()
    {
        return array(
            "state" => $this->stateInfo,
            "url" => $this->fullName,
            "title" => $this->fileName,
            "original" => $this->oriName,
            "type" => $this->fileType,
            "size" => $this->fileSize
        );
    }

    /**
     * 获取指定目录下，指定类型的文件列表
     * @param  string $path        目录名
     * @param  array  $allow_files 文件类型
     * @param array $files
     * @return array 文件列表
     */
    private function getFileList($path, $allow_files = array('.png','.jpg'), &$files = array()) {
        if (!is_dir($path)) return array();
        if (substr($path, strlen($path) - 1) != '/') $path .= '/';

        $dir_list = scandir($path);  //返回文件和目录。
        foreach ($dir_list  as $file) {
            if ($file != '.' && $file != '..') {
                $file = $path . $file;
                if (!is_dir($file)) {  //如果是文件
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($extension, $allow_files)) {
                        $file = trim($file, './');
                        $files[] = array(
                            'url'   => asset(substr($file, strlen(public_path()))),
                            'mtime' => filemtime($file),
                        );
                    }
                } else {   //如果是目录
                    $this->getFileList($file, $allow_files, $files);
                }
            }
        }
        return $files;
    }

    public function response()
    {
        $result = isset($this->result) ? $this->result : $this->getFileInfo();
        /* 输出结果 */
        if (isset($_GET["callback"])) {

            if (preg_match("/^[\w_]+$/", $_GET["callback"])) {
                return response()->jsonp($_GET['callback'], $result);

            } else {
                return response()->json(array(
                    'state' => 'callback参数不合法'
                ));
            }
        } else {
            return response()->json($result);
        }
    }

    public function getUploadedFiles()
    {
        return $this->getUploadedFiles();
    }
} 