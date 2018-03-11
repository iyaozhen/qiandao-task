<?php
// 配置文件

return [
    'task_num' => 3,
    'task_redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 10,
        'list_name' => 'qiandao#tasks'
    ],
    /**
     * @see \Psr\Log\LogLevel
     */
    'log_level' => 'debug',
];