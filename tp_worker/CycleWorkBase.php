<?php
/**
 * Created by PhpStorm.
 * User: Mikkle
 * QQ:776329498
 * Date: 2017/10/19
 * Time: 10:10
 */

namespace mikkle\tp_worker;


use mikkle\tp_master\Db;
use mikkle\tp_master\Exception;
use mikkle\tp_master\Log;
use mikkle\tp_tools\Time;

abstract class CycleWorkBase
{
    protected $listName;
    protected $redis;
    protected $workList;
    protected $workerName;
    public static $instance;
    protected  $connect =[] ;
    protected $saveLog=false;
    protected $tableName = "mk_log_service_queue";
    protected $error;
    protected $stopTime;
    protected $startTime;

    /**
     * Base constructor.
     * @param array $options
     */
    public function __construct($options=[])
    {
        $this->_initialize();
        $this->redis = $this->redis();
        $this->workList = "worker_list";
        $this->workerName = get_called_class();
        $this->listName = md5($this->workerName);

    }
    public function _initialize(){

    }

    abstract  protected function runCycleHandle($data);
    protected function runHandle($data)
    {
        try{
            while ( true ){
                self::signWorking();
                $sn = uniqid();
                if (!empty(self::instance()->startTime ) && is_int( self::instance()->startTime ) ){
                    if ( time()>strtotime( self::instance()->startTime )){
                        $instance=self::instance($sn);
                        $instance->pcntlWorker($data);
                        $instance->clearSn($sn);
                        Log::notice("执行了程序pcntlWorker".$sn);
                    }else{
                        Log::notice("未到执行时间".$sn);
                    }
                }else{
                    $instance=self::instance($sn);
                    $instance->pcntlWorker($data);
                    $instance->clearSn($sn);
                    Log::notice("执行了程序pcntlWorker".$sn);
                }

                Log::notice("循环执行程序执行程序".$sn);
                $time = $this->getNextRunTime();
                $this->sleep($time);
                if ( $this->checkWorkingStop() ){
                    break;
                }
            }
            $this-> clearWorkingWork();
            Log::notice("循环执行程序执结束");
        }catch (Exception $e){
            Log::notice( $e ->getMessage());
        }
    }

    protected function getNextRunTime(){
        if (!empty( $this->nextTime ) && is_int( $this->nextTime)){
            return $this->nextTime;
        }
        return 10;
    }

    /**
     * @title redis
     * @description redis加载自定义Redis类
     * User: Mikkle
     * QQ:776329498
     * @return \mikkle\tp_redis\Redis
     */
    protected static function redis()
    {
        return WorkerRedis::instance();
    }


    /**
     * @title runWorker
     * @description 标注命令行执行此任务
     * User: Mikkle
     * QQ:776329498
     * @param string $handleName
     */
    public function runWorker($handleName="run"){
        $this->redis->hset($this->workList,$this->workerName,$handleName);
    }

    /**
     * 标注命令行清除此任务
     * Power: Mikkle
     * Email：776329498@qq.com
     */
    public function clearWorker(){
        $this->redis->hdel($this->workList,$this->workerName);
    }


    /**
     * Power: Mikkle
     * Email：776329498@qq.com
     * @param array $options
     * @return static
     */
    static public function instance($options=[]){
        $sn = md5(json_encode($options));
        if (self::$instance[$sn]){
            return self::$instance[$sn];
        }
        return  new static($options);
    }
    protected function clearSn($options=[]){
        $sn = md5(json_encode($options));
        if (self::$instance[$sn]){
            unset( self::$instance[$sn] ) ;
        }
    }


    /**
     *
     * 当命令行未运行 直接执行
     * description add
     * User: Mikkle
     * QQ:776329498
     * @param $data
     * @return bool
     */
    static public function start($data=[]){
        try{
            $data=json_encode($data);
            $instance = static::instance();
            self::clearWorkingStop();
            switch (true){
                case (self::checkWorking()):
                    Log::notice("Work service is Running!!");
                    break;
                case (self::checkCommandRun()):
                    $instance->redis->lpush($instance->listName,$data);
                    Log::notice("Command service start work!!");
                    $instance->runWorker();
                    break;
                default:
                    Log::notice("Command service No away!!");
                    $instance->runHandle($data);
            }
            return true;
        }catch (Exception $e){
            Log::error($e->getMessage());
            return false;
        }
    }

    /**
     *
     * 当命令行未运行 直接执行
     * description add
     * User: Mikkle
     * QQ:776329498
     */
    static public function stop(){
           return self::signWorkingStop();
    }

    static public function status(){
        return self::checkWorking();
    }


    static  public function signWorking($time=200){
        self::redis()->set(get_called_class()."_run","true",$time);
    }

    static public function checkWorking(){
        return self::redis()->get(get_called_class()."_run") ? true :false;
    }
    static  public function clearWorkingWork(){
         self::redis()->delete( get_called_class()."_run");
        self::redis()->delete( get_called_class()."_stop");
    }

