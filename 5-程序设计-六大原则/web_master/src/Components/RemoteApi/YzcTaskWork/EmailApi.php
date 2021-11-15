<?php

namespace App\Components\RemoteApi\YzcTaskWork;

use Illuminate\Support\Arr;

class EmailApi extends BaseYzcTaskWorkApi
{
    /**
     * 按照模版发送邮件
     * @param string|array $to
     * @param string $template
     * @param array $data
     * @param string $title
     */
    public function sendTemplate($to, string $template, array $data = [], string $title = ''): void
    {
        $this->api('/email/sendTemplate', [
            'body' => [
                'to' => Arr::wrap($to),
                'template' => $template,
                'data' => $data,
                'title' => $title,
            ],
        ]);
    }
}
