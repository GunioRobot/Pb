<?php
class Pb_Model_HelperBroker implements Pb_Model_Broker_Interface
{
    // $helperNameは、Helperのクラスフルネーム
    // $nameは、Helperのクラスprefixなし
    // static定義のメソッドは、Bootstrap等で初期設定を行うことを想定している
    protected static $_pluginLoader;
    protected static $_delimiter = '_';
    protected static $_helpers = array();

    public static function setPluginLoader(Zend_Loader_PluginLoader_Interface $loader)
    {
        self::$_pluginLoader = $loader;
    }

    public static function getPluginLoader()
    {
        if (is_null(self::$_pluginLoader)) {
            require_once('Zend/Loader/PluginLoader.php');
            self::$_pluginLoader = new Zend_Loader_PluginLoader(array(
                'Pb_Model_Helper' => 'Pb/Model/Helper',
            ));
        }

        return self::$_pluginLoader;
    }

    // リソース読み出し元追加(prefixからpathも設定)
    public static function addPrefix($prefix)
    {
        $prefix = rtrim($prefix, '_');
        $path   = str_replace('_', DIRECTORY_SEPARATOR, $prefix);
        self::addPath($path, $prefix);
    }

    // リソース読み出し元追加(prefix、pathを別に設定)
    public static function addPath($path, $prefix = null)
    {
        self::getPluginLoader()->addPrefixPath($prefix, $path);
    }

    // 登録helper追加(予め生成してつっこむときに使う）
    public static function addHelper(Pb_Model_Helper_Abstract $helper)
    {
        if (!self::hasHelper($helper->getName())) {
            self::$_helpers[$helper->getName()] = $helper;
        }
    }

    // 登録helper存在チェック
    public static function hasHelper($helperName)
    {
        return array_key_exists($helperName, self::$_helpers);
    }

    // 登録helper削除
    public static function removeHelper($helperName)
    {
        if (self::hasHelper($helperName)) {
            unset(self::$_helpers[$helperName]);
        }
    }

    // helperクラスを取得する
    // 未生成のhelperは生成してから返却する
    public function getHelper($name)
    {
        $helperName = $this->_getHelperFullName($name);
        if (!array_key_exists($helperName, self::$_helpers)) { $this->_loadHelper($name); }
        return self::$_helpers[$helperName];
    }

    // helper取得のショートカット
    public function __get($name)
    {
        return $this->getHelper($name);
    }

    // helperクラスを生成し、配列に格納する
    protected function _loadHelper($name)
    {
        $helperName = $this->_getHelperFullName($name);
        $helper = new $helperName();
        if (!($helper instanceof Pb_Model_Helper_Interface)) {
            require_once('Pb/Model/Helper/Exception.php');
            $msg = 'Helper name ' . $name . ' -> class ' . $helperName . ' is not Pb_Model_Helper_Interface';
            throw new Pb_Model_Helper_Exception($msg);
        }

        self::$_helpers[$helperName] = $helper;
    }

    // Helperのクラス名を取得する
    protected function _getHelperFullName($name)
    {
        try {
            return self::getPluginLoader()->load($this->_getLoadClassName($name));
        } catch (Zend_Loader_PluginLoader_Exception $e) {
            require_once('Pb/Model/Helper/Exception.php');
            throw new Pb_Model_Helper_Exception('Model Helper by name ' . $name . ' not found');
        }
    }

    // 指定されたクラス名をplubinLoaderに渡す形式に変更する
    // xxx_yyyの形式を、Xxx_Yyyに変更する
    protected function _getLoadClassName($name)
    {
        if (strpos($name, self::$_delimiter) === false) { return $name; }

        return str_replace(" ", "_", ucwords(str_replace(self::$_delimiter, " ", $name)));
    }

    // bootstrapはFrontControllerから取得するので、FrontControllerが未作成状態の
    // bootstrapの中では、new で生成できない
    protected function _getBootstrap()
    {
        if (is_null($this->_bootstrap)) {
            $this->_bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');

            if (is_null($this->_bootstrap)) {
                throw new Pb_Model_Helper_Exception("frontController doesn't have bootstrap yet");
            }
        }

        return $this->_bootstrap;
    }
}
?>
