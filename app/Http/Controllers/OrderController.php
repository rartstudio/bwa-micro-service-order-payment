<?php

namespace App\Http\Controllers;

use App\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->input('user_id');
        
        //get all data orders
        $orders = Order::query();
        
        //when have a query 
        $orders->when($userId, function($query) use ($userId){
            return $query->where('user_id','=',$userId);
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $orders->get()
        ]);
    }

    public function create(Request $request)
    {
        //get user and course data
        $user = $request->input('user');
        $course = $request->input('course');

        $order = Order::create([
            'user_id' => $user['id'],
            'course_id' => $course['id']
        ]);
        
        //use string random to prevent error on order_id (same id order)
        $transaction_details = [
            'order_id' => $order->id.'-'.Str::random(5),
            'gross_amount' => $course['price']
        ];
        
        $itemDetails = [
            [
                'id' => $course['id'],
                'price' => $course['price'],
                'quantity' => 1,
                'name' => $course['name'],
                'brand' => 'BWA MICRO',
                'category' => 'Online Course'
            ]
        ];
        
        $customerDetails = [
            'first_name' => $user['name'],
            'email' => $user['email']
        ];
        
        //request snap url to midtrans
        $midtransParams = [
            'transaction_details' => $transaction_details,
            'item_details' => $itemDetails,
            'customer_details' => $customerDetails
        ];
        
        //get snap url midtrans 
        $midtransSnapUrl = $this->getMidtransSnapUrl($midtransParams);
        
        //update order data
        $order->snap_url = $midtransSnapUrl;
        $order->metadata = [
            'course_id' => $course['id'],
            'course_price' => $course['price'],
            'course_name' => $course['name'],
            'course_thumbnail' => $course['thumbnail'],
            'course_level' => $course['level']
        ];

        // //save all data
        $order->save();
        
        return response()->json([
            'status' => 'success',
            'data' => $order
        ]);
        
        //result url midtrans
        //to try copy it and paste it to browser
        // return $midtransSnapUrl;
        
        //result order create
        // return response()->json($order);
    }

    private function getMidtransSnapUrl($params)
    {
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        
        //(bool) casting env value to boolean cause default is string
        \Midtrans\Config::$isProduction = (bool) env('MIDTRANS_PRODUCTION');
        \Midtrans\Config::$is3ds = (bool) env('MIDTRANS_3DS');
        
        $snapUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;

        return $snapUrl;
    }
}
