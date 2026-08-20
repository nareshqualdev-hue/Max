<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StaticpageController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\PopupController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\ShoppingcartController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\MainCategoryController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\PaypalController;
use App\Http\Controllers\AfterpayController;
use App\Http\Controllers\AmazonpayController;
use App\Http\Controllers\FrontCacheController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\FbUserDeleteController;
use App\Http\Controllers\LandingpageController;
use App\Http\Controllers\RewardProgramController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\PhoneOrderController;
use App\Http\Controllers\TempUserController;
//POS Start
use App\Http\Controllers\POSController;
use App\Http\Middleware\POSMaintainMode;
use App\Http\Controllers\POSAdmController;
use App\Http\Controllers\StripeTerminalController;
use App\Http\Controllers\POSConnecterController;


use App\Http\Controllers\Checkout\CheckoutController;
use App\Http\Controllers\Checkout\CheckoutCartController;
use App\Http\Controllers\Checkout\CheckoutAddressController;
use App\Http\Controllers\Checkout\CheckoutShippingController;
use App\Http\Controllers\Checkout\CheckoutDiscountController;
use App\Http\Controllers\Checkout\CheckoutTotalsController;

//POS End
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
/*
|--------------------------------------------------------------------------
| One Page Checkout
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| One Page Checkout
|--------------------------------------------------------------------------
*/

Route::prefix('checkoutnew')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    */

    Route::get('/', [
        CheckoutController::class,
        'index'
    ])->name('checkoutnew.index');


    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    */

    Route::prefix('cart')->group(function () {

        Route::post('/add', [
            CheckoutCartController::class,
            'add'
        ])->name('checkoutnew.cart.add');

        Route::post('/update', [
            CheckoutCartController::class,
            'update'
        ])->name('checkoutnew.cart.update');

        Route::post('/remove', [
            CheckoutCartController::class,
            'remove'
        ])->name('checkoutnew.cart.remove');

        Route::post('/clear', [
            CheckoutCartController::class,
            'clear'
        ])->name('checkoutnew.cart.clear');

        Route::post('/summary', [
            CheckoutCartController::class,
            'summary'
        ])->name('checkoutnew.cart.summary');
    });


    /*
    |--------------------------------------------------------------------------
    | Totals
    |--------------------------------------------------------------------------
    */

    Route::post('/totals', [
        CheckoutTotalsController::class,
        'calculate'
    ])->name('checkoutnew.totals');


    /*
    |--------------------------------------------------------------------------
    | Address
    |--------------------------------------------------------------------------
    */

    Route::post('/address', [
        CheckoutAddressController::class,
        'update'
    ])->name('checkoutnew.address.update');


    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    */

    Route::post('/shipping-methods', [
        CheckoutShippingController::class,
        'getAvailableShippingMethods'
    ])->name('checkoutnew.shipping.methods');

    Route::post('/shipping-method', [
        CheckoutShippingController::class,
        'setShippingMethod'
    ])->name('checkoutnew.shipping.method');

    Route::post('/shipping-insurance', [
        CheckoutShippingController::class,
        'setShippingInsurance'
    ])->name('checkoutnew.shipping.insurance');

    Route::post('/shipping-signature', [
        CheckoutShippingController::class,
        'setShippingSignature'
    ])->name('checkoutnew.shipping.signature');

    Route::post('/gift-certificate', [
        CheckoutShippingController::class,
        'setGiftCertificate'
    ])->name('checkoutnew.gift-certificate');

    Route::post('/gift-wrapping', [
        CheckoutShippingController::class,
        'setGiftWrapping'
    ])->name('checkoutnew.gift-wrapping');


    /*
    |--------------------------------------------------------------------------
    | Discount
    |--------------------------------------------------------------------------
    */

    Route::post('/discount/apply', [
        CheckoutDiscountController::class,
        'apply'
    ])->name('checkoutnew.discount.apply');

    Route::post('/discount/remove', [
        CheckoutDiscountController::class,
        'removeCoupon'
    ])->name('checkoutnew.discount.remove');

    Route::post('/discount/remove-yotpo-reward', [
        CheckoutDiscountController::class,
        'removeYotpoReward'
    ])->name('checkoutnew.discount.remove-yotpo-reward');


    /*
    |--------------------------------------------------------------------------
    | Payment
    |--------------------------------------------------------------------------
    */

    Route::post('/payment/availability', [
        CheckoutPaymentController::class,
        'availability'
    ])->name('checkoutnew.payment.availability');

});;
Route::get('/clear-cache', function() {
   $exitCode = Artisan::call('cache:clear');
});

Route::get('/clear-view', function() {
   $exitCode = Artisan::call('view:clear');
});

/** Homepage Module Start **/
Route::get('/', [HomeController::class,'index'])->name('home');
Route::get('/homepagebanners', [HomeController::class,'HomePageBanners']);
/** Homepage Module End **/

//test controller start
Route::get('/test.html', [TestController::class,'index']);
//test controller end

/** Customer Module Start **/
Route::get('/register.html', [CustomerController::class,'Register'])->name('retailer-registration');
Route::post('/register.html', [CustomerController::class,'Register']);
Route::get('/login.html', [CustomerController::class,'Login'])->name('login');
Route::post('/login.html', [CustomerController::class,'Login']);
Route::get('/login/{id}.html', [CustomerController::class,'Login'])->name('login');
Route::post('/login/{id}.html', [CustomerController::class,'Login']);
Route::get('/logout.html', [CustomerController::class,'Logout']);
Route::post('/autologin', [CustomerController::class,'Login']);
Route::post('/forgot-password.html', [CustomerController::class,'ForgotPassword'])->name('forgot-password');
Route::get('/forgot-password.html', [CustomerController::class,'ForgotPassword'])->name('forgot-password');
Route::get('/wholesaleregister.html', [CustomerController::class,'WholeSaleRegister'])->name('wholesaler-registration');
Route::post('/wholesaleregister.html', [CustomerController::class,'WholeSaleRegister'])->name('wholesaler-registration');
Route::get('/wholesale-register.html', [CustomerController::class,'WholeSaleRegister'])->name('wholesaler-registration');
Route::post('/wholesale-register.html', [CustomerController::class,'WholeSaleRegister'])->name('wholesaler-registration');

Route::get('/sales-tax-exemption.html', [CustomerController::class,'SalesTaxExemption'])->name('sales-tax-exemption');
Route::post('/sales-tax-exemption.html', [CustomerController::class,'SalesTaxExemption'])->name('sales-tax-exemption');

