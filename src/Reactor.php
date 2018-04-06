<?php
/**
 * @author iyaozhen
 * Date: 2018-03-10
 */

namespace Qiandao;

use Swoole;
use Psr\Log\LoggerInterface;
use Qiandao\redis\RedisWrap;
use \RedisException;

class Reactor
{
    private $redisConf;
    /**
     * @var LoggerInterface
     */
    private $log;
    /**
     * @var array 等待被投递的task数据
     */
    private $waitingData;

    /**
     * Reactor constructor.
     * @param $config array 配置参数
     * @param $logger LoggerInterface 日志模块对象
     */
    function __construct($config, $logger)
    {
        $this->redisConf = $config['task_redis'];
        $this->log = $logger;
    }

    /**
     * 运行入口
     * @param Swoole\Process $worker
     */
    public function run(Swoole\Process $worker)
    {
        $worker->name('qiandao: reactor process');

        try {
            $redis = RedisWrap::getInstance($this->redisConf['host'], $this->redisConf['port']);
        } catch (RedisException $e) {
            $this->log->error('connect redis failure, ' . $e->getMessage());
            $worker->exit(ErrorCode::REDIS_ERROR);
        }

        while (true) {
            try {
                $popData = $redis->brPop($this->redisConf['list_name'], 10);

                if (!empty($popData)) {
                    array_push($this->waitingData, $popData[1]);
                } else {
                    $this->log->notice("no more task");
                }
            } catch (RedisException $e) {
                // 运行过程出错，打印日志，不退出
                $this->log->error('brPop redis failure, ' . $e->getMessage());
            }

            while (!empty($this->waitingData)) {
                $taskData = array_pop($this->waitingData);
                if ($worker->push($taskData) === false) {
                    // 进程间队列投递失败，重新放入数据，并退出本轮次投递
                    $this->log->error("push to queue failure", $worker->statQueue());
                    array_push($this->waitingData, $taskData);
                    break;
                }
            }
        }
    }
}