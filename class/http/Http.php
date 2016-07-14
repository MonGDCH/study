<?php
/**
* HTTP类
* @version 1.0
* @author Mon 	985558837@qq.com
*/
class Http
{
	//HTTP配置路径，可修改
	private static $configFile = "./httpserver.conf";
	//配置
	private static $config;
	//缓存已请求的server配置
	private static $requestCache;

	function __construct($argument = null)
	{

	}

	/**
	 * HTTP以URL的形式发送请求
	 * @todo 目前只实现get,post。若以后有更多请求类型再补充
	 * @param 	string 	$url 	请求地址
	 * @param 	array 	$data 	传递数据
	 * @param 	string 	$type 	请求类型
	 * @param 	bool 	$toJson	是否转换为json返回
	 * @param 	int 	$timeOut请求超时时间
	 * @return  $result
	 */
	public static function excuteUrl($url, $data = array(), $type = 'get', $toJson = false, $timeOut = 0)
	{
		//post请求
		if($type == 'post'){
			$result = self::sendRquest($url, $data, "post", $toJson, $timeOut);
			return $result;
		}

		//get请求
		if(count($data) > 0 && $type = 'get'){
			$uri = self::arrToUri($data);
			$url = $url.$uri;
		}
		$result = self::sendRquest($url, array(), "get", $toJson, $timeOut);
		return $result;
	}

	/**
	 * CMD类型访问,相对性能较高
	 * @param 	string 	$serverName 配置文件中对应节点名
	 * @param 	array 	$data 		传输数据
	 * @param 	bool 	$cache 		是否读取缓存数据
	 * @param 	int 	$cacheTime 	缓存数据有效时间
	 * @param 	bool 	$toJson 	是否返回JSON数据
	 * @param 	int 	$timeOut 	请求超时时间
	 * @return  $result;
	 */
	public static function excuteCMD($serverName, $data = array(), $cache = false, $cacheTime = 60, $toJson = false, $timeOut = 0)
	{
		$server = self::getServer($serverName);
		if(empty($server)){
			$errorMsg = "未能成功创建[ ".$serverName." ]访问节点";
			self::errorQuit($errorMsg);
		}
		//判断是否允许获取缓存数据
		if($cache && !empty($server['chche']) && (time(0) - $server['time']) <= $cacheTime){
			return $server['cache'];
		}

		//CMD请求
		$uri = self::arrToUri($data);
		$url = $server['url'].$uri;
		$result = self::sendRquest($url, array(), "get", $toJson, $timeOut);

		//缓存数据
		self::$requestCache['serverName']['cache'] = $result;
		self::$requestCache['serverName']['time'] = time(0);

		return $result;
	}

	/**
	 * 数组转换成uri
	 * @param array $data 一维数组
	 * @return string uri
	 */
	public static function arrToUri($data){
		$ds = "&";
		$result = "";
		if(count($data) < 1){
			$errorMsg = "[".__METHOD__."]未能成功转换数组为URI";
			self::errorQuit($errorMsg);
		}

		foreach ($data as $key => $value) {
			$result = $result.$ds.$key."=".$value;
		}

		return "?".$result;
	}

	/**
	 * 获取IP地址端口
	 * @param string $serverName 配置文件对应服务名
	 * @return array
	 */
	public static function getServer($serverName)
	{
		//判断是否存在请求缓存
		if(!empty(self::$requestCache[$serverName])){
			return self::$requestCache[$serverName];
		}

		if(empty(self::$config)){
			self::getConfig();
		}
		//判断配置文件中是否存在对应节点
		if(empty(self::$config[$serverName])){
			$errorMsg = "配置文件未设置对应节点";
			self::errorQuit($errorMsg);
		}

		//创建请求地址实例，缓存实例
		$serverNum 	= self::$config[$serverName]['serverNum'];
		$port 		= self::$config[$serverName]['port'];
		$rand 		= ($serverNum > 0) ? mt_rand(0,$serverNum-1) : 0;
		$ip			= self::$config[$serverName]['ip'.$rand];
		//缓存
		self::$requestCache[$serverName] = array(
			"url" 	=> "http://{$ip}:{$port}/",
			"cache" => '',
			"time" 	=> time(0),
		);

		return self::$requestCache[$serverName];
	}

	/**
	 * 获取配置文件配置信息
	 */
	public static function getConfig()
	{
		if(!file_exists(self::$configFile)){
			$errorMsg = "HttpError : The config file is not found！";
			self::errorQuit($errorMsg);
		}

		//以php.ini的格式读取配置文件
		self::$config = parse_ini_file(self::$configFile, true);

		return self::$config;
	}


	/**
	 * 错误返回,抛出错误
	 * @param string $msg 错误提示信息
	 * @return Error
	 */
	private static function errorQuit($msg = "")
	{
		if(!empty($msg)){
			//抛出错误
			trigger_error($msg,E_USER_ERROR);
			exit();
		}
	}

	private static function sendRquest($url,  $data = array(), $type = 'get', $toJson = false, $timeOut = 0)
	{
		//判断是否为https请求
		$ssl = substr($url, 0, 8) == "https://" ? TRUE : FALSE;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, ($type == 'post') ? true : false);
		//判断是否需要传递post数据
		if(count($data) != 0){
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		//设置内容以文本形式返回，而不直接返回
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		if ($ssl){
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
	    }

	    //设置超时时间
	    if(!empty($timeOut)){
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);
		}

		$html = curl_exec($ch);

		$result = ($toJson) ? json_encode($html, true) : $html;

		return $result;
	}

}