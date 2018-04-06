<?php
/**
 * @author iyaozhen
 * Date: 2018-03-15
 */

namespace Qiandao;

use Swoole;
use Psr\Log\LoggerInterface;

class Tasker
{
    /**
     * @var LoggerInterface
     */
    private $log;
    private $config;

    /**
     * Reactor constructor.
     * @param $config array 配置参数
     * @param $logger LoggerInterface 日志模块对象
     */
    function __construct($config, $logger)
    {
        $this->config = $config;
        $this->log = $logger;
    }

    /**
     * 运行入口
     * @param Swoole\Process $worker
     */
    public function run(Swoole\Process $worker)
    {
        $worker->name('qiandao: tasker process');

        while (true) {
            $taskData = $worker->pop();
            $this->log->debug($taskData);
            if ($taskData !== false) {
                continue;
            } else {
                $this->log->error('pop from queue failure', $worker->statQueue());
            }
        }
    }
}