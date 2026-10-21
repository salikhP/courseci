<?php

namespace App\Enums;

enum GradingStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Passed = 'passed';
    case Failed = 'failed';
    case TimedOut = 'timed_out';
    case SystemError = 'system_error';
}