Route::get('/reset-password/{code}',[StaticpageController::class,'ResetPassword']);
Route::post('/reset-password/{code}',[StaticpageController::class,'ResetPassword']);
Route::get('/order-detail-pdfs/{id}-{cid}.html', [CustomerController::class,'OrderDetailPdf']);//new added 19-09-2023
Route::get('/order-detail-pdf/{id}.html', [CustomerController::class,'OrderDetailPdf']);
/* Below routes are accessible only if the user is logged in, otherwise it will be redirected to the login page. */
Route::middleware(['auth'])->group(function () {
	Route::get('/myaccount.html', [CustomerController::class,'Myaccount'])->name('myaccount');
	Route::get('/sendmail', [CustomerController::class,'SendMails']);
	Route::get('/editprofile.html', [CustomerController::class,'EditProfile']);
	Route::post('/editprofile.html', [CustomerController::class,'EditProfile']);
	Route::get('/changepassword.html', [CustomerController::class,'ChangePassword']);
	Route::post('/changepassword.html', [CustomerController::class,'ChangePassword']);
	Route::get('/addressbook.html', [CustomerController::class,'AddressBook']);
	Route::post('/addressbook.html', [CustomerController::class,'AddressBook']);
	Route::get('/addressbook/add.html',[CustomerController::class,'AddAddressbook']);
	Route::post('/addressbook/add.html',[CustomerController::class,'AddAddressbook']);
	Route::get('/addressbook/edit/{id}.html',[CustomerController::class,'EditAddressbook']);
	Route::post('/addressbook/edit/{id}.html',[CustomerController::class,'EditAddressbook']);
	Route::delete('/addressbook/remove.html',[CustomerController::class,'RemoveAddressbook'])->name('remove-addressbook');
	//Route::get('/referral-customer.html', [CustomerController::class,'ReferralCustomer']);
	Route::get('/myrewardpoint.html', [CustomerController::class,'MyRewardPoint']);
	Route::get('/cancel-orders.html', [CustomerController::class,'OrderCancel'])->name('cancel_orders');
	Route::get('/order-history.html', [CustomerController::class,'OrderHistory'])->name('order_history');
	Route::post('/order-history.html', [CustomerController::class,'OrderHistory']);
	Route::get('/order-detail/{id}.html', [CustomerController::class,'OrderDetail']);
	//Route::get('/order-detail-pdf/{id}.html', [CustomerController::class,'OrderDetailPdf']);
	Route::get('/wish-category.html', [CustomerController::class,'wishCategory']);
	Route::delete('/wish-category.html', [CustomerController::class,'wishCategory']);
	Route::get('/wish-category/{category_id}.html', [CustomerController::class,'WishCategoryEdit']);
	Route::post('/wish-category/{category_id}.html', [CustomerController::class,'WishCategoryEdit']);
	Route::get('/wish-product/{category_id}.html', [CustomerController::class,'WishProduct']);
	Route::delete('/wish-product/{category_id}.html', [CustomerController::class,'WishProduct']);
	Route::get('/order-item-return.html', [CustomerController::class,'OrderItemReturn']);
	Route::post('/order-item-return.html', [CustomerController::class,'OrderItemReturn']);
	Route::get('/credit-summary.html', [CustomerController::class,'CreditSummary']);
	Route::get('/dropshipper-fund-summary.html', [CustomerController::class,'DropshipperFundSummary'])->name('dropshipper-fund-summary');
	Route::get('/help-videos.html', [CustomerController::class,'HelpVideos']);
	Route::get('/orderftp.html', [CustomerController::class,'EditFtp']);
	Route::post('/orderftp.html', [CustomerController::class,'EditFtp']);
	Route::get('/productftp.html', [CustomerController::class,'ProductFtp']);
	Route::post('/productftp.html', [CustomerController::class,'ProductFtp']);
	Route::get('/import-order.html', [CustomerController::class,'ImportOrder']);
	// Route::post('/import-order.html', [CustomerController::class,'ImportOrder']);
	Route::post('/import-order-csv.html', [ImportExportController::class,'ImportOrderCSV']);
	//Route::get('/imported-order-list.html', [CustomerController::class,'ImportedOrderList']);
	Route::get('/imported-order-list.html', [CustomerController::class,'ImportedOrderList'])->name('DropshipOrder');
	Route::get('/imported-order-detail/{id}.html', [CustomerController::class,'ImportedOrderDetail']);
	Route::post('/imported-order-detail/{id}.html', [CustomerController::class,'ImportedOrderDetail']);

	Route::delete('/delete-imported-order-list.html', [CustomerController::class,'DeleteImportedOrderList']);
	Route::get('/export-fund-history.html', [ImportExportController::class,'ExportFundHistory']);

	Route::get('/re-order-detail/{id}.html', [CustomerController::class,'ReOrderDetail']);
	Route::get('/exportorders.html', [CustomerController::class,'ExportOrders']);
	Route::post('/export-order-csv.html', [ImportExportController::class,'ExportOrderCSV']);
    Route::get('/return-orders.html', [CustomerController::class,'OrderReturnHistory'])->name('return_orders');
	Route::post('/return-orders.html', [CustomerController::class,'OrderReturnHistory']);
	Route::get('/tracking/{id}', [CustomerController::class,'OrderTracking']);

});

Route::get('/order-detail-print/{id}.html', [CustomerController::class,'OrderDetailPrint']);
Route::get('/special-product-list/key-{search_keyword}/{all_items?}', [GeneralController::class,'WholeSaleProducts']);
Route::get('/special-product-list/{all_items?}', [GeneralController::class,'WholeSaleProducts']);
Route::post('/specialproductlistmore', [GeneralController::class,'WholeSaleProducts']);
Route::post('/searchwholesaleproducts', [GeneralController::class,'SearchWholeSaleProducts']);
Route::get('/specialwholesaleproductpricelist', [GeneralController::class,'SpecialWholeSaleProductList']);
Route::get('/download_specialwholesaleproductlist', [GeneralController::class,'SpecialWholeSaleProductList_Download']);
Route::post('/wholesalealertpopup', [GeneralController::class,'GetProductDetailAlert']);
Route::post('/changecurrency', [GeneralController::class,'ChangeCurrency']);

Route::get('/payment/{id}/{method?}', [GeneralController::class,'PhoneorderPayReceipt'])->name('phoneorder_payment_receipt');
Route::get('/invoice/{invoice_no}', [GeneralController::class,'PhoneorderDownloadInvoice']);
Route::get('/stripe/phoneorder', [StripeController::class,'PhoneOrder']);
Route::get('/payment_process/{id}/{success}', [GeneralController::class,'PhoneorderPayReceiptResponse']);
Route::get('/downloadpricelist.html', [GeneralController::class,'DownloadPPL']);
Route::post('/phone_order_discount', [PhoneOrderController::class,'CalculatePhoneOrderDiscount']);
Route::get('/zendesk.html',[StaticpageController::class,'ZendPage']);

/** Customer Module End **/

Route::get('/referral-customer.html', function(){
    return redirect('/myrewardpoint.html', 301);
});
/** Staticpage Module Start **/

Route::get('/fragrance/cid/1', function () {

return redirect('/fragrance', 301);

});

Route::get('/idiots', function () {
return redirect('/house-of-dastan/smid-1031', 301);
});

Route::get('/flagrant', function () {
return redirect('/house-of-dastan/smid-1031', 301);
});

Route::get('/free-sample.html', function () {
return redirect('/site-page/free-sample.html', 301);
});

