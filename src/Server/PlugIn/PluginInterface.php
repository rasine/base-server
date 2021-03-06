<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/4/18
 * Time: 12:25
 */

namespace ESD\BaseServer\Server\PlugIn;


use ESD\BaseServer\Coroutine\Channel;
use ESD\BaseServer\Server\Context;

interface PluginInterface
{
    /**
     * @return Channel
     */
    public function getReadyChannel();

    /**
     * @param PluginInterface $root
     * @param int $layer
     * @return int
     */
    public function getOrderIndex(PluginInterface $root, int $layer): int;

    /**
     * @param mixed $afterPlug
     */
    public function addAfterPlug(PluginInterface $afterPlug);

    /**
     * @param $className
     * @return void
     */
    public function atAfter(...$className);

    /**
     * @param $className
     * @return void
     */
    public function atBefore(...$className);

    /**
     * 获取插件名字
     * @return string
     */
    public function getName(): string;

    /**
     * 在服务启动前
     * @param Context $context
     * @return mixed
     */
    public function init(Context $context);

    /**
     * 初始化
     * @param Context $context
     * @return mixed
     */
    public function beforeServerStart(Context $context);

    /**
     * 在进程启动前
     * @param Context $context
     * @return mixed
     */
    public function beforeProcessStart(Context $context);

    /**
     * @return array
     */
    public function getAfterClass(): array;

    /**
     * @return array
     */
    public function getBeforeClass(): array;

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager);

}