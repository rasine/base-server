<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/4/19
 * Time: 16:12
 */

namespace ESD\BaseServer\Plugins\Event;


class ProcessEvent extends Event
{
    const ProcessStartEvent = "ProcessStartEvent";
    const ProcessStopEvent = "ProcessStopEvent";
}