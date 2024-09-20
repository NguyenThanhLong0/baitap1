<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// 1. Truy vấn lấy danh sách người dùng đã mua sản phẩm trong 30 ngày qua, cùng với thông tin sản phẩm và ngày mua

Route::get('select1', function () {
    $select = DB::table('users')
        ->join('oders', 'users.id', '=', 'oders.user_id')
        ->join('order_items', 'oders.id', '=', 'order_items.order_id')
        ->join('products', 'order_items.product_id', '=', 'products.id')
        ->select('users.name', 'products.product_name', 'oders.oder_date')
        ->where('oders.oder_date', '>=', DB::raw('NOW() - INTERVAL 30 DAY'))
        ->ddRawSql();
});

// 2. Truy vấn lấy tổng doanh thu theo từng tháng, chỉ tính những đơn hàng đã hoàn thành 
Route::get('select2', function () {
    $select2 =  DB::table('orders')
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->select(DB::raw("DATE_FORMAT(orders.order_date, '%Y-%m') AS order_month"), DB::raw('SUM(order_items.quantity * order_items.price) AS total_revenue'))
        ->where('orders.status', '=', 'completed')
        ->groupBy('order_month')
        ->orderByDesc('order_month')
        ->ddRawSql();
    //hàm DATE_FORMAT của MySQL để định dạng ngày trong cột order_date thành chuỗi theo định dạng YYYY-MM
});


// 3. Lấy danh sách khách hàng và số lượng đơn hàng mà họ đã thực hiện, chỉ tính những khách hàng có từ 3 đơn hàng trở lên
Route::get('select3', function () {
    $select3 = DB::table('users')
        ->join('orders', 'users.id', '=', 'orders.user_id')
        ->select('users.name', DB::raw('COUNT(orders.id) AS order_count'))
        ->groupBy('users.name')
        ->having('order_count', '>=', 3)
        ->ddRawSql();
});


// 4. Truy vấn các sản phẩm chưa từng được bán (sản phẩm không có trong bảng order_items)
Route::get('select4', function () {
    $select4 = DB::table('products')
        ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
        ->select('products.product_name')
        ->whereNull('order_items.product_id')
        ->ddRawSql();
});


// 5. Lấy danh sách các sản phẩm có doanh thu cao nhất cho mỗi loại sản phẩm
Route::get('select5', function () {
    $select5 = DB::table('products as p')
        ->join(DB::raw('(SELECT product_id, SUM(quantity * price) AS total FROM order_items GROUP BY product_id) as oi'), 'p.id', '=', 'oi.product_id')
        ->select('p.category_id', 'p.product_name', DB::raw('MAX(oi.total) AS max_revenue'))
        ->groupBy('p.category_id', 'p.product_name')
        ->orderByDesc('max_revenue')
        ->ddRawSql();
});


// 6. Truy vấn thông tin chi tiết về các đơn hàng có giá trị lớn hơn mức trung bình
Route::get('select6', function () {
    $select6 =
        DB::table('orders')
        ->join('users', 'orders.user_id', '=', 'users.id')
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->select('orders.id', 'users.name', 'orders.order_date', DB::raw('SUM(order_items.quantity * order_items.price) AS total_value'))
        ->groupBy('orders.id', 'users.name', 'orders.order_date')
        ->having('total_value', '>', DB::raw('(SELECT AVG(total) FROM (SELECT SUM(quantity * price) AS total FROM order_items GROUP BY order_id) as avg_order_value)'))
        ->ddRawSql();
});




// 7. Truy vấn danh sách nhân viên và khách hàng mà họ đã tương tác trong quá trình bán hàng, với các thông tin chi tiết về đơn hàng
Route::get('select7', function () {
    $select7 = DB::table('employees')
        ->join('order_assignees', 'employees.id', '=', 'order_assignees.employee_id')
        ->join('orders', 'order_assignees.order_id', '=', 'orders.id')
        ->join('users', 'orders.user_id', '=', 'users.id')
        ->select('employees.name AS employee_name', 'users.name AS customer_name', 'orders.order_date', 'orders.status')
        ->where('orders.status', 'completed')
        ->ddRawSql();
});


// 8. Truy vấn tìm các đơn hàng có sản phẩm bị trả lại nhiều hơn 2 lần
Route::get('select8', function () {
    $select8 =
        DB::table('orders')
        ->join('users', 'orders.user_id', '=', 'users.id')
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->join('products', 'order_items.product_id', '=', 'products.id')
        ->join('returns', 'order_items.id', '=', 'returns.order_item_id')
        ->select('orders.id', 'users.name', 'products.product_name', DB::raw('COUNT(returns.id) AS return_count'))
        ->groupBy('orders.id', 'users.name', 'products.product_name')
        ->having('return_count', '>', 2)
        ->ddRawSql();
    //->having để lọc kết quả sau khi đã thực hiện các phép nhóm
});


// 9. Truy vấn tìm tất cả các sản phẩm có doanh số cao nhất trong từng danh mục (category)
Route::get('select9', function () {
    $select9 = DB::table('products AS p')
        ->join('order_items AS oi', 'p.id', '=', 'oi.product_id')
        ->select('p.category_id', 'p.product_name', DB::raw('SUM(oi.quantity) AS total_sold'))
        ->groupBy('p.category_id', 'p.product_name')
        ->having('total_sold', '=', function ($query) {
            $query->select(DB::raw('MAX(sub.total_sold)'))
                ->from(DB::raw('(SELECT product_name, SUM(quantity) AS total_sold
                               FROM order_items
                               JOIN products ON order_items.product_id = products.id
                               WHERE products.category_id = p.category_id
                               GROUP BY product_name) sub'));
        });
    dd($select9->toSql());
});



// 10. Truy vấn lấy danh sách các khách hàng có chi tiêu tổng cộng cao nhất trong hệ thống, cùng với thông tin chi tiết về các đơn hàng của họ
Route::get('select10', function () {
    $select10 = DB::table('users')
        ->join('orders', 'users.id', '=', 'orders.user_id')
        ->join('order_items', 'orders.id', '=', 'order_items.order_id')
        ->select('users.name', DB::raw('SUM(order_items.quantity * order_items.price) AS total_spent'))
        ->groupBy('users.name')
        ->orderBy('total_spent', 'DESC')
        ->limit(10)
        ->ddRawSql();
});
