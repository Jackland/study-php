<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Mail\SendWithTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    use ApiResponse;

    // 发邮件
    public function sendTemplate(Request $request)
    {
        $request->validate([
            'to' => 'sometimes|required',
            'title' => 'nullable|string',
            'template' => 'required|string',
            'data' => 'nullable|array',
            'preview' => 'nullable|bool',
        ]);
        if (is_array($request->to)) {
            $request->validate(['to.*' => 'email|distinct']);
        } else {
            $request->validate(['to' => 'email']);
        }

        $data = $request->all();
        $mail = new SendWithTemplate($data['template'], $data['data'] ?? [], $data['title'] ?? null);
        if (isset($data['preview']) && $data['preview']) {
            // 预览，用于测试生成的邮件页面样式
            return $mail->render();
        }
        $mail->onQueue('email_queue');
        Mail::to(array_filter(Arr::wrap($data['to'])))->queue($mail);
        return $this->success();
    }
}