Route::get('/site-page/reward_point_program.html', function () {
return redirect('/reward-point-program.html', 301);
});

Route::get('/store-credit.html', function () {
return redirect('/site-page/store-credit.html', 301);
});

Route::get('/site-page/store_credit.html', function () {
return redirect('/site-page/store-credit.html', 301);
});

Route::get('/site-page/privacy_policy.html', function () {
	return redirect('/privacy-policy.html', 301);
});

Route::get('/site-page/terms_and_conditions.html', function () {
	return redirect('/terms-and-conditions.html', 301);
});

Route::get('/site-page/site_map.html', function () {
	return redirect('/site-map.html', 301);
});

Route::get('/site-page/shipping_policy.html', function () {
	return redirect('/shipping-policy.html', 301);
});

Route::get('/site-page/security_policy.html', function () {
	return redirect('/security-policy.html', 301);
});
Route::get('/site-page/return_exchange_policy.html', function () {
	return redirect('/return-exchange-policy.html', 301);
});
Route::get('/site-page/FAQS.html', function () {
	return redirect('/faq.html', 301);
});

Route::get('/site-page/shipping_information.html', function () {
	return redirect('/shipping-policy.html', 301);
});

Route::get('/site-page/contactus.html', function () {
	return redirect('/contact-us.html', 301);
});

Route::get('/site-page/coupons_promotional.html', function () {
	return redirect('/coupons-promotional.html', 301);
});

Route::get('/dontseereq.html', function () {
	return redirect('/dont-see-request.html', 301);
});

Route::get('/site-page/coupon-code.html', function () {
	return redirect('/coupons-promotional.html', 301);
});
Route::get('/site-page/LimeSpot.html', function () {
	return redirect('/', 301);
});
Route::get('/site-page/celebrity_perfume.html', function () {
	return redirect('/', 301);
});
Route::get('/site-page/wholesaler_shipping_policy.html', function () {
	return redirect('/site-page/wholesaler-shipping-policy.html', 301);
});
Route::get('/site-page/Redemption_policy.html', function () {
	return redirect('/site-page/redemption-policy.html', 301);
});

Route::get('/site-page/returns_policy.html', function () {
	return redirect('/site-page/returns-policy.html', 301);
});
Route::get('/site-page/authenticity_promise.html', function () {
	return redirect('/site-page/authenticity-promise.html', 301);
});

Route::get('/site-page/coupon_code.html', function () {
	return redirect('/coupons-promotional.html', 301);
});
Route::get('/site-page/FAQS_old.html', function () {
	return redirect('/faq.html', 301);
});
Route::get('/site-page/about_us.html', function () {
	return redirect('/about-us.html', 301);
});

Route::get('/order-item-return.html', function () {
	return redirect('/return-orders.html', 301);
});

Cache::forget('StaticPagesCache');
if(!Cache::has('StaticPagesCache'))
{
	GetPages();
}

$StaticPage = Cache::get('StaticPagesCache');
if(is_array($StaticPage) && count($StaticPage) > 0)
{
    for($i=0;$i<count($StaticPage);$i++)
    {
        Route::get($StaticPage[$i]['link'],[StaticpageController::class,'show'])->name($StaticPage[$i]['slug']);
    }
}
Route::get('/check-mail.html', [StaticpageController::class,'CheckMail']);
Route::get('/shipping_service.html', function () {
	return redirect('/dropshipping-faqs.html', 301);
});
Route::get('/site-page/shipping_service.html', function () {
	return redirect('/dropshipping-faqs.html', 301);
});
Route::get('/site-page/shipping-service.html', function () {
	return redirect('/dropshipping-faqs.html', 301);
});
/*Route::get('/site-page/affiliate-program.html',[StaticpageController::class,'show'])->name('affiliate-program');
Route::get('/reward-point-program.html', [StaticpageController::class,'show'])->name('reward_point_program');
Route::get('/about-us.html', [StaticpageController::class,'show'])->name('about_us');
Route::get('/faq.html', [StaticpageController::class,'show'])->name('faq');
Route::get('/security-policy.html', [StaticpageController::class,'show'])->name('security_policy');
Route::get('/privacy-policy.html', [StaticpageController::class,'show'])->name('privacy_policy');
Route::get('/shipping-policy.html', [StaticpageController::class,'show'])->name('shipping_policy');
Route::get('/terms-and-conditions.html', [StaticpageController::class,'show'])->name('terms_and_conditions');
Route::get('/return-exchange-policy.html', [StaticpageController::class,'show'])->name('return_exchange_policy');
Route::get('/site-page/authenticity_promise.html', [StaticpageController::class,'show'])->name('authenticity_promise');
Route::get('/site-page/shipping_service.html', [StaticpageController::class,'show'])->name('authenticity_promise');
Route::get('/coupons-promotional.html', [StaticpageController::class,'show'])->name('coupons_promotional');
*/
Route::get('/brand-name-perfumes.html', [StaticpageController::class,'BrandPerfume'])->name('brand-name-perfumes');
Route::get('/gift-certificate.html', [StaticpageController::class,'GiftCertificate']);
Route::post('/gift-certificate.html', [StaticpageController::class,'GiftCertificate']);

//Temporary Purpose
Route::get('/gift-certificate-new.html', [StaticpageController::class,'GiftCertificateNew']);
Route::post('/gift-certificate-new.html', [StaticpageController::class,'GiftCertificateNew']);

Route::get('/contact-us.html', [StaticpageController::class,'ContactUs']);
Route::post('/contact-us.html', [StaticpageController::class,'ContactUs']);
Route::post('/edesk-contact-us', [StaticpageController::class,'eDeskContactUs']);

Route::get('/edesk-chat', [StaticpageController::class,'eDeskChat']);

Route::get('/contact-us-new.html', [StaticpageController::class,'ContactUsNew']);
Route::post('/contact-us-new.html', [StaticpageController::class,'ContactUsNew']);

Route::post('/get_brands', [StaticpageController::class,'GetBrands']);
Route::get('/track-order.html', [StaticpageController::class,'TrackOrder']);
Route::post('/track-order.html', [StaticpageController::class,'TrackOrder']);
Route::get('/dont-see-request.html', [StaticpageController::class,'DontSeeRequest']);
Route::post('/dont-see-request.html', [StaticpageController::class,'DontSeeRequest']);

Route::get('/faq.html', [StaticpageController::class,'FAQ']);
//Route::get('/shipping_service.html', [StaticpageController::class,'SHIPPING_SERVICE']);
Route::get('/dropshipping-faqs.html', [StaticpageController::class,'SHIPPING_SERVICE']);
Route::get('/reviews', [StaticpageController::class,'YotpoReview']);
Route::get('/temp-reviews', [StaticpageController::class,'YotpoTempReview']);

/** Staticpage Module End **/

