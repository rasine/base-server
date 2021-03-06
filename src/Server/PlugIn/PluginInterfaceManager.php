<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/4/18
 * Time: 12:24
 */

namespace ESD\BaseServer\Server\PlugIn;

use DI\ContainerBuilder;
use ESD\BaseServer\Coroutine\Channel;
use ESD\BaseServer\Exception;
use ESD\BaseServer\Plugins\Event\EventDispatcher;
use ESD\BaseServer\Server\Context;
use ESD\BaseServer\Server\Server;
use Monolog\Logger;

/**
 * 插件管理器
 * Class PlugManager
 * @package ESD\BaseServer\Server\Plug
 */
class PluginInterfaceManager implements PluginInterface
{
    /**
     * @var PluginInterface[]
     */
    private $plugs = [];

    /**
     * @var PluginInterface[]
     */
    private $plugClasses = [];

    /**
     * @var bool
     */
    private $fixed = false;

    /**
     * @var Server
     */
    private $server;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var Channel
     */
    private $readyChannel;

    /**
     * @var ContainerBuilder
     */
    private $containerBuilder;

    public function __construct(Server $server)
    {
        $this->server = $server;
        $this->readyChannel = new Channel();
        //baseManager是获取不到的，只有用户插件管理才能获取到
        $this->log = $this->server->getContext()->getDeepByClassName(Logger::class);
        $this->eventDispatcher = $this->server->getContext()->getDeepByClassName(EventDispatcher::class);
        $this->containerBuilder = $this->server->getContext()->getDeepByClassName(ContainerBuilder::class);
    }

    /**
     * 添加插件
     * @param PluginInterface $plug
     * @throws Exception
     */
    public function addPlug(PluginInterface $plug)
    {
        if ($this->fixed) {
            throw new Exception("已经锁定不能添加插件");
        }
        $this->plugs[$plug->getName()] = $plug;
        $this->plugClasses[get_class($plug)] = $plug;
        $plug->onAdded($this);
    }

    /**
     * 获取插件
     * @param String $className
     * @return PluginInterface|null
     */
    public function getPlug(String $className)
    {
        return $this->plugClasses[$className] ?? null;
    }

    /**
     * 初始化
     * @param Context $context
     * @return mixed|void
     */
    public function init(Context $context)
    {
        foreach ($this->plugs as $plug) {
            if ($this->log != null) {
                $this->log->log(Logger::DEBUG, "加载[{$plug->getName()}]插件");
            }
            $plug->init($context);
        }
    }

    /**
     * 在服务启动之前
     * @param Context $context
     * @return mixed|void
     */
    public function beforeServerStart(Context $context)
    {
        //发出PlugManagerEvent:PlugBeforeServerStartEvent事件
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugBeforeServerStartEvent, $this));
        }
        foreach ($this->plugs as $plug) {
            $plug->beforeServerStart($context);
        }
        //发出PlugManagerEvent:PlugAfterServerStartEvent事件
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugAfterServerStartEvent, $this));
        }
    }

    /**
     * 在进程启动之前
     * @param Context $context
     * @return mixed|void
     */
    public function beforeProcessStart(Context $context)
    {
        //发出PlugManagerEvent:PlugBeforeProcessStartEvent事件
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugBeforeProcessStartEvent, $this));
        }
        foreach ($this->plugs as $plug) {
            try {
                $plug->beforeProcessStart($context);
            } catch (\Throwable $e) {
                $this->log->error($e);
                $this->log->error("{$plug->getName()}插件加载失败");
                continue;
            }
            if (!$plug->getReadyChannel()->pop(5)) {
                $plug->getReadyChannel()->close();
                if ($this->log != null) {
                    $this->log->error("{$plug->getName()}插件加载失败");
                }
                if ($this->eventDispatcher != null) {
                    $this->eventDispatcher->dispatchEvent(new PluginEvent(PluginEvent::PlugFailEvent, $plug));
                }
            } else {
                if ($this->eventDispatcher != null) {
                    $this->eventDispatcher->dispatchEvent(new PluginEvent(PluginEvent::PlugSuccessEvent, $plug));
                }
            }
        }
        //发出PlugManagerEvent:PlugAfterProcessStartEvent事件
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugAfterProcessStartEvent, $this));
        }
        $this->readyChannel->push("ready");
    }

    /**
     * 插件排序
     */
    public function order()
    {
        foreach ($this->plugs as $plug) {
            foreach ($this->getPlugBeforeClass($plug) as $needAddAfterPlug) {
                $needAddAfterPlug->addAfterPlug($plug);
            }
            foreach ($this->getPlugAfterClass($plug) as $afterPlug) {
                $plug->addAfterPlug($afterPlug);
            }
        }
        usort($this->plugs, function ($a, $b) {
            if ($a->getOrderIndex($a, 0) > $b->getOrderIndex($b, 0)) {
                return 1;
            } else {
                return -1;
            }
        });
        $this->fixed = true;
    }

    /**
     * @param PluginInterface $plug
     * @return PluginInterface[]
     */
    private function getPlugBeforeClass(PluginInterface $plug): array
    {
        $result = [];
        foreach ($plug->getBeforeClass() as $class) {
            $one = $this->plugClasses[$class] ?? null;
            if ($one != null) {
                $result[] = $one;
            }
        }
        return $result;
    }

    /**
     * @param PluginInterface $plug
     * @return PluginInterface[]
     */
    private function getPlugAfterClass(PluginInterface $plug): array
    {
        $result = [];
        foreach ($plug->getAfterClass() as $class) {
            $one = $this->plugClasses[$class] ?? null;
            if ($one != null) {
                $result[] = $one;
            }
        }
        return $result;
    }

    /**
     * 获取插件名字
     * @return string
     */
    public function getName(): string
    {
        return "PlugManager";
    }

    /**
     * @param int $index
     * @return mixed
     */
    public function setOrderIndex(int $index)
    {
        return;
    }

    /**
     * @param PluginInterface $root
     * @param int $layer
     * @return int
     */
    public function getOrderIndex(PluginInterface $root, int $layer): int
    {
        return 0;
    }

    /**
     * @return mixed
     */
    public function getBeforeClass(): array
    {
        return [];
    }

    /**
     * @return mixed
     */
    public function getAfterClass(): array
    {
        return [];
    }

    /**
     * @param mixed $afterPlug
     */
    public function addAfterPlug(PluginInterface $afterPlug): void
    {
        return;
    }

    /**
     * @return Channel
     */
    public function getReadyChannel(): Channel
    {
        return $this->readyChannel;
    }

    /**
     * 等待
     */
    public function waitReady()
    {
        $this->readyChannel->pop();
        $this->readyChannel->close();
        //发出PlugManagerEvent:PlugAllReadyEvent事件
        if ($this->eventDispatcher != null) {
            $this->eventDispatcher->dispatchEvent(new PluginManagerEvent(PluginManagerEvent::PlugAllReadyEvent, $this));
        }
    }

    public function atAfter(...$className)
    {
        return;
    }

    /**
     * @param $className
     * @return void
     */
    public function atBefore(...$className)
    {
        return;
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        return;
    }

    /**
     * @return Server
     */
    public function getServer(): Server
    {
        return $this->server;
    }
}