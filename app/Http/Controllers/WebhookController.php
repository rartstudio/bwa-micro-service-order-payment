<?php

namespace App\Http\Controllers;

use App\Order;
use App\PaymentLog;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function midtransHandler(Request $request)
    {
        $data = $request->all();
        
        $signatureKey= $data['signature_key'];
        
        $orderId = $data['order_id'];
        $statusCode = $data['status_code'];
        $grossAmount = $data['gross_amount'];
        $serverKey= env('MIDTRANS_SERVER_KEY');
        
        $mySignatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
        
        // dd($mySignatureKey);

        $transactionStatus = $data['transaction_status'];
        $type = $data['payment_type'];
        $fraudStatus = $data['fraud_status'];
        
        //if our signature doesnt match with midtrans signature
        if($signatureKey !== $mySignatureKey){
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature'
            ],400);
        }
        
        //order_id = 9-asdasdasd
        $realOrderId = explode('-',$orderId);
        $order = Order::find($realOrderId[0]);
        
        //if dont find order
        if(!$order){
            return response()->json([
                'status' => 'error',
                'message' => 'order id not found'
            ],404);
        }
        
        //if order status already success reject it to prevent error
        if($order->status === 'success'){
            return response()->json([
                'status' => 'error',
                'message' => 'operation not permitted'
            ],405);
        }
        
        if($transactionStatus == 'capture'){
            if($fraudStatus == 'challenge'){
                //todo set transaction status on your database to 'challenge'
                //and response with 200 ok
                $order->status = 'challenge';
            }
            else if ($fraudStatus == 'accept'){
                //todo set transaction status on your database to 'accept'
                //and response with 200 ok
                $order->status = 'success';
            }
        }
        else if ($transactionStatus == 'settlement'){
            $order->status = 'success';
        }
        else if($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire'){
            $order->status = 'failure';
        }
        else if ($transactionStatus == 'pending'){
            $order->status = 'pending';
        }
            
        $logData = [
            'status' => $transactionStatus,
            'raw_response' => json_encode($data),
            'order_id' => $realOrderId[0],
            'payment_type' => $type
            
        ];
        
        //create log from webhook
        PaymentLog::create($logData);
        
        //save all change from order data
        $order->save();
        
        if($order->status === 'success'){
            createPremiumAccess([
                'user_id' => $order->user_id,
                'course_id' => $order->course_id
            ]);
        }
        
        return response()->json('Ok');
    }
}