/** Brand Controller Start **/
Route::get('/{brand_name}/smid-{brand_id}', [BrandController::class,'BrandPage']);
Route::get('/{brand_name}/tpid/{brand_id}', [BrandController::class,'BrandHistory']);
Route::post('/getbrandproducts', [BrandController::class,'GetBrandProducts']);
Route::post('/getbrandhistorybundleproducts', [BrandController::class,'GetBrandHistoryBundleProducts']);
Route::get('/maxaroma-bundles', [BrandController::class,'MaxaromaBundles']);

/** Brand Controller End **/

/** Category Controller Start **/
Route::get('/{category_name}/{category_name1}/scid/{category_id}', [CategoryController::class,'CategoryPage'])->name('CategoryPage1');
Route::get('/{category_name}/scid/{category_id}', [CategoryController::class,'CategoryPage'])->name('CategoryPage2');
Route::get('/{category_name}/tpid/{category_id}', [CategoryController::class,'CategoryPage'])->name('CategoryPage3');
/** Category Controller End **/

/** Product Controller Start **/

Route::get('/p4u/key-{keyword}/view', [ProductController::class,'ProductList'])->name('product-list5');

Route::get('/p4u/mid-/view', function () {
return redirect('/brand-name-perfumes.html', 301);
});

Route::get('/p4u/mid-339/cid-3/view', function () {
return redirect('/brand-name-perfumes.html', 301);
});

Route::get('/aaron-terence-hughes/', function () {
return redirect('/brand-name-perfumes.html', 301);
});

Route::get('/fragrances/unisex-perfumes/p4u/cid-4/pp-64/view', function () {
return redirect('/fragrances/unisex-perfumes/p4u/cid-4/view', 301);
});

Route::get('/newsletter/newsletter.html1629143878609', function () {
return redirect('/', 301);
});

Route::get('/max2017/privacy-policy.html', function () {
return redirect('/privacy-policy.html', 301);
});

Route::get('/p4u/key-Jessica-Mcclintock-', function () {
return redirect('/p4u/key-Jessica-Mcclintock/view', 301);
});

Route::get('/p4u/key-Roja/pp-64/view', function () {
return redirect('/p4u/key-Roja/view', 301);
});

Route::get('/usr', [TempUserController::class, 'index']);

Route::get('/{category_name}/{category_name1}/p4u/cid-{category_id}/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)')->name('product-list1');
Route::get('/{category_name}/p4u/cid-{category_id}/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)')->name('product-list2');
Route::get('/{category_name}/p4u/cid-{category_id}/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)')->name('product-list3');
Route::get('/p4u/cid-{category_id}/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)')->name('product-list4');

Route::post('/get_products', [ProductController::class,'ProductListPage']);
//Route::get('/{brand_name}/p4u/mid-{mid}/{filters?}', [ProductController::class,'BrandProductList'])->where('filters', '(.*)');
Route::get('/{brand_name}/p4u/mid-{mid}/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)');
Route::get('/{category_name}/p4u/{filters?}', [ProductController::class,'ProductList'])->where('filters', '(.*)');
Route::get('/promotional.html', [BrandController::class,'Promotional']);
Route::post('/getpromotional', [BrandController::class,'GetPromotional']);

Route::get('/offers.html', [BrandController::class,'Offers']);
Route::get('/exclusive-offers.html', [BrandController::class,'Offers']);

Route::get('/dealofweek.html/{filters?}', [ProductController::class,'Dealofweek'])->where('filters', '(.*)');
Route::post('/getdealofweek', [ProductController::class,'GetDealOfWeek']);

Route::get('/maxtwoday.html/{filters?}', [ProductController::class,'Maxtwoday'])->where('filters', '(.*)');
Route::post('/getmaxtwoday', [ProductController::class,'GetMaxtwoday']);

Route::post('/searchspring_autocomplete',[ProductController::class, 'SearchSpringAutocomplete']);
Route::get('/p4u/{filters?}/view', [ProductController::class,'ProductList'])->where('filters', '(.*)')->name('product-note');
// Catch URLs with 'pp-all' and redirect
Route::get('/p4u/key-{keyword}/pp-all/view', function ($keyword) {
    return redirect("/p4u/key-$keyword/view", 301);
})->name('ppall-redirect');

/** Product Controller End **/

/** Popup Controller Start **/
Route::post('/get_sales_offers', [PopupController::class, 'SalesOffer']);
Route::post('/show_popup',[PopupController::class, 'showPopUp']);
Route::post('/instant_coupon_ajax',[PopupController::class, 'instantCouponAjax']);
Route::post('/wishlist_add',[PopupController::class, 'wishlistAdd']);
Route::post('/niche_fragrance_membership',[PopupController::class, 'NicheFragranceMembership']);
Route::post('/product_alert_me',[PopupController::class, 'ProductAlertMe']);
Route::post('/email_friend', [PopupController::class, 'EmailFriend']);
Route::post('/ratings_review', [PopupController::class, 'ProductRatingsReview']);
Route::post('/login_pdetail_page', [PopupController::class, 'LoginProductDetailsPage']);
Route::post('/product_quick_view', [PopupController::class, 'ProductQuickView']);
Route::post('/cancel_order', [PopupController::class, 'CancelOrder']);
Route::post('/return_order', [PopupController::class, 'ReturnOrder']);
Route::post('/add_fund', [PopupController::class, 'AddFund']);
Route::post('/shipping_calculate', [PopupController::class, 'ShippingCalculate']);
Route::post('/free_shipping', [PopupController::class, 'FreeShippingPopUp']);
Route::post('/shipping_service', [PopupController::class, 'ShippingServicePopUp']);
Route::post('/wholesaler_shipping_policy', [PopupController::class, 'WholesalerShippingPolicyPopUp']);
//Route::post('/signin_signup_bk',[PopupController::class, 'SigninSignUpPopUp']);
Route::post('/signin_signup_new',[PopupController::class, 'SigninSignUpPopUp']);
Route::post('/wholesaler-terms',[PopupController::class, 'WholesalerTerms']);
Route::post('/claim_order', [PopupController::class, 'ClaimOrder']);
Route::post('/claimed_order', [PopupController::class, 'ClaimedOrder']);
Route::post('/claimed_order_police_report', [PopupController::class, 'ClaimedOrderPoliceReport']);
Route::post('/claim_terms_popup', [PopupController::class, 'ClaimOrderTermsPopup']);
Route::post('/return_item_popup', [PopupController::class, 'ReturnOrderItemPopup']);
Route::post('/return_order_item', [PopupController::class, 'ReturnOrderItem']);
Route::get('/validategoogle',[PopupController::class, 'GoogleAddressValidationPopup']);

Route::get('/{filters?}/pid/{products_id}/{category_id}', [CategoryController::class,'ProductDetails'])->where('filters', '(.*)')->name('proddetails');
Route::get('/{filters?}/pid/{products_id}/{category_id}/{code}', [CategoryController::class,'ProductDetails'])->where('filters', '(.*)')->name('proddetails_size');
Route::get('/{filters?}/pid/{products_id}/{category_id}/{code}/{private}', [CategoryController::class,'ProductDetails'])->where('filters', '(.*)')->name('proddetails_code');

