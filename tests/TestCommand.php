<?php namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'test';
    protected $signature = 'test';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'test';

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
        $d = app('debugbar');
        $this->line('hi');
        $d->info('info');
        $obj = new \StdClass();
        $d->error('error');
        $obj->foo = 'bar';
        $obj->abc = 'def';
        debug($obj);
        
        \App\User::first();
	}
}
