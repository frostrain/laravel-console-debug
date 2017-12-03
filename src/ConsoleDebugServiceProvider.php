<?php

namespace Frostrain\Laravel\ConsoleDebug;

use Illuminate\Support\ServiceProvider;
use Frostrain\Laravel\ConsoleDebug\ConsoleOutputDebugCommand;
use Artisan;

class ConsoleDebugServiceProvider extends ServiceProvider
{
    /**
     * @var boolean
     */
    protected $isEnabled;
    /**
     * @var string
     */
    protected $verboseLevel;

    /**
     * Is debug function enabled.
     * @return boolean
     */
    protected function isEnabled()
    {
        if (is_null($this->isEnabled)) {

            // $argv 类似这样 [0=> 'artisan', 1 => 'test', 2 => '-v']
            // 也就是用 空格 分割所有 命令行参数 获取的数组..
            $argv = $_SERVER['argv'];

            // '--verbose' 等同于 -vv 级别
            $verboseOptions = ['-v', '-vv', '-vvv', '--verbose'];

            $enabled = false;
            // 这里我们要获取命令行参数中的 $verboseLevel
            foreach ($verboseOptions as $option) {
                $r = array_keys($argv, $option);
                if ($r) {
                    $verboseLevel = $argv[$r[0]];
                    $this->verboseLevel = $verboseLevel;
                    $enabled = true;
                    break;
                }
            }

            $this->isEnabled = $enabled;

        }
        return $this->isEnabled;
    }

    /**
     * 检查当前环境是否输出 debug 信息
     * @return boolean
     */
    protected function isDebug()
    {
        // 单元测试 时不开启 debug 功能
        return $this->app->runningInConsole() && !$this->app->runningUnitTests() && $this->isEnabled();
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if (!$this->isDebug()) {
            return;
        }

        // php artisan vendor:publish --provider="Frostrain\Laravel\ConsoleDebug\ConsoleDebugServiceProvider"
        $this->publishes([
            __DIR__.'/../config/console_debug.php' => config_path('console_debug.php'),
        ]);

        $debugbar = $this->app['debugbar'];
        // 由于 debugbar 默认在命令模式下不启动(只是注册了而已, 不收集数据), 我们自己启动
        // 见 https://github.com/barryvdh/laravel-debugbar/blob/v2.3.2/src/ServiceProvider.php#L104
        $debugbar->enable();
        $debugbar->boot();

        // terminating() 函数用于注册程序终止前的回调操作
        $this->app->terminating(function () {
            // $params 的要求是这样的: ['-vv' => true];
            $params = [];

            $params[$this->verboseLevel] = true;
            $command = 'console:output-debug-info';
            $params['command'] = $command;

            $input = new \Symfony\Component\Console\Input\ArrayInput($params);
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
            // 为了 5.0-5.3 的兼容性, 需要使用 handle() 而不是 call()
            // 不过 5.0 还是不能显示颜色..
            Artisan::handle($input, $output);
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        if (!$this->isDebug()) {
            return;
        }

        $this->mergeConfigFrom(
            __DIR__.'/../config/console_debug.php', 'console_debug'
        );

        // 绑定实现
        $this->app->singleton('console-output-debug', function () {
            return new ConsoleOutputDebugCommand();
        });
        // 注册命令
        $this->commands('console-output-debug');
    }
}