Route::get('/{filters?}/tempq_pid/{products_id}/{category_id}', [CategoryController::class,'ProductDetailsTemp'])->where('filters', '(.*)')->name('proddetails');
Route::get('/{filters?}/temp_pid/{products_id}/{category_id}', [CategoryController::class,'ProductDetailsTemp'])->where('filters', '(.*)')->name('temp_proddetails_code');

Route::post('/get_freegift_products',[ShoppingcartController::class,'GetFreeGiftProducts']);

Route::post('/get_freesample_products',[ShoppingcartController::class,'GetSampleProducts']);
Route::get('/get_freesample_products',[ShoppingcartController::class,'GetSampleProducts']);
/** Common Popup - Coupon Controller End **/

/** Shoppingcart **/
Route::post('/shoppingcart/{method?}',[ShoppingcartController::class,'ShoppingcartPage'])->name('shoppingcart');
Route::get('/shoppingcart/{method?}',[ShoppingcartController::class,'ShoppingcartPage'])->name('shoppingcart');
Route::post('/cart',[ShoppingcartController::class,'SetCart']);
Route::post('/getcart',[ShoppingcartController::class,'GetCartHTML']);
Route::post('/getshopcart',[ShoppingcartController::class,'GetCartPartial']);

Route::post('/checkcoupontoredeem',[ShoppingcartController::class,'CheckCouponToRedeem']);

/** One Page Checkout - Shipping **/



Route::post('/checkout/{method?}',[ShoppingcartController::class,'CheckoutPage'])->name('billing');
Route::get('/checkout/{method?}',[ShoppingcartController::class,'CheckoutPage'])->name('billing');
Route::match(['get', 'post'],'/shipping',[ShoppingcartController::class,'ShippingMethods'])->name('billing-shipping');
Route::match(['get', 'post'],'/payment',[ShoppingcartController::class,'PaymentMethods'])->name('billing-payment');
Route::post('/setbilling',[ShoppingcartController::class,'SetBilling']);
Route::post('/checkmember',[ShoppingcartController::class,'CheckMember']);
Route::post('/setshipmethod',[ShoppingcartController::class,'SetShippingMethod']);
Route::post('/custaddfund',[ShoppingcartController::class,'CustomerAddFund']);
Route::post('/paypal_fund_response/{pagefrom}',[ShoppingcartController::class,'PaypalFundResponse']);
Route::get('/paypal_fund_response/{pagefrom}',[ShoppingcartController::class,'PaypalFundResponse']);
Route::post('/paypal_fund_process/{uid}',[ShoppingcartController::class,'PaypalFundProcess']);
Route::get('/paypal_fund_process/{uid}',[ShoppingcartController::class,'PaypalFundProcess']);
Route::get('/billing-amazon-checkout',[ShoppingcartController::class,'AmazonCheckout'])->name('AmazonBilling');
Route::get('/billing-amazon',[ShoppingcartController::class,'AmazonFundCheckout'])->name('AmazonBillingFund');
Route::get('/phoneorder-amazon',[GeneralController::class,'AmazonPhoneOrderCheckout'])->name('AmazonPhoneOrderCheckout');
Route::get('/check-template',[ShoppingcartController::class,'CheckMailTemplate']);
Route::post('/get-wholesale-price',[ProductController::class,'SetWholesalePrice']);
Route::get('/sitecart',[ShoppingcartController::class,'SetOmnisendCart']);
Route::post('/GetSlideCartData',[ShoppingcartController::class,'GetSlideCartDataPage']);

//New Checkout Page
Route::get('/secure-checkout',[ShoppingcartController::class,'CheckoutPageNew'])->name('secure-checkout');

Route::post('/secure-checkout1/{method?}', [CheckoutController::class, 'index'])->name('checkout-new');
Route::get('/secure-checkout1/{method?}', [CheckoutController::class, 'index'])->name('checkout-new');


Route::post(
    '/checkoutnew/shipping-insurance',
    [CheckoutShippingController::class, 'setShippingInsurance']
)->name('checkoutnew.shipping.insurance');

Route::post(
    '/checkoutnew/shipping-signature',
    [CheckoutShippingController::class, 'setShippingSignature']
)->name('checkoutnew.shipping.signature');



Route::post(
    '/checkoutnew/shipping-methods',
    [CheckoutShippingController::class, 'getAvailableShippingMethods']
)->name('checkoutnew.shipping.methods');

Route::post(
    '/checkoutnew/shipping-method',
    [CheckoutShippingController::class, 'setShippingMethod']
)->name('checkoutnew.shipping.method');
//Route::post('/setIntelliSuggestTrackingCart',[ShoppingcartController::class,'setIntelliSuggestTrackingCart']);

/** Shoppingcart **/
Route::post('/setcreditlimit',[ShoppingcartController::class,'SetCreditLimit']);
Route::post('/setshippinginsurance',[ShoppingcartController::class,'SetShippingInsurance']);

Route::get('/gift-guide', [LandingpageController::class,'GiftGuide'])->name('gift-guide');
Route::post('/getgiftproducts', [LandingpageController::class,'GetGiftGuideProducts']);

Route::get('/gift-guide-preview', [LandingpageController::class,'GiftGuidePreview']);
Route::post('/getgiftproductspreview', [LandingpageController::class,'GetGiftGuideProductsPreview']);

Route::get('/fragrance', [LandingpageController::class,'FragranceLandingPage']);

/** Newsletter Starts **/
Route::post('/newsletter-subscribe', [NewsletterController::class,'NewsletterSubscribe']);
/** Newsletter Ends **/

/** Reward Program Starts **/
https://www.maxaroma.com/reward-point-program-new.html
Route::get('/reward-point-program.html', [RewardProgramController::class,'RewardProgram'])->name('referral-program');
Route::get('/reward-point-program-new.html', [RewardProgramController::class,'RewardProgramNew'])->name('referral-program');
/** Reward Program Ends **/

Route::get('/referral-program.html', [RewardProgramController::class,'ReferralProgram'])->name('referral-program');

/** Customer Reviews Starts **/
Route::get('/customer-reviews.html', [CustomerController::class,'CustomerReviews']);
/** Customer Reviews Ends **/

/* Main Category Page Starts*/
// Route::get('/{category_name}/cid/{category_id}', [MainCategoryController::class,'CategoryPage']);
Route::get('/{category_name}/cid/{category_id}/{filters?}', [MainCategoryController::class,'CategoryPage'])->where('filters', '(.*)')->name('CategoryPage4');
Route::post('/{category_name}/cid/{category_id}/{filters?}', [MainCategoryController::class,'CategoryPage'])->where('filters', '(.*)');
/* Main Category Page Ends*/

Route::get('/{category_name}/tcid/{category_id}/{filters?}', [GeneralController::class,'ProductPage'])->where('filters', '(.*)')->name('product-list6');

/** Ref product start  **/
Route::post('/getrefproduct',[CategoryController::class,'GetRefProduct']);
Route::post('/getquickviewrefproduct',[PopupController::class,'GetProductQuickViewRef']);
/** Ref product end  **/

