<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BuyerController extends Controller
{
    //region airwallex
    public function airwallex(Request $request)
    {
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $buyers = DB::table('oc_buyer_airwallex_bind_apply as oba')
                ->leftJoin('oc_buyer as ob', 'oba.buyer_id', '=', 'ob.buyer_id')
                ->where('airwallex_email', 'like', "%{$keyword}%")
                ->get(['oba.buyer_id', 'oba.airwallex_email', 'ob.airwallex_id']);
        }
        return view('admin.buyer.airwallex', compact('buyers'));
    }

    public function airwallexSave(Request $request)
    {
        $this->validate($request, [
            'buyer_id' => 'required|array',
            'buyer_id.*' => Rule::exists('oc_buyer', 'buyer_id'),
            'airwallex_id' => 'required|array'
        ]);
        $buyerId = $request->input('buyer_id');
        $airwallexId = $request->input('airwallex_id');
        Log::useFiles(storage_path('logs/admin/buyer-airwallex.log'));
        foreach ($airwallexId as $key => $airId) {
            if (!empty($airId)) {
                $buyer = DB::table('oc_buyer')->where('buyer_id', $buyerId[$key])->first();
                $ordAirwallexId = $buyer->airwallex_id;
                DB::table('oc_buyer')->where('buyer_id', $buyerId[$key])->update([
                    'airwallex_id' => $airId
                ]);
                Log::info(Auth()->user()->name . '修改buyer(' . $buyerId[$key] . ') airwallex id:' . $ordAirwallexId . '=>' . $airId);
            }
        }
        return redirect()->route('buyer.airwallex', $request->all('keyword'));
    }
    //endregion

    //region 用户登入限制
    public function loginLimit(Request $request)
    {
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $buyers = DB::table('oc_customer_login')
                ->where('email', 'like', "%{$keyword}%")
                ->get();
        }
        return view('admin.buyer.login_limit', compact('buyers'));
    }

    public function loginLimitSave(Request $request)
    {
        $this->validate($request, [
            'customer_login_id' => 'required|array',
            'customer_login_id.*' => Rule::exists('oc_customer_login', 'customer_login_id'),
            'totals' => 'required|array'
        ]);
        $customerLoginId = $request->input('customer_login_id');
        $totals = $request->input('totals');
        Log::useFiles(storage_path('logs/admin/buyer-login-total.log'));
        foreach ($totals as $key => $total) {
            if (!empty($total)) {
                $oldTotal = DB::table('oc_customer_login')->where('customer_login_id', $customerLoginId[$key])->first();
                DB::table('oc_customer_login')->where('customer_login_id', $customerLoginId[$key])->update([
                    'total' => $total
                ]);
                Log::info(Auth()->user()->name . '修改oc_customer_login(' . $customerLoginId[$key] . ') total:' . $oldTotal->total . '=>' . $total);
            }
        }
        return redirect()->route('buyer.loginLimit', $request->all('keyword'));
    }
    //endregion
}
