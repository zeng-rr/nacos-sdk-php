<?php
/**
 * Created by PhpStorm.
 * User: huangwh
 * Date: 2021/12/30
 * Time: 10:10
 */

namespace Alicloud\ConfigMonitor;
use Alicloud\ConfigMonitor\nacos\listener\config\GetConfigRequestErrorListener;
use Alicloud\ConfigMonitor\nacos\listener\config\ListenerConfigRequestListener;
use Alicloud\ConfigMonitor\nacos\Nacos;
use Alicloud\ConfigMonitor\nacos\NacosConfig;
use Alicloud\ConfigMonitor\nacos\util\LogUtil;


/**
 * Class MonitorHandle
 * @package AlicloudMonitor
 */
class MonitorHandle
{
    
    //轮询时间
    protected $pullingSeconds = 30;

    //nacos请求地址，包括端口
    protected $nacosHost = null;
    
    //区分配置文件环境
    protected $env = null;
    
    //配置项名称：即项目名称
    protected $dataId = null;
    
    //项目分组：
    protected $group = 'DEFAULT_GROUP';
    
    //租户信息：即命名空间，如果此项目的配置不同部署节点会不一样，则可以设置为多个命名空间
    protected $nameSpaceId = '';
    
    //是否修改laravel 本地 env文件
    protected $changeToEnvFile = null;
    
    
    /**
     * 初始化配置
     * MonitorHandle constructor.
     * @param string  $host 访问地址, IP需加端口
     * @param string  $nameSpaceId 命名空间id
     * @param string  $dataId 数据集id
     * @param string  $changeToEnvFile  实际要用于变更的 项目env文件
     * @param string  $group 分组名
     * @param integer $pullingSeconds  轮询时间 默认30秒
     * @param string  $env 是本地的环境，不同的环境会生成不同的快照目录
     * @throws
     */
    public function __construct($host, $nameSpaceId, $dataId, $changeToEnvFile = '', $group = 'DEFAULT_GROUP', $pullingSeconds = 30, $env = 'dev')
    {
        if (!empty($changeToEnvFile)){
            $dirinfo = pathinfo($changeToEnvFile);
            if (!is_dir($dirinfo['dirname'])){
                throw new \LogicException(sprintf("env文件路径 %s 不存在,请先创建. %s", $dirinfo['dirname'], PHP_EOL), 1);
            }
            $this->changeToEnvFile = $changeToEnvFile;
        }

        //轮询时间
        $pullingSeconds = intval($pullingSeconds);
        $pullingSeconds = ($pullingSeconds >60  || $pullingSeconds <1) ? 30 : $pullingSeconds;
        $this->pullingSeconds = $pullingSeconds;

        //初始化一些值
        $this->nacosHost = $host;
        $this->dataId   = $dataId;
        $this->env      = $env;
        $this->nameSpaceId = $nameSpaceId;
        $this->group = $group;
        
        //设置值，其它参数在init中传入
        NacosConfig::setIsDebug(false);
        NacosConfig::setLongPullingTimeout($pullingSeconds * 1000);
        
    }
    
    /**
     * 执行监听
     * @param bool $polling 是否进行长监听, 否则只监听一次
     * @param string|null $currentConf 当前配置文件内容, 默认从快照读取, 用于监听内容对比
     */
    public function listenNotify($polling = true, $currentConf = null)
    {
        //注册一个出错通知
        GetConfigRequestErrorListener::add(function($config) {
            if (!$config->getConfig()) {
                //LogUtil::error("获取配置异常，不做任何变更..." . PHP_EOL);
                $config->setChanged(false);  //出错不修改
            }
        });
        
        //添加获取配置变更通知
        ListenerConfigRequestListener::add(function ($config) {
            if ($config){
                LogUtil::info("监听到配置有变更，已经更新到本地ENV文件... \n");
                if (!empty($this->changeToEnvFile)) {
                    //这里会更新到本地env文件
                    file_put_contents($this->changeToEnvFile, $config);
                }
            }
        });
    
    
        LogUtil::info(sprintf("配置监听中，长轮询时间为%s秒 ... %s", $this->pullingSeconds, PHP_EOL));
        Nacos::init(
            $this->nacosHost,
            $this->env,
            $this->dataId,
            $this->group,
            $this->nameSpaceId
        )->listener($polling, $currentConf);
        
    }
    
    
}