/** STRIPE BUTTON **/
Route::post('/stripebtnres',[ShoppingcartController::class,'StripButtonResponse']);
Route::post('/getclientsecret',[ShoppingcartController::class,'GetClientSecret']);
Route::post('/getstripecart',[ShoppingcartController::class,'SetCartForStripe']);
Route::post('/getshippingmodes',[ShoppingcartController::class,'GetShippingOptionsJson']);
/** STRIPE BUTTON **/

Route::post('/getpaypalcartItems',[ShoppingcartController::class,'SetCartForPaypal']);
Route::post('/paypalupdatedetails',[PaypalController::class,'CheckoutPaypalUpdate']);
Route::post('/paypallogupdate',[ShoppingcartController::class,'UpdatePaypalLog']);
Route::post('/applepaylogupdate',[ShoppingcartController::class,'updateApplePayLog']);
Route::post('/paypalordercollect',[ShoppingcartController::class,'PaypalOrderCollect']);
Route::post('/dopaymentpaypalpdp',[PaypalController::class,'DoPaymentPaypalPDP']);
Route::post('/dopaymentpaypal',[PaypalController::class,'DoPaymentPaypal']);

Route::post('/stripe/placeorder',[StripeController::class,'SetStripe']);
//Route::get('/stripe/placeorder',[StripeController::class,'SetStripe']);
Route::get('/stripe/placeorder/{ordernoid}',[StripeController::class,'SetStripe']);
Route::get('/stripe/addfund/{pagefrom}',[StripeController::class,'AddFund']);
Route::post('/placeorder',[ShoppingcartController::class,'PlaceOrder'])->name('placeorder');
Route::post('/order-receipt',[ShoppingcartController::class,'OrderReceipt'])->name('order-receipt');
Route::get('/order-receipt',[ShoppingcartController::class,'OrderReceipt'])->name('order-receipt');
Route::post('/paypal/placeorder',[PaypalController::class,'SetPaypal']);
Route::get('/paypal/placeorder/{dropsipflag?}',[PaypalController::class,'SetPaypal']);
Route::get('/paypal/success/{dropsipflag?}',[PaypalController::class,'Success']);
Route::get('/paypal/cancel',[PaypalController::class,'Cancel']);
Route::get('/paypal/dopayment/{dropsipflag?}',[PaypalController::class,'DoPayment']);
Route::get('/dropshipper-details',[ShoppingcartController::class,'GetDropshipperFundDetails']);
Route::post('/set-dropship-order',[CustomerController::class,'SetDropshiperOrder']);

Route::patch('/update_paypal_order',[PaypalController::class,'updatePaypalOrder']);
//Route::patch('/update_paypal_order_shipping_option', [PayPalController::class, 'updatePaypalOrderShippingOption']);
Route::post('update_paypal_order_shipping_option', [PaypalController::class, 'updatePaypalOrderShippingOption']);
Route::post('/paypalbuynoworder',[ShoppingcartController::class,'paypalBuynowOrder']);
Route::post('/placepaypalorder',[ShoppingcartController::class,'PlacePayPalOrder']);
Route::match(['get', 'post'],'/shippingpaypal',[ShoppingcartController::class,'ShippingMethodsPayPal'])->name('billing-shipping');

Route::post('/paypal/phoneorder',[PaypalController::class,'PhoneOrder']);
Route::get('/paypal/phoneorder',[PaypalController::class,'PhoneOrder']);
Route::get('/paypal/success_phoneorder',[PaypalController::class,'Success_Phoneorder']);
Route::get('/paypal/cancel_phoneorder',[PaypalController::class,'Cancel_Phoneorder']);
Route::post('/paypal/capture_phoneorder',[PaypalController::class,'Capture_Phoneorder']);

Route::get('/amazon/login',[PaypalController::class,'DoPayment'])->name('AmazonpayLogin');

/* Afterpay Routes Start */
Route::post('/afterpay/placeorder',[AfterpayController::class,'SetAfterpay']);
Route::get('/afterpay/placeorder',[AfterpayController::class,'SetAfterpay']);
Route::post('/afterpay/placeorder/{ordernoid}',[AfterpayController::class,'SetAfterpay']);
Route::get('/afterpay/placeorder/{ordernoid}',[AfterpayController::class,'SetAfterpay']);
Route::get('/afterpay/success',[AfterpayController::class,'Success']);
Route::get('/afterpay/cancel',[AfterpayController::class,'Cancel']);
Route::get('/afterpay/dopayment/{order_id}',[AfterpayController::class,'DoPayment']);
Route::get('/afterpay/phoneorder', [AfterpayController::class,'PhoneOrder']);
Route::get('/afterpay/success_phoneorder',[AfterpayController::class,'Success_Phoneorder']);
Route::get('/afterpay/cancel_phoneorder',[AfterpayController::class,'Cancel_Phoneorder']);
Route::get('/afterpay/dopayment_phoneorder/{order_id}',[AfterpayController::class,'DoPayment_Phoneorder']);

Route::post('/afterpay/phoneorder_express', [AfterpayController::class,'PhoneOrder_Express']);
Route::get('/afterpay/phoneorder_express', [AfterpayController::class,'PhoneOrder_Express']);
// Route::post('/afterpay/success_phoneorder_express',[AfterpayController::class,'Success_Phoneorder_Express']);
// Route::get('/afterpay/success_phoneorder_express',[AfterpayController::class,'Success_Phoneorder_Express']);
Route::get('/afterpay/success_phoneorder_express/{status}/{orderToken}',[AfterpayController::class,'Success_Phoneorder_Express']);
Route::get('/afterpay/dopayment_phoneorder_express/{order_id}',[AfterpayController::class,'DoPayment_Phoneorder_Express']);

Route::post('/afterpay/placeorder_express',[AfterpayController::class,'SetAfterpay_Express']);
Route::get('/afterpay/placeorder_express',[AfterpayController::class,'SetAfterpay_Express']);
Route::get('/afterpay/success_express',[AfterpayController::class,'Success_Express']);
Route::get('/afterpay/billing_checkout_express/{status}/{orderToken}',[AfterpayController::class,'Billing_Checkout_Express']);
Route::get('/afterpay/success_express/{ap_psChecksum}',[AfterpayController::class,'Success_Express']);
Route::get('/afterpay/success_express/{ap_psChecksum}/{ordernoid}',[AfterpayController::class,'Success_Express']);
Route::get('/afterpay/dopayment_express/{order_id}',[AfterpayController::class,'DoPayment_Express']);
Route::get('/afterpay/dopayment_express_btm/{ordernoid}',[AfterpayController::class,'DoPayment_Express_BTM']);
Route::get('/afterpay/dopayment_express_btm',[AfterpayController::class,'DoPayment_Express_BTM']);
/* Afterpay Routes End */

