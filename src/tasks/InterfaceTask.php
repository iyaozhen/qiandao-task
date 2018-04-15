<?php
/**
 * @author iyaozhen
 * Date: 2018-04-10
 */

namespace Qiandao\tasks;


interface InterfaceTask
{
    /**
     * InterfaceTask constructor.
     * @param array $params
     */
    function __construct(array $params);

    /**
     * 检查参数
     * @return array
     */
    public function checkParams();

    /**
     * 运行
     * @return array
     */
    public function run();
}