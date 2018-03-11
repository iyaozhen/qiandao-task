<?php
/**
 * @author iyaozhen
 * Date: 2018-03-10
 */

namespace Qiandao;

use Redis;
use Swoole;
use Psr\Log\LoggerInterface;

class Reactor
{
    private $redisConf;
    /**
     * @var LoggerInterface
     */
    private $log;

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

        $redis = new Redis();
        try {
            $redis->connect(
                $this->redisConf['host'],
                $this->redisConf['port'],
                $this->redisConf['timeout'],
                null, 1000
            );
        } catch (\RedisException $e) {
            $this->log->error('connect redis failure, ' . $e->getMessage());
            $worker->exit(ErrorCode::REDIS_ERROR);
        }

        while (true) {
            $taskData = $redis->brPop($this->redisConf['list_name'], 10);
            if (!empty($taskData)) {
                if ($worker->push($taskData[1])) {
                    continue;
                } else {
                    $worker->exit(ErrorCode::QUEUE_ERROR);
                }
            } else {
                $this->log->notice("no more task");
            }
        }
    }
}