    static  public function signWorkingStop($time=600){
        self::redis()->delete( get_called_class()."_run");
       return self::redis()->set(get_called_class()."_stop","true",$time);
    }

    static  public function clearWorkingStop(){
        return self::redis()->delete( get_called_class()."_stop");
    }

    static public function checkWorkingStop(){
        if (!empty(self::instance()->stopTime )){
            if ( time()>strtotime( self::instance()->stopTime )){
                self::redis()->delete( get_called_class()."_run");
                return true;
            }else{
                return false;
            }
        }
        return self::redis()->get(get_called_class()."_stop") ? true :false;
    }


    /**
     * 命令行执行的方法
     * Power: Mikkle
     * Email：776329498@qq.com
     */
    static public function run(){
        $instance = static::instance();
        try{
            $i = 0;
            while(true){
                $redisData= $instance->redis->rpop($instance->listName);
                $data = json_decode($redisData,true);
                if ($data){
                    $instance->runHandle($data);
                }else{
                    $instance->clearWorker();
                    break;
                }
                $i++;
                sleep(1);
            }
            echo "执行了{$i}次任务".PHP_EOL;
        }catch (Exception $e){
            Log::error($e);
            Log::error($e->getMessage());
            echo ($e->getMessage());
        }
    }


    /**
     * 检测命令行是否执行中
     * Power: Mikkle
     * Email：776329498@qq.com
     * @return bool
     */
    static public function checkCommandRun(){
        return self::redis()->get("command") ? true :false;
    }

    public function getError(){
        if (is_array($this->error )){
            return json_encode( $this->error );
        }
        return $this->error;
    }

    /*
 * 检查是注重某些值是非为空
 */
    protected function checkArrayValueEmpty($array,$value,$error=true){
        switch (true){
            case (empty($array)||!is_array($array)):
                if ($error==true){
                    $this->addError("要检测的数据不存在或者非数组");
                }
                return false;
                break;
            case (is_array($value)):
                foreach ($value as $item){
                    if (!isset($array[$item]) || (empty($array[$item]) && $array[$item]!==0)){
                        if ($error==true) {
                            $this->addError("要检测的数组数据有不存在键值{$item}");
                        }
                        return false;
                    }
                }
                break;
            case (is_string($value)):
                if (!isset($array[$value]) || empty($array[$value] && $array[$value]!==0)){
                    if ($error==true) {
                        $this->addError("要检测的数组数据有不存在键值{$value}");
                    }
                    return false;
                }
                break;
            default:
        }
        return true;
    }
    public function addError($error){
        $this->error = is_string($error) ? $error : json_encode($error);
    }


    protected function saveRunLog($result,$data){
        try{
            $operateData = [
                "class" => $this->workerName,
                "args" =>  json_encode($data),
                "result"=> $result ? "true":"false",
                "error" => $this->error ? $this->getError() : null,
                "time" => Time::getDefaultTimeString(),
            ];
            Db::connect($this->connect)->table($this->tableName)->insert($operateData);
        }catch (Exception $e){
            Log::error($e->getMessage());
        }
    }

    protected function  sleep($time=1){
        sleep(sleep($time));
    }

    /**
     * title 分进程
     * description pcntlWorker
     * User: Mikkle
     * QQ:776329498
     * @param $data
     */
    protected function pcntlWorker($data)
    {
        try{
            // 通过pcntl得到一个子进程的PID
            $pid = pcntl_fork();
            if ($pid == -1) {
                // 错误处理：创建子进程失败时返回-1.
                die ('could not fork');
            } else if ($pid) {
                // 父进程逻辑

                // 等待子进程中断，防止子进程成为僵尸进程。
                // WNOHANG为非阻塞进程，具体请查阅pcntl_wait PHP官方文档
                pcntl_wait($status, WNOHANG);
            } else {
                // 子进程逻辑
                $pid_2 = pcntl_fork();
                if ($pid_2 == -1) {
                    // 错误处理：创建子进程失败时返回-1.
                    die ('could not fork');
                } else if ($pid_2) {
                    // 父进程逻辑
                    echo "父进程逻辑开始" . PHP_EOL;
                    // 等待子进程中断，防止子进程成为僵尸进程。
                    // WNOHANG为非阻塞进程，具体请查阅pcntl_wait PHP官方文档
                    pcntl_wait($status, WNOHANG);
                    echo "父进程逻辑结束" . PHP_EOL;
                } else {
                    // 子进程逻辑
                    echo "子进程逻辑开始" . PHP_EOL;
                    $this->runCycleHandle( $data );
                    echo "子进程逻辑结束" . PHP_EOL;
                    $this->pcntlKill();
                }
                $this->pcntlKill();
            }
        }catch (Exception $e){
            Log::error($e->getMessage());
        }
    }
    /**
     * Kill子进程
     * Power: Mikkle
     * Email：776329498@qq.com
     */
    protected function pcntlKill(){
        // 为避免僵尸进程，当子进程结束后，手动杀死进程
        if (function_exists("posix_kill")) {
            posix_kill(getmypid(), SIGTERM);
        }
            system('kill -9 ' . getmypid());

        exit ();
    }


}