<?php
/**
 * @author iyaozhen
 * Date: 2018-03-10
 */

namespace Qiandao;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\MemoryUsageProcessor;
use Swoole;

class Master
{
    /**
     * @var Logger
     */
    private $log;
    private $conf;

    /**
     * Master constructor.
     * @param $config array 配置参数
     */
    function __construct($config)
    {
        $this->conf = $config;
    }

    /**
     * 运行入口
     * @throws \Exception
     */
    public function run()
    {
        // 启动reactor进程
        $reactorLogger = new Logger('qiandao:reactor');
        $reactorLogger->pushHandler(new StreamHandler(__DIR__ . '/../log/run.log', $this->conf['log_level']));
        $reactorLogger->pushProcessor(new MemoryUsageProcessor());
        $reactor = new Reactor($this->conf, $reactorLogger);
        $reactorProcess = new Swoole\Process([$reactor, 'run'], false, false);
        $reactorProcess->useQueue();
        $reactorProcess->start();

        // 启动task进程
        $taskLogger = new Logger('qiandao:task');
        $taskLogger->pushHandler(new StreamHandler(__DIR__ . '/../log/run.log', $this->conf['log_level']));
        $taskLogger->pushProcessor(new MemoryUsageProcessor());
        for ($i = 0; $i < $this->conf['task_num']; $i++) {
            $taskProcess = new Swoole\Process(function (Swoole\Process $worker) use ($taskLogger) {
                $worker->name('qiandao: tasker process');
                while (true) {
                    $taskData = $worker->pop();
                    $taskLogger->debug($taskData);
                    if (!empty($taskData)) {
                        continue;
                    } else {
                        $taskLogger->error('pop data empty');
                    }
                }
            }, false, false);
            $taskProcess->useQueue();
            $taskProcess->start();
        }

        $this->_run();
    }

    /**
     * master进程的一些工作
     * @throws \Exception
     */
    private function _run()
    {
        cli_set_process_title('qiandao: master process');
        $this->log = new Logger('qiandao:master');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/../log/run.log', $this->conf['log_level']));
        $this->log->pushProcessor(new MemoryUsageProcessor());
        Swoole\Process::signal(SIGTERM, function($signo) {
            $this->log->notice('shutdown.');
            // TODO 通知子进程结束工作
            exit(0);
        });
        Swoole\Process::signal(SIGCHLD, function($sig) {
            //必须为false，非阻塞模式
            while($ret =  Swoole\Process::wait(false)) {
                // TODO 重启子进程
                $this->log->notice("PID={$ret['pid']}");
            }
        });
        // 守护进程
        Swoole\Process::daemon();
    }
}