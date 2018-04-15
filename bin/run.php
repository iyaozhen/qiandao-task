<?php

use Qiandao\Master;

require "../vendor/autoload.php";
$config = require("../conf/config.php");

$cmd = isset($argv[1]) ? $argv[1] : 'help';

switch ($cmd) {
    case 'start':
    case 'restart':
        stop();
        start();
        break;
    case 'stop':
        stop();
        break;
    case 'status':
        status();
        break;
    default:
        echo "php run.php start|stop|restart|status\n";
}

/**
 * 启动服务
 * @throws Exception
 */
function start()
{
    global $config;

    $master = new Master($config);
    $master->run();
    echo "start success\n";
}

/**
 * 停止服务
 */
function stop()
{
    $masterPid = @file_get_contents('./run.pid');
    if (empty($masterPid)) {
        echo "stop notice, master pid is empty, maybe service didn't run\n";
    }
    else {
        // 检测进程是否真的存在
        if (posix_kill($masterPid, 0)) {
            // 发送退出信号
            posix_kill($masterPid, SIGTERM);
            // 确认退出成功
            do {
                sleep(1);
                echo "waiting service exit\n";
            } while (posix_kill($masterPid, 0));

            echo "stop success\n";
        }
        else {
            echo "stop warning, pid not exist\n";
        }
    }
}

/**
 * 查看服务状态
 * TODO 显示队列信息
 */
function status()
{
    passthru("ps aufx | grep -E 'COMMAND|qiandao' | grep -v 'grep'");
    echo "\n";
}