Route::get('/setupamazon/{page_from?}',[AmazonpayController::class,'SetupAmazon']);
Route::post('/amazon/order-details',[AmazonpayController::class,'GetOrderInfo']);
Route::post('/amazon/phoneorder-details',[AmazonpayController::class,'GetOrderInfo_Phoneorder']);
Route::get('amazon/placeorder',[AmazonpayController::class, 'AmazonPlaceOrder']);
Route::post('amazon-fund-process',[AmazonpayController::class, 'AmazonFundProcess']);
Route::post('amazon-phoneorder-process',[AmazonpayController::class, 'AmazonPhoneOrderProcess']);

/** Cache Clear Start **/
Route::post('/clearfrontcache',[FrontCacheController::class,'ClearFrontCache']);
/** Cache Clear End **/
Route::post('/createfrontcache',[FrontCacheController::class,'CreateFrontCache']);

Route::get('/redirect', [SocialLoginController::class,'redirect']);
Route::get('/callback', [SocialLoginController::class,'callback']);

Route::get('/redirectgoogle', [SocialLoginController::class,'redirectgoogle']);
Route::get('/callbackgoogle', [SocialLoginController::class,'callbackgoogle']);

Route::get('/fb/deletion/{id}', [FbUserDeleteController::class,'deletion']);
Route::get('/fb/deletion', [FbUserDeleteController::class,'deletion']);

Route::post('/dropship-shipping-method-ajax',[CustomerController::class,'DropshipShippingMethods']);
Route::post('/dropship-order-summary-ajax',[CustomerController::class,'DropshipOrderSummary']);
Route::delete('/remove-dropship-order-item',[CustomerController::class,'DropshipOrderItemRemove']);
Route::post('/ajax-list-skus',[CustomerController::class,'AjaxListSkus']);

/** blog Starts **/
Route::get('/top-10-trending-womens-fragrances-for-autumn/27', function () {
return redirect('/blog/top-10-trending-womens-fragrances-for-autumn/27', 301);
});
Route::get('/our-favorite-bdk-fragrances/26', function () {
return redirect('/blog/our-favorite-bdk-fragrances/26', 301);
});
Route::get('/top-5-the-spirit-of-dubai-fragrances/25', function () {
return redirect('/blog/top-5-the-spirit-of-dubai-fragrances/25', 301);
});
Route::get('/5-niche-perfume-brands-to-pay-attention/24', function () {
return redirect('/blog/5-niche-perfume-brands-to-pay-attention/24', 301);
});
Route::get('/all-about-gourmand-fragrances/20', function () {
return redirect('/blog/all-about-gourmand-fragrances/20', 301);
});
Route::get('/5-must-try-spring-fragrances-for-men/19', function () {
return redirect('/blog/5-must-try-spring-fragrances-for-men/19', 301);
});
Route::get('/find-your-perfect-scent:-top-5-best-spring-fragrances-for-women/18', function () {
return redirect('/blog/find-your-perfect-scent:-top-5-best-spring-fragrances-for-women/18', 301);
});
Route::get('/6-gorgeous-fragrance-gift-sets-to-gift-on-mothers-day/17', function () {
return redirect('/blog/6-gorgeous-fragrance-gift-sets-to-gift-on-mothers-day/17', 301);
});
Route::get('/5-of-the-best-modern-oud-fragrances/16', function () {
return redirect('/blog/5-of-the-best-modern-oud-fragrances/16', 301);
});
Route::get('/a-few-floral-perfumes-(-omanluxury)/15', function () {
return redirect('/blog/a-few-floral-perfumes-(-omanluxury)/15', 301);
});
Route::get('/alghabra-perfumes/14', function () {
return redirect('/blog/alghabra-perfumes/14', 301);
});
Route::get('/introducing-omanluxury-perfumes/13', function () {
return redirect('/blog/introducing-omanluxury-perfumes/13', 301);
});
Route::get('/7-tips-to-make-your-perfume-last-longer/12', function () {
return redirect('/blog/7-tips-to-make-your-perfume-last-longer/12', 301);
});
Route::get('/blend-oud:-the-original-collection/10', function () {
return redirect('/blog/blend-oud:-the-original-collection/10', 301);
});
Route::get('/masque-milano-fragrances(part-2)/8', function () {
return redirect('/blog/masque-milano-fragrances(part-2)/8', 301);
});
Route::get('/moresque-parfum,-part-2-(art-collection)/7', function () {
return redirect('/blog/moresque-parfum,-part-2-(art-collection)/7', 301);
});
Route::get('/masque-milano-fragrances-(part-1)/6', function () {
return redirect('/blog/masque-milano-fragrances-(part-1)/6', 301);
});
Route::get('/moresque-fragrances-(-white-collection)/5', function () {
return redirect('/blog/moresque-fragrances-(-white-collection)/5', 301);
});
Route::get('/blog', [BlogController::class,'Blog']);
Route::get('/blog/{title}/{id}', [BlogController::class,'BlogDetailPage']);
Route::post('/getBlogProducts', [BlogController::class,'BlogDetailPageProducts']);
/** blog End **/

