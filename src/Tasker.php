<?php
/**
 * @author iyaozhen
 * Date: 2018-03-15
 */

namespace Qiandao;

use Psr\Log\LoggerInterface;
use Swoole;


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
            $this->log->debug('task data', $taskData);
            if ($taskData !== false) {
                $taskInfo = unserialize($taskData);
                $taskId = $taskInfo['task_id'];
                $taskName = $taskInfo['task_name'];
                $taskParams = $taskInfo['task_params'];
                $taskClass = "\\Qiandao\\tasks\\$taskName";
                /**
                 * @var $taskObj \Qiandao\tasks\AbstractTask
                 */
                $taskObj = new $taskClass($taskParams);
                $checkResult = $taskObj->checkParams();
                if ($checkResult['code'] == 200) {
                    $runResult = $taskObj->run();
                    if ($runResult['code'] == 200) {
                        // TODO update $taskId status
                        $this->log->info("$taskClass run success", $taskInfo);
                    } else {
                        $this->log->error("$taskClass run failure", $taskInfo);
                    }
                } else {
                    $this->log->error("$taskClass check params failure", $taskInfo);
                }
            } else {
                $this->log->error('pop from queue failure', $worker->statQueue());
            }
        }
    }
}