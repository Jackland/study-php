<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\LoggerHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    // 手机号跳过验证
    public function telephoneIgnoreVerify(Request $request)
    {
        $customers = [];
        $sql = null;
        if ($request->input('keyword') || $request->old('keyword')) {
            $keyword = $request->input('keyword');
            if (!$keyword) {
                $keyword = $request->old('keyword');
            }
            $customers = DB::table('oc_customer')
                ->whereIn('user_number', explode(',', $keyword))
                ->get();
            $in = $customers->pluck('user_number')
                ->where('telephone_verified_at', 0)
                ->implode('\', \'');
            $sql = "UPDATE `oc_customer` SET `telephone_verified_at` = 1 WHERE `user_number` IN ('{$in}');";
        }

        if ($request->isMethod('POST')) {
            $execSql = $request->post('sql');
            if ($execSql != $sql) {
                return redirect()->refresh()
                    ->withInput($request->only(['keyword']))
                    ->with(['error' => "sql 变化，重新检查"]);
            }
            $count = DB::update($sql);
            $this->log(['sql' => $sql, 'updatedCount' => $count]);
            return redirect()->refresh()
                ->withInput($request->only(['keyword']))
                ->with(['success' => "成功修改数据：{$count}条"]);
        }

        return view('admin.customer.telephone_ignore_verify', compact('customers', 'sql'));
    }

    private function log(array $message, $type = 'info')
    {
        $user = Auth::user();
        $message['user'] = ['id' => $user->id, 'name' => $user->name];
        LoggerHelper::wrapWithDaily('admin/customer.log', function () use ($message, $type) {
            Log::$type($message);
        });
    }
}