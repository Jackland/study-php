<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ModifyPic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statistic:pic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '临时php 脚本 修改图片';

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
        ini_set('memory_limit', '10240M');
        $dir = "C:\Users\Administrator\Desktop\ACME Picture";
        $dir_new = "C:\Users\Administrator\Desktop\ACME Picture New";
        $file_list = scandir($dir);
        $flag=0;
        $rtn_txt='';
        foreach ($file_list as $k => $v) {
            if (is_file($dir . '/' . $v)) {
                $file_name = pathinfo($dir . '/' . $v)['filename'];
                $rtn_txt.="'".$file_name."',";
                $file = $dir . '/' . $v;
                $new_file_dir=$dir_new.'/'.$file_name;
                if(!is_dir($new_file_dir)){
                    mkdir(iconv("UTF-8", "GBK", $new_file_dir),0777,true);
                }
                $new_file_path=$new_file_dir."/".$v;
                echo $v;
                if(copy($file,$new_file_path)){
                    echo '    success';
                }else{
                    echo '    error';
                }
                echo PHP_EOL;
                $flag++;
            }
        }
        file_put_contents($new_file_dir."/".'rtn.txt',$rtn_txt);
        echo 'end 文件数:'.$flag;
    }

}
