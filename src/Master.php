<?php
/**
 * @author iyaozhen
 * Date: 2018-03-10
 */

namespace Qiandao;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryUsageProcessor;
use Qiandao\common\ErrorCode;
use Swoole;


class Master
{
    private $conf;
    private $taskers = [];
    private $reactors = [];
    private $stopping = false;
    private $pid;
    private $pidFile = __DIR__ . '/../bin/run.pid';
    /**
     * @var Logger
     */
    private $masterLogger;
    /**
     * @var Logger
     */
    private $taskLogger;
    /**
     * @var Logger
     */
    private $reactorLogger;

    /**
     * Master constructor.
     * @param $config array 配置参数
     * @throws \Exception
     */
    function __construct($config)
    {
        // 守护进程
        Swoole\Process::daemon();
        cli_set_process_title('qiandao: master process');

        $this->conf = $config;
        $this->masterLogger = new Logger('qiandao:master');
        $this->masterLogger->pushHandler(
            new StreamHandler(__DIR__ . '/../log/run.log', $this->conf['log_level'])
        );
        $this->masterLogger->pushProcessor(new MemoryUsageProcessor());
        // 自定义错误处理，将php错误写入日志
        set_error_handler([$this, 'errorHandler']);

        $this->pid = getmypid();
        file_put_contents($this->pidFile, $this->pid);
        // 退出时删除pid文件
        register_shutdown_function('unlink', $this->pidFile);

        $this->reactorLogger = new Logger('qiandao:reactor');
        $this->reactorLogger->pushHandler(
            new StreamHandler(__DIR__ . '/../log/run.log', $this->conf['log_level'])
        );
        $this->reactorLogger->pushProcessor(new MemoryUsageProcessor());

        $this->taskLogger = new Logger('qiandao:task');
        $this->taskLogger->pushHandler(
            new StreamHandler(__DIR__ . '/../log/run.log', $this->conf['log_level'])
        );
        $this->taskLogger->pushProcessor(new MemoryUsageProcessor());
    }

    /**
     * 运行入口
     */
    public function run()
    {
        $this->masterLogger->info("master pid=" . $this->pid);
        $this->runTasker();
        $this->runReactor();

        // master进程相关设置
        // 处理kill信号
        Swoole\Process::signal(SIGTERM, function ($signo) {
            $this->masterLogger->notice("signal $signo, shutdown");
            $this->exit();
        });
        // 处理子进程退出信号
        Swoole\Process::signal(SIGCHLD, function ($signo) {
            $this->masterLogger->notice("signal $signo, had child process exit");
            // 必须为false，非阻塞模式
            while ($ret = Swoole\Process::wait(false)) {
                $pid = $ret['pid'];
                $exitCode = $ret['code'];

                if (array_key_exists($pid, $this->reactors)) {
                    // reactor进程
                    $this->masterLogger->notice("reactor exit, pid=$pid code=$exitCode");
                    unset($this->reactors[$pid]);
                    // 致命错误，程序结束
                    if (in_array($exitCode, [ErrorCode::REDIS_ERROR])) {
                        $this->masterLogger->error("fatal error, all process exit");
                        $this->exit();
                    }
                    // 重启进程
                    if (!$this->stopping) {
                        $this->runReactor();
                    }
                } elseif (array_key_exists($pid, $this->taskers)) {
                    // tasker进程
                    $this->masterLogger->notice("tasker exit, pid=$pid code=$exitCode");
                    unset($this->taskers[$pid]);

                    if (!$this->stopping) {
                        $this->runTasker();
                    }
                } else {
                    // 未知进程
                    $this->masterLogger->notice("unknown process exit, pid=$pid code=$exitCode");
                }
            }
        });
        // 定时检查线程和进程间队列
        swoole_timer_tick(60 * 1000, function () {
            /**
             * @var $reactor Swoole\Process
             */
            $reactor = array_values($this->reactors)[0];
            $this->masterLogger->info("stat queue", $reactor->statQueue());
            // 检查子进程是否存在
            foreach ($this->taskers as $pid => $process) {
                if (Swoole\Process::kill($pid, 0)) {
                    $this->masterLogger->info("tasker ok, pid=$pid");
                } else {
                    unset($this->taskers[$pid]);
                    $this->masterLogger->error("tasker not exit, pid=$pid");
                }
            }
            $this->runTasker();

            foreach ($this->reactors as $pid => $process) {
                if (Swoole\Process::kill($pid, 0)) {
                    $this->masterLogger->info("reactor ok, pid=$pid");
                } else {
                    unset($this->reactors[$pid]);
                    $this->masterLogger->error("reactor not exit, pid=$pid");
                }
            }
            $this->runReactor();
        });
    }

    /**
     * 启动reactor进程
     */
    private function runReactor()
    {
        if (empty($this->reactors)) {
            $reactor = new Reactor($this->conf, $this->reactorLogger);
            $reactorProcess = new Swoole\Process([$reactor, 'run'], false, false);
            $reactorProcess->useQueue();
            $pid = $reactorProcess->start();
            if ($pid !== false) {
                $this->reactors[$pid] = $reactorProcess;
                $this->masterLogger->info("run reactor success, pid=$pid");
            } else {
                $this->masterLogger->error("run reactor failure");
            }
        }
    }

    /**
     * 启动task进程
     */
    private function runTasker()
    {
        while (count($this->taskers) < $this->conf['task_num']) {
            $tasker = new Tasker($this->conf, $this->taskLogger);
            $taskProcess = new Swoole\Process([$tasker, 'run'], false, false);
            $taskProcess->useQueue();
            $pid = $taskProcess->start();
            if ($pid !== false) {
                $this->taskers[$pid] = $taskProcess;
                $this->masterLogger->info("run tasker success, pid=$pid");
            } else {
                $this->masterLogger->error("run tasker failure");
            }
        }
    }

    /**
     * 平滑退出进程
     */
    private function exit()
    {
        $this->stopping = true;
        // 结束子进程
        foreach ($this->reactors as $pid => $process) {
            Swoole\Process::kill($pid);
            $this->masterLogger->notice("kill reactor, pid=$pid");
        }
        foreach ($this->taskers as $pid => $process) {
            Swoole\Process::kill($pid);
            $this->masterLogger->notice("kill tasker, pid=$pid");
        }

        exit(0);
    }

    /**
     * 自定义错误处理
     *
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     * @return bool
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }
        /**
         * @link http://php.net/manual/zh/errorfunc.constants.php
         */
        switch ($errno) {
            case E_WARNING:
                $errnoStr = 'E_WARNING';
                break;
            case E_NOTICE:
                $errnoStr = 'E_NOTICE';
                break;
            case E_DEPRECATED:
                $errnoStr = 'E_DEPRECATED';
                break;
            case E_USER_ERROR:
                $errnoStr = 'E_USER_ERROR';
                break;
            case E_USER_WARNING:
                $errnoStr = 'E_USER_WARNING';
                break;
            case E_USER_NOTICE:
                $errnoStr = 'E_USER_NOTICE';
                break;
            case E_USER_DEPRECATED:
                $errnoStr = 'E_USER_DEPRECATED';
                break;
            case E_RECOVERABLE_ERROR:
                $errnoStr = 'E_RECOVERABLE_ERROR';
                break;
            default:
                $errnoStr = "ERROR[$errno]";
        }

        $this->masterLogger->error("PHP $errnoStr: $errstr. In $errfile:$errline");

        return true;
    }
}