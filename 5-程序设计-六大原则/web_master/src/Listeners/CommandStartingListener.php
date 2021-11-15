<?php

namespace App\Listeners;

use Framework\Exception\NotSupportException;
use Illuminate\Config\Repository;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\InputOption;

class CommandStartingListener
{
    public function handle(CommandStarting $event)
    {
        $configFileName = realpath(__DIR__ . '/../../config/__safe_command.php');
        $config = new Repository(require $configFileName);

        if ($config->get('all_can_exec', false)) {
            // 所有允许执行
            return;
        }
        if (array_key_exists($event->command, $config->get('white_list', []))) {
            // 在白名单中的命令允许
            return;
        }
        if (!app()->isConsole()) {
            throw new NotSupportException('命令不支持执行，网页执行请添加白名单到配置中');
        }

        $tmpExecOption = $config->get('tmp_exec.allow_option');
        $tmpExecCountDownOption = $config->get('tmp_exec.count_down_option');
        if ($tmpExecOption && $event->input->hasParameterOption($tmpExecOption)) {
            // 临时执行

            // 动态增加 option
            $tmpExecCountDownOptionDefault = $config->get('tmp_exec.count_down_time', 10);
            app(Kernel::class)->getArtisan()->getDefinition()->addOptions([
                new InputOption($tmpExecOption, null, InputOption::VALUE_NONE, '临时执行'),
                new InputOption($tmpExecCountDownOption, null, InputOption::VALUE_REQUIRED, "临时执行倒计时，默认{$tmpExecCountDownOptionDefault}秒"),
            ]);
            // 倒计时
            $countDown = (int)$event->input->getParameterOption($tmpExecCountDownOption) ?: $tmpExecCountDownOptionDefault;
            if ($countDown > 0) {
                $event->output->writeln("安全考虑，{$countDown}秒后执行（可以通过`{$tmpExecCountDownOption}`设置时长）");
                while ($countDown > 0) {
                    sleep(1);
                    $event->output->write($countDown . '-');
                    $countDown--;
                    if ($countDown === 0)  {
                        $event->output->write('0');
                        $event->output->writeln('');
                    }
                }
            }
            $event->output->writeln('开始执行：' . $event->input);
            return;
        }

        $event->output->writeln("STOP Command `{$event->command}`, forbidden!!");
        $event->output->writeln("如果需要临时执行，请添加命令执行参数：{$tmpExecOption}");
        $event->output->writeln("如果需要长期执行，请添加命令白名单到：{$configFileName}");
        exit(0);
    }
}
