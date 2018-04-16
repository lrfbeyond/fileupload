<?php 
namespace Upload;

use SplFileInfo;
use SplFileObject;

class File extends SplFileObject
{
    protected $filename;

    protected $info;
    // 上传文件命名规则
    protected $rule = 'time';
    // 上传验证规则
    public $validate = [];

    public function __construct($field, $upload = true, $mode = 'r')
    {
        if ($upload) {
            $filename = $field['tmp_name'];
        } else {
            $filename = $field;
        }
        
        parent::__construct($filename, $mode);
        $this->info = $field;
        $this->filename = $filename;
    }

    /**
     * 检查目录是否可写
     * @param  string   $path    目录
     * @return boolean
     */
    public function checkPath($path)
    {
        if (is_dir($path)) {
            return true;
        }

        if (mkdir($path, 0755, true)) {
            return true;
        } else {
            $res['msg'] = "目录 {$path} 创建失败！";
            echo json_encode($res);
            return false;
        }
    }

    /**
     * 获取上传文件的信息
     * @param  string   $name
     * @return array|string
     */
    public function getInfo($name = '')
    {
        return isset($this->info[$name]) ? $this->info[$name] : $this->info;
    }

    /**
     * 获取文件类型信息
     * @return string, 如：image/png
     */
    public function getMime()
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($finfo, $this->filename);
    }

    /**
     * 检测是否合法的上传文件,通过http POST上传
     * @return bool
     */
    public function isValid()
    {
        return is_uploaded_file($this->filename);
    }

    /**
     * 检测上传文件
     * @param  array   $rule    验证规则
     * @return bool
     */
    public function check($rule = [])
    {
        $rule = $rule ?: $this->validate;

        /* 检查文件大小 */
        if (isset($rule['size']) && !$this->checkSize($rule['size'])) {
            $res['msg'] = '上传文件大小不符！';
            echo json_encode($res);
            return false;
        }

        /* 检查文件Mime类型 */
        if (isset($rule['type']) && !$this->checkMime($rule['type'])) {
            $res['msg'] = '上传文件MIME类型不允许！';
            echo json_encode($res);
            return false;
        }

        /* 检查文件后缀 */
        if (isset($rule['ext']) && !$this->checkExt($rule['ext'])) {
            $res['msg'] = '上传文件后缀不允许';
            echo json_encode($res);
            return false;
        }

        /* 检查图像文件 */
        if (!$this->checkImg()) {
            $res['msg'] = '非法图像文件！';
            echo json_encode($res);
            return false;
        }

        return true;
    }

    /**
     * 检测上传文件大小
     * @param  integer   $size    最大大小
     * @return bool
     */
    public function checkSize($size)
    {
        if ($this->getSize() > $size) {
            return false;
        }
        return true;
    }

    /**
     * 检测上传文件类型
     * @param  array|string   $mime    允许类型
     * @return bool
     */
    public function checkMime($mime)
    {
        if (is_string($mime)) {
            $mime = explode(',', $mime);
        }
        if (!in_array(strtolower($this->getMime()), $mime)) {
            return false;
        }
        return true;
    }

    /**
     * 检测上传文件后缀
     * @param  array|string   $ext    允许后缀
     * @return bool
     */
    public function checkExt($ext)
    {
        if (is_string($ext)) {
            $ext = explode(',', $ext);
        }
        $extension = strtolower(pathinfo($this->getInfo('name'), PATHINFO_EXTENSION));
        if (!in_array($extension, $ext)) {
            return false;
        }
        return true;
    }

    /**
     * 检测图像文件
     * @return bool
     */
    public function checkImg()
    {
        $extension = strtolower(pathinfo($this->getInfo('name'), PATHINFO_EXTENSION));
        /* 对图像文件进行严格检测 */
        if (in_array($extension, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'swf']) && !in_array($this->getImageType($this->filename), [1, 2, 3, 4, 6])) {
            return false;
        }
        return true;
    }

    // 判断图像类型
    protected function getImageType($image)
    {
        if (function_exists('exif_imagetype')) {
            return exif_imagetype($image);
        } else {
            $info = getimagesize($image);
            return $info[2];
        }
    }

    /**
     * 获取保存文件名
     * @param  string|bool   $savename    保存的文件名 默认自动生成
     * @return string
     */
    public function buildSaveName()
    {
        switch ($this->rule) {
            case 'time':
                $savename = date('Ymd') . DIRECTORY_SEPARATOR . date('YmdHis').rand(1000,9999);
                break;
            case 'md5':
                $savename = date('Ymd') . DIRECTORY_SEPARATOR . md5(microtime(true));
                break;
            case 'uniqid':
                $savename = date('Ymd') . DIRECTORY_SEPARATOR . uniqid();
                break;
            default:
                $savename = date('Ymd') . DIRECTORY_SEPARATOR . date('YmdHis').rand(1000,9999);
        }
        if (!strpos($savename, '.')) {
            $savename .= '.' . pathinfo($this->getInfo('name'), PATHINFO_EXTENSION);
        }
        return $savename;
    }

    /**
     * 上传文件
     * @param  string           $path    保存路径
     * @param  boolean          $replace 同名文件是否覆盖
     * @return false|SplFileInfo false-失败 否则返回SplFileInfo实例
     */
    public function upload($path, $replace = true)
    {

        // 检测合法性
        if (!$this->isValid()) {
            $res['msg'] '非法上传文件';
            echo json_encode($res);
            return false;
        }

        // 验证上传
        if (!$this->check()) {
            return false;
        }
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        // 文件保存命名规则
        $saveName = $this->buildSaveName();
        $filename = $path . $saveName;

        // 检测目录
        if (false === $this->checkPath(dirname($filename))) {
            return false;
        }

        /* 不覆盖同名文件 */
        if (!$replace && is_file($filename)) {
            $res['msg'] = '存在同名文件' . $filename;
            echo json_encode($res);
            return false;
        }

        /* 移动文件 */
        if (!move_uploaded_file($this->filename, $filename)) {
            $res['msg'] '文件上传保存错误！';
            echo json_encode($res);
            return false;
        }

        // 返回 File对象实例
        $file = new self($filename, false);
        $info = [
            'savename' => $saveName,
            'filename' => $file->getFilename(),
            'ext' => $file->getExtension(),
            'size' => $file->getSize()
        ];
        return $info;
        // $file->setSaveName($saveName);
        // $file->setUploadInfo($this->info);
        // return $file;
    }

    // 如果方法不存在，则返回
    public function __call($method, $args)
    {
        return '类方法[' . $method . ']不存在！';
    }

}