/** POS System Start **/
Route::match(['get', 'post'], '/skipaddress', [POSController::class, 'SetSkipGuestCustomer']);
Route::match(['get', 'post'], '/setguestcustomer', [POSController::class, 'SetGuestCustomer']);
Route::match(['get', 'post'], '/setguestcustomerautocomplete', [POSController::class, 'SetGuestCustomerAutoComplete']);
Route::middleware([POSMaintainMode::class])->group(function () {

	Route::match(['get', 'post'], '/shiptostore', [POSController::class, 'ShipToStore']);
    Route::match(['get', 'post'], '/posautologin', [POSController::class, 'SetPosAutoLogin']);
    Route::get('/store/pos-components.html', [POSController::class, 'POSComponents'])->name('retailer-registration');
    Route::get('/store/pos-components.html', [POSController::class, 'POSComponents']);

	Route::get('/store/order-detail-print/{id}.html', [POSController::class,'OrderDetailPrint']);

	Route::get('/store/login.html', [POSController::class,'Login'])->name('login');
	Route::post('/store/login.html', [POSController::class,'Login']);

	Route::get('/store/store-security.html', [POSController::class,'StoreSecurity'])->name('storesecurity');
	Route::post('/store/store-security.html', [POSController::class,'StoreSecurity']);

	Route::post('/store/forgot-password.html', [POSController::class,'ForgotPassword']);
	Route::get('/store/forgot-password.html', [POSController::class,'ForgotPassword'])->name('storeforgot-password');
	Route::redirect('/store', '/store/login.html');

	Route::get('/store/login-from-cookiee', [POSController::class,'LoginFromCookiee'])->name('login_from_cookiee');

	Route::middleware('auth:store')->group(function(){

		Route::get('/store/customer-login.html', [POSController::class,'CustomerLogin'])->name('myaccount');
		Route::get('/store/store-dashboard.html', [POSController::class,'Myaccount'])->name('myaccount');

		Route::get('/store/change-password.html', [POSController::class,'ChangePassword'])->name('storechangepassword');
		Route::post('/store/change-password.html', [POSController::class,'ChangePassword']);

		Route::get('/store/logout', [POSController::class,'Logout'])->name('store_logout');
		Route::get('/store/adminlogin', [POSAdmController::class,'adminLoginRedirect']);

		Route::get('/store/editprofile.html', [POSController::class,'Editprofile']);
		Route::post('/store/editprofile.html',[POSController::class,'Editprofile']);

		Route::get('/store/create-order.html', [POSController::class,'CreateOrder'])->name('create-order');
		Route::post('/store/create-order.html', [POSController::class,'CreateOrder'])->name('create-order');

		Route::post('/store/validate_salesperson', [POSController::class,'ValidateSalesPerson'])->name('validate-salesperson');
		Route::post('/store/search_salesperson', [POSController::class,'SearchSalesPerson'])->name('search-salesperson');
		Route::post('/store/add_salesperson', [POSController::class,'AddSalesPerson'])->name('add-salesperson');
		Route::match(['get', 'post'],'/store-payment',[ShoppingcartController::class,'StorePaymentMethods'])->name('billing-payment');

		Route::match(['get', 'post'],'/process-payment',[POSController::class,'ProcessPayment']);

		Route::post('storeupdateorder', [POSController::class, 'StoreUpdateOrder'])->name('storeupdateorder');

		Route::get('/store/devices', [POSConnecterController::class, 'devicesStatus'])->name('devicesStatus');
		Route::post('/store/connect-reader', [POSConnecterController::class, 'ConnectNewReader'])->name('connectNewReader');
		Route::post('/store/change-connect-reader', [POSConnecterController::class, 'ChangeCardReader'])->name('changeCardReader');

		//
		Route::get('store/cash-drawer',[POSController::class,'cashDrawer'])->name('cash-drawer');

		Route::get('store/cash-drawer-daily-open',[POSController::class,'cashDrawerDailyOpen'])->name('cash-drawer-daily-open');
		Route::post('store/save-cash-drawer-daily-open',[POSController::class,'saveCashDrawerDailyOpen'])->name('cash-drawer-daily-open');

		Route::get('store/cash-drawer-daily-close',[POSController::class,'cashDrawerDailyClose'])->name('cash-drawer-daily-close');
		Route::post('store/save-cash-drawer-daily-close',[POSController::class,'saveCashDrawerDailyClose'])->name('cash-drawer-daily-close');

		Route::get('store/cash-drawer-manual-open',[POSController::class,'cashDrawerManualOpen'])->name('cash-drawer-manual-open');
		Route::post('store/cash-drawer-manual-open',[POSController::class,'cashDrawerManualOpen'])->name('cash-drawer-manual-open');
		Route::post('store/save-cash-drawer-manual-open',[POSController::class,'saveCashDrawerManualOpen'])->name('cash-drawer-manual-open');

		Route::post('store/save-pos-order-cash-change',[POSController::class,'savePOSOrderCashChangeData'])->name('save-store-order-cash-change-data');
		Route::post('store/cash-drawer/log',[POSController::class,'cashDrawerLog'])->name('cash-drawer-log');

		Route::get('store/get-daily-open-pending-popup',[POSController::class,'getDailyOpenPendingPopup'])->name('cash-drawer-daily-open-pending-popup');
		Route::get('store/storeDeviceLog',[POSController::class,'storeDeviceLog'])->name('device-log');
		Route::get('store/search-product', [POSController::class, 'searchProduct'])->name('search-product');
		Route::get('store/search-customer', [CustomerController::class, 'searchCustomer'])->name('search-customer');

		Route::post('/order-receipts',[POSController::class,'OrderReceipts'])->name('order-receipts');
		Route::get('/order-receipts',[POSController::class,'OrderReceipts'])->name('order-receipts');
		Route::get('/pos-test', function () {
			return view('pos-test');
		});

		Route::post('store/getReceiptData',[POSController::class,'getReceiptData'])->name('print-receipt-data');

		Route::get('/terminal/pos-create-intent', [StripeTerminalController::class, 'createPaymentIntentCheckout']);
		Route::post('/terminal/pos-create-intent', [StripeTerminalController::class, 'createPaymentIntentCheckout']);

		Route::get('/terminal/pos-create-intent-normal', [StripeTerminalController::class, 'createPaymentIntent']);
		Route::post('/terminal/pos-create-intent-normal', [StripeTerminalController::class, 'createPaymentIntent']);

		Route::get('/terminal/pos-process-intent', [StripeTerminalController::class, 'PaymentProcessCheckout']);
		Route::post('/terminal/pos-process-intent', [StripeTerminalController::class, 'PaymentProcessCheckout']);

		Route::get('/terminal/cancel-intent', [StripeTerminalController::class, 'PaymentCancel']);
		Route::post('/terminal/cancel-intent', [StripeTerminalController::class, 'PaymentCancel']);

	});

/*	Route::get('/terminal/create-payment-intent', [StripeTerminalController::class, 'createPaymentIntent']);
	Route::post('/terminal/connection-token', [StripeTerminalController::class, 'createConnectionToken']);
	*/
	//Route::post('/terminal/create-payment-intent', [StripeTerminalController::class, 'createPaymentIntent']);

});

/** POS System End **/

Route::get('pdp.html', [CategoryController::class,'Pdp']);
Route::get('pdp1.html', [CategoryController::class,'Pdp1']);
Route::post('/servercheck', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/refresh-csrf', function () {
    return response()->json(['token' => csrf_token()]);
});

Route::post('/setguestmember',[ShoppingcartController::class,'SetGuestToMember']);

//Maxaroma-Edit
Route::get('maxaroma-edit',[LandingpageController::class,'MaxaromaEdit']);
Route::get('/get-csrf-token', [LandingpageController::class,'GetCsrfToken']);
Route::post('/create-maxaroma-edit-cache',[FrontCacheController::class,'CreateMaxaromaEditPageCache']);
Route::get('/maxaroma-edit-preview', [LandingpageController::class,'MaxaromaEditPreview']);
Route::get('/seasonal-landing-pages', [LandingpageController::class,'SeasonalLandingPages']);
Route::get('/luxury-edit', [LandingpageController::class,'LuxuryEdit']);

/* New Checkout */



Route::post(
    '/checkout/gift-certificate',
    [CheckoutShippingController::class, 'setGiftCertificate']
)->name('checkout.gift-certificate');

Route::post(
    '/checkout/gift-wrapping',
    [CheckoutShippingController::class, 'setGiftWrapping']
)->name('checkout.gift-wrapping');
/** One Page Checkout - Shipping End **/

/** One Page Checkout - Discount **/
Route::post(
    '/checkout/discount/apply',
    [CheckoutDiscountController::class, 'apply']
)->name('checkout.discount.apply');

Route::post(
    '/checkout/discount/remove',
    [CheckoutDiscountController::class, 'removeCoupon']
)->name('checkout.discount.remove');

Route::post(
    '/checkout/discount/remove-yotpo-reward',
    [CheckoutDiscountController::class, 'removeYotpoReward']
)->name('checkout.discount.remove-yotpo-reward');
/** One Page Checkout - Discount End **/


