<?php

namespace App\Http\Controllers;

use App\codeList;
use App\myOpt;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Request;
class IndexController extends Controller
{

    public function index(){
        $data = codeList::whereIn('code',['SZ000001','SZ000002','SZ000004'])->get();
        $array['data'] = $data;
        $array['code'] = 0;
        return json_encode($array);
    }

    public function getCodeList(Request $request){
        $request = $request->all();
        $res =[];
        $codes = myOpt::get()->toArray();
        $endTime = date('H:i:s');
        $beginTime = strtotime($endTime)-60;//设定当前时间戳往前减少60秒也就是1分钟 为开始查询时间
        foreach($codes as $key=>$value){
            $url = 'http://quotes.money.163.com/service/zhubi_ajax.html?'.http_build_query(['symbol'=>$value['code'],'end'=>$endTime]);
            $result = json_decode(file_get_contents($url));
            if(!empty($result)){
                $array['content'] =[];
                $arr = json_decode( json_encode($result),true);
                foreach($arr['zhubi_list'] as $k=>$v){
                    if(strtotime($v['DATE_STR'])>$beginTime){//在1分钟内的要，超出一分钟内过滤掉
                        $array['content'][$k]['time'] =$v['DATE_STR'];
                        $array['content'][$k]['price'] =$v['PRICE'];
                        $array['content'][$k]['chajia'] =$v['PRICE_INC'];
                        $array['content'][$k]['number'] =$v['VOLUME_INC'];
                        $array['content'][$k]['money'] =$v['TURNOVER_INC'];
                        $array['content'][$k]['opt'] =$v['TRADE_TYPE_STR'];
                    }
                }
                $array['code'] =$value['code'];
                $res[$key] = $array;
            }

        }
        $res = $this->compute($res);
        $data['data'] = $res;
        $data['code'] = 0;
        return json_encode($data);
    }

    public function addMyOpt(Request $request){

        $code ='SZ000005';
        $array['data'] = [];
        $array['code'] = 600;
        return json_encode($array);

    }

    public function compute($array){

        $data =[];
        foreach($array as $key=>$value){
            if(!empty($value['content'])){
                $end = $value['content'][0]['time'];
                $uniqueNumber = array_unique(array_column($value['content'],'price'));
                $costNumber = 0;
                foreach($value['content'] as $k=>$v){
                    if($v['money']>900000){
                        $costNumber++;
                    }
                }
                $data[] = [
                    'time'=>$end,
                    'code'=>$value['code'],
//                'name'=>$value['name'],
                    'price'=>$value['content'][0]['price'],
                    'level'=>count($uniqueNumber)+$costNumber,
                    'change'=>count($uniqueNumber)
                ];
            }
        }
        if(count($data)>5){//只返回前五只个股
            $data = array_slice($data,0,5);
        }
        return $data;
    }

    public function addBuys(Request $request){
//        $data = codeList::whereIn('code',['SZ000001','SZ000002','SZ000004'])->get();
        $data = $request->all();
        $code = $data['code'];
        $price = $data['price'];
        $str = 'from pywinauto.application import Application
# 方式一：创建应用程序时可以，指定应用程序的合适的backend，start方法中指定启动的应用程序
# 注意backend的值不是win32就是uia ,软件路径要加斜杆,不然下面无法定位到打开窗口
# app = Application().start("C:\\同花顺软件\\同花顺\\xiadan.exe")
# 第二种方式是根据已经打开到进程号定位到窗口
app = Application().connect(process=13539)
# 第二步 定位窗口
win = app.window(best_match=u"网上股票交易系统5.0")
# 第三步 打印窗口控件信息 ，以定位控件
# 第四步 选择控件，模拟鼠标点击
win.type_keys("{F1}")
win.print_control_identifiers()
edit = app["网上股票交易系统5.0"]["证券代码Edit"]
code = '."$code".'
edit.type_keys(code)
edit = app["网上股票交易系统5.0"]["买入价格Edit"]
# 先置空
edit.type_keys("")
# 再指定价格
price = '."$price".'
edit.type_keys(price)
win["Button38"].click()';
        Storage::put('buy.py',$str);
        $array['data'] = [];
        $array['code'] = 0;
        return json_encode($array);
    }

    public function deleteOpt(Request $request){
        $array = $request->all();

        $array['userId'] = 1;
        $data = myOpt::where('userId',$array['userId'])->where('code',$array['code'])->delete();
        $array['data'] = $array;
        $array['code'] = 0;
        return json_encode($array);
    }

    public function batchCurl($urls){
        $res = array();
        $conn = array();
        // 创建批处理cURL句柄
        $mh = curl_multi_init();
        foreach ($urls as $i => $url) {
            // 创建一对cURL资源
            $conn[$i] = curl_init();
            // 设置URL和相应的选项
            curl_setopt($conn[$i], CURLOPT_URL, $url);
            curl_setopt($conn[$i], CURLOPT_HEADER, 0);
            curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($conn[$i], CURLOPT_TIMEOUT, 60);
            //302跳转
            curl_setopt($conn[$i], CURLOPT_FOLLOWLOCATION, 1);
            // 增加句柄
            curl_multi_add_handle($mh, $conn[$i]);
        }
        $active = null;
        //防卡死写法：执行批处理句柄
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        foreach ($urls as $i => $url) {
            //获取当前解析的cURL的相关传输信息
            $info = curl_multi_info_read($mh);
            //获取请求头信息
            $heards = curl_getinfo($conn[$i]);
            //获取输出的文本流
            $res[$i] = curl_multi_getcontent($conn[$i]);
            // 移除curl批处理句柄资源中的某个句柄资源
            curl_multi_remove_handle($mh, $conn[$i]);
            //关闭cURL会话
            curl_close($conn[$i]);
        }
        //关闭全部句柄
        curl_multi_close($mh);
        //var_dump($res);
        return $res;
    }


    public function getCurl($url)
    {
        $header = array(
            'Accept: application/json',
            'Access-Control-Allow-Origin: *',
            'Access-Control-Allow-Method:POST,GET'//允许访问的方式
        );
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        // 超时设置,以秒为单位
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);

        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        //执行命令
        $data = curl_exec($curl);

        // 显示错误信息
        if (curl_error($curl)) {
            print "Error: " . curl_error($curl);
        } else {
            // 打印返回的内容
            curl_close($curl);
        }
        return $data;
    }

    public function postCurl($url){
        // $url 是请求的链接
// $postdata 是传输的数据，数组格式
        $header = array(
            'Accept: application/json',
        );

        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        // 超时设置
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);

        // 超时设置，以毫秒为单位
        // curl_setopt($curl, CURLOPT_TIMEOUT_MS, 500);

        // 设置请求头
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE );

        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        //执行命令
        $data = curl_exec($curl);

        // 显示错误信息
        if (curl_error($curl)) {
            print "Error: " . curl_error($curl);
        } else {
            // 打印返回的内容
            var_dump($data);
            curl_close($curl);
        }
    }

}
