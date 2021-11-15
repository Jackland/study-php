<?php

namespace App\Commands\OssTransfer;


use App\Models\Information\InformationDescription;
use Framework\Console\Command;

class InformationDataCommand extends Command
{

    protected $name = 'OssTransfer:information-data';

    protected $description = '将oc_information_description(请务必备份oc_information_description后在执行) 富文本替换src 为oss';

    protected $help = '';

    protected $openUploadOss = false;//是否开启上传到阿里云

    /**
     * description:清洗数据表的数据 替换成阿里的oss地址
     * author: fuyunnan
     * @param
     * @return string
     * @throws
     * Date: 2021/6/22
     */
    public function handle()
    {
        $this->infoDesc();
        echo 'run shell ok time---' . date("Y-m-d H:i:s") . PHP_EOL;
        return true;
    }


    private function infoDesc()
    {
        $data = InformationDescription::query()
            ->get()
            ->toArray();
        if ($data) {
            foreach ($data as $item) {
                if ($item['description']) {
                    $str = html_entity_decode($item['description'], ENT_QUOTES, 'UTF-8');
                    $content = $this->pregReplaceImg2($str, get_env('ALI_OSS_DOMAIN'));
                    InformationDescription::query()->where('information_id', $item['information_id'])
                        ->update(['description' => htmlentities($content)]);
                    echo 'run information_id--'.$item['information_id'].PHP_EOL;
                }
            }
            return true;
        }
        return false;
    }

    //oc_information_description
    private function pregReplaceImg2($content, $prefix)
    {
        $contentAlter = preg_replace_callback('/(<[img|IMG].*?src=[\'\"])([\s\S]*?)([\'\"])[\s\S]*?/i', function ($match) use ($prefix) {

            $exits = strstr($match[2], '/image/catalog');
            if ($exits) {//如果是catalog
                //获取当前文件的相对路径
                $relativePath = strstr($match[2], '/image/');
                return $match[1] . $prefix . $relativePath . $match[3];
            }else{
                return $match[0];
            }

        }, $content);
        return $contentAlter;
    }
}
