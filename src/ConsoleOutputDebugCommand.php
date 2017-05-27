<?php

namespace Frostrain\Laravel\ConsoleDebug;

use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\TableSeparator;

class ConsoleOutputDebugCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'console:output-debug-info';
    /**
     * for laravel 5.0
     * @var string
     */
    protected $name = 'console:output-debug-info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'output debug info';

    protected $debugbar;
    /**
     * @var int
     */
    protected $columnLengthLimit;
    /**
     * @var array
     */
    protected $debugMessageStyles;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // TODO: 实现更多的信息收集(timeline/exceptions等)

        // 如果没有 vorbose 参数, 直接返回, 不打印任何信息
        if (!$this->output->isVerbose()) {
            return;
        }
        $this->debugbar = $this->laravel->make('debugbar');
        $this->columnLengthLimit = config('console_debug.column_length_limit');
        $this->debugMessageStyles = config('console_debug.debug_message_styles');

        // 实际上可以自己设定样式, 见 http://symfony.com/doc/current/console/coloring.html
        // 支持的颜色: black, red, green, yellow, blue, magenta, cyan, white
        // 字体效果: bold, underscore 等
        // $this->line('12asdf', 'fg=black;bg=cyan;options=bold,underscore');

        $this->outputMessages();
        $this->outputSqls();
    }

    protected function outputMessages()
    {
        if (!$this->debugbar->hasCollector('messages')) {
            return;
        }

        // messages 的数组中的元素看起来是这样
        // ['message'=>'foo', 'is_string'=>'true', 'label'=>'info', 'time'=> ...]
        // is_string 对于 数字 会是 false
        $messageData = $this->debugbar->getCollector('messages')->collect();

        $count = $messageData['count'];
        $messages = $messageData['messages'];

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $msg = $messages[$i];
            $rows[] = $this->handleMessage($msg);
            // $rows[] = $msg;
            // 在每条信息之间加上一条横岗
            if ($i < $count - 1) {
                $rows[] = new TableSeparator();
            }
        }

        if (!empty($rows)) {
            $this->info('');
            // table 方法传入的 $header 和 $data 最好是 元素数目相等, 并且顺序对应...
            // 否则结果会比较怪异
            $header = ['level', 'debug message'];
            // table 方法无法设置 verbosity ...
            $this->table($header, $rows);
        }
    }

    /**
     * 为 debug消息 添加输出样式
     */
    protected function handleMessage($msg)
    {
        // $this->line('info) 等输出的颜色:
        // line: 白, info: 绿, warn: 黄, error: 红底白字
        // comment: 黄(等于warn..), question: 蓝底.., alert: 黄色的box包围..

        // level 类型: debug, info, notice, warning, error, critical, alert, emergency
        $level = $msg['label'];
        $message = $msg['message'];
        if (isset($this->debugMessageStyles[$level]) && $style = $this->debugMessageStyles[$level]) {
            $level = "<$style>$level</$style>";
            $message = "<$style>$message</$style>";
        }

        return compact('level', 'message');
    }

    protected function outputSqls()
    {
        if (!$this->debugbar->hasCollector('queries')) {
            return;
        }

        // collect() 方法用于返回收集器的数据
        $statements = $this->debugbar->getCollector('queries')->collect()['statements'];
        $count = count($statements);
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $query = $statements[$i];
            $sql = $this->addLineBreak($query['sql'], $this->columnLengthLimit);
            // $sql = $query['sql'];
            $rows[] = [$sql, $query['duration_str']];
            if ($i < $count - 1) {
                $rows[] = new TableSeparator();
            }
        }

        // 部分输出方法可以设置 verbosity
        // 比如 info() 的第二个参数, vvv 就会会显示所有 v/vv/vvv 的信息, 类推
        // $this->info('very verbose info', 'vv');

        // info(): 绿色, warn(): 黄色

        if (!empty($rows)) {
            $this->line('');
            // table 方法传入的 $header 和 $data 最好是 元素数目相等, 并且顺序对应...
            // 否则结果会比较怪异
            $header = ['sql', 'duration'];
            // table 方法无法设置 verbosity ...
            $this->table($header, $rows);
        }
    }

    /**
     * 以 空格 为边界自动给长字符串添加 换行符. 用于 cli 表格输出.
     * @param string $str
     * @param int $columnLimit
     */
    protected function addLineBreak($str, $columnLimit = 80)
    {
        // 最小不低于 60
        $columnLimit = max($columnLimit, 60);
        $r = [];
        while (($rest = strlen($str)) > $columnLimit) {
            $pos = strrpos($str, " ", -($rest - $columnLimit));
            if ($pos === false) {
                break;
            }
            $r[] = substr($str, 0, $pos);
            $str = substr($str, $pos);
        }
        $r[] = $str;
        return implode("\n", $r);
    }
}
