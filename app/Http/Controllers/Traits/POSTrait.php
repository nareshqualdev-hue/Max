<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use App\Http\Controllers\Traits\VendorTrait;
use App\Models\HomepageProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductsCategory;
use App\Models\ProductsReview;
use App\Models\Listingmenu;
use App\Models\Manufacture;
use App\Models\Products;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\GiftCertificate;
use App\Models\Customer;
use App\Models\RewardRule;
use App\Models\MailBanner;
use App\Models\RewardPoint;
use App\Models\Dealofweek;
use App\Models\ReferFriend;
use App\Models\SiteSettings;
use App\Models\StoreCardReader;

use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;
use DB;
use Session;
use Cache;
use PDF;
use Carbon\Carbon;

trait POSTrait
{
	use VendorTrait;
	use CartTrait;
	protected $Afterpay_Checkout="No";

	public function SplitCalculateSubTotal($CartArr=array(),$Flag="Store")
	{
		if($CartArr && count($CartArr) > 0)
		{
			$shoppingcart = $CartArr;
			$count = count($shoppingcart);
			$SubTotal = 0;
			$TotalItemInCart = 0 ;

			for($a=0; $a<$count; $a++)
			{
				$SubTotal += $shoppingcart[$a]['TotPrice'];
				$TotalItemInCart += $shoppingcart[$a]['Qty'];
			}

			Session::put('ShoppingCart.'.$Flag.'SubTotal',NumberFormat($SubTotal));
			Session::put('ShoppingCart.'.$Flag.'TotalItemInCart',$TotalItemInCart);
		}
	}

	public function ApplySplitAutoDiscount($CartArr=array(),$Flag="Store")
	{
		$auto_discount = 0;
		$NewSubTotal = 0;
		$AutoDiscountItemWise = 0;

		$pocketPerfumeCategory = $this->getPocketPerfumeCategory();
		$log['pocketPerfumeCategory'] = json_encode($pocketPerfumeCategory);
		addLog('ApplyAutoDiscountStart',$log);
        if($CartArr && count($CartArr) > 0)
        {
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}
			$this->getSplitAllDiscountBlank("Auto",$CartArr,$Flag);
			//if(Auth::user() && Session::get('eusertype') == "Wholesaler")
			if($normaluser && Session::get('eusertype') == "Wholesaler")
			{
				Session::put('ShoppingCart.'.$Flag.'AutoDiscount',0);
				return null;
			}

			if(Session::has('ShoppingCart.'.$Flag.'SubTotal'))
				$NewSubTotal = NumberFormat(Session::get('ShoppingCart.'.$Flag.'SubTotal'));
			$GiftCertiTotal = 0;
			if(Session::has('ShoppingCart.'.$Flag.'GiftCertiTotal'))
				$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.'.$Flag.'GiftCertiTotal'));
			$subTotal = $NewSubTotal - $GiftCertiTotal;

			$DealSubTotal = $this->getSplitDealSubTotal($CartArr,$Flag);

			$subTotal = $subTotal - $DealSubTotal;

			$log['NewSubTotal'] = $NewSubTotal;
			$log['GiftCertiTotal'] = $GiftCertiTotal;
			$log['DealSubTotal'] = $DealSubTotal;
			$log['subTotal'] = $subTotal;
			addLog('ApplyAutoDiscount',$log);

            $Cart = $CartArr;
            $discount_coupon_flag = '';
            if($subTotal <= 0 )
            {
                Session::put('ShoppingCart.'.$Flag.'AutoDiscount',0);
                Session::put('ShoppingCart.'.$Flag.'AutoDiscountFlag','');
                return NULL;
            }
            $CouponCode = "";
            if(Session::has('ShoppingCart.PromoCoupon.CouponCode') && Session::get('ShoppingCart.PromoCoupon.CouponCode') != '')
                $CouponCode = Session::get('ShoppingCart.PromoCoupon.CouponCode');
            if($CouponCode !='')
            {
                $coupon_res = Coupon::select('autodiscount_flag')
                                ->where('coupon_number','=',$CouponCode)
                                ->where('status','=','1')
                                ->where('start_date','<=',DB::raw('curdate()'))
                                ->where('end_date','>=',DB::raw('curdate()'))
                                ->get();
                if($coupon_res && $coupon_res->count() > 0)
                {
					$log['coupon_res'] = $coupon_res;
					addLog('ApplyAutoDiscount',$log);
                    if($coupon_res[0]->autodiscount_flag == "No")
                    {
                        Session('ShoppingCart.'.$Flag.'AutoDiscount',0);
                        Session('ShoppingCart.'.$Flag.'AutoDiscountFlag','');
                        return NULL;
                    }
                }
            }

            $AutoRS = AutoDiscount::where('start_date','<=',DB::raw('curdate()'))
                        ->where('end_date','>=',DB::raw('curdate()'))
                        ->where('end_order_amount','>=',$subTotal)
                        ->where('order_amount','<=',$subTotal)
                        ->where('status','=','1')->orderBy('end_order_amount','desc')->get();

            if($AutoRS && $AutoRS->count() <= 0)
            {
                $AutoRS = AutoDiscount::where('start_date','<=',DB::raw('curdate()'))
                        ->where('end_date','>=',DB::raw('curdate()'))
                        ->where('end_order_amount','<=',$subTotal)
                        ->where('status','=','1')->orderBy('end_order_amount','desc')->get();
            }

			$log['AutoRS'] = $AutoRS;
			addLog('ApplyAutoDiscount',$log);

            $TotalItems = $CartArr;
            $TotalAutoDiscountRecords = $AutoRS->count();
            $TotalExcludePrice = 0;
            if($TotalAutoDiscountRecords > 0)
            {
                 $SKURemoveArr = '';
                 for($i=0;$i<$TotalAutoDiscountRecords;$i++)
                 {
                    $discount_coupon_flag = $AutoRS[$i]->discount_coupon_flag;
                    $ExcludeSKUListArr = array();
                    $TotalExcludePrice = 0;

                    if($TotalItems > 0 && $TotalAutoDiscountRecords > 0)
                    {
						if(isset($AutoRS[$i]->exclude_sku) && trim($AutoRS[$i]->exclude_sku)!='')
						{
							$ExcludeSKUListArr = array();
							$ExcludeSKUListArr  = explode(",",$AutoRS[$i]->exclude_sku);
							$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
							$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
							$TotalExcludePrice = 0;
							for($p=0;$p<$TotalItems;$p++)
							{
								if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
								{

									$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
								}
							}
						}
						if(isset($AutoRS[$i]->exclude_pocketperfume) && $AutoRS[$i]->exclude_pocketperfume=="Yes")
						{

							for($p=0;$p<$TotalItems;$p++)
							{
								if(!empty($Cart[$p]["CategoryID"]) && $Cart[$p]["CategoryID"] > 0  && in_array($Cart[$p]["CategoryID"],$pocketPerfumeCategory))
								{
									$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
								}
							}
						}
                    }
					if($AutoRS[$i]["orders"]=='0')
					{
						$QtySKU = trim($AutoRS[$i]['sku']);
						########### For Multiple SKU ###############
						$arr_QtySKU  = explode(",",$QtySKU);

						$arr_QtySKU  = array_unique(array_map('trim',$arr_QtySKU));
						$arr_QtySKU  = array_filter($arr_QtySKU, 'strlen');

						$AutoDiscount1 = 0;

						if(is_array($arr_QtySKU) and !empty($arr_QtySKU))
						{
							$tempCart  = $CartArr;
							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}
							$total_qty = 0;
							$total_price = 0;
							$TotalAmount =0;
							for ($a=0; $a<count($tempCart); $a++)
							{

								if(trim($AutoRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}

								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];
								if(in_array($tempCart[$a]['SKU'] , $arr_QtySKU) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] ,$SKURemoveArrNew))
								{
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($AutoRS[$i]['type'] == 1 )
									{
									   $AutoDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $AutoRS[$i]['auto_discount_amount']/100);
									   Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'AutoItemWiseDiscout',$AutoDiscountItemWise);
									   $AutoDiscount1 = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $AutoDiscount1;
									}
									$SKURemoveArr.= $tempCart[$a]['SKU'].",";
								}
							}
							if($AutoRS[$i]['type'] == 0 && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{

									if(trim($AutoRS[$i]["exclude_pocketperfume"])=='Yes')
									{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}
									}

									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];
									if(in_array($tempCart[$a]['SKU'] , $arr_QtySKU) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] ,$SKURemoveArrNew))
									{

										$AutoDiscountItemWise = (($AutoRS[$i]['auto_discount_amount']*100)/$TotalAmount);

										$AutoDiscountCal = (($tempCart[$a]['TotPrice']  * $AutoDiscountItemWise)/100);

										Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'AutoItemWiseDiscout',$AutoDiscountCal);

									}
								}
							}
							$MatchNewAutoDiscount=  0;
							if($auto_discount > 0)
							{
								$MatchNewAutoDiscount = $auto_discount;
							}

							if($AutoRS[$i]['type'] == 1)
							{
								$auto_discount = ($AutoDiscount1 * ($AutoRS[$i]['auto_discount_amount']/100));
							}
							else
							{
								$auto_discount = $AutoRS[$i]['auto_discount_amount'];
							}
							 $auto_discount = $auto_discount + $MatchNewAutoDiscount;

						}
					}
					else if($AutoRS[$i]["orders"] == '1')
					{
						if($AutoRS[$i]->sku !='')
						{
                        $QtySKU = trim($AutoRS[$i]->sku);
                        $QtyBrandID    	= trim($AutoRS[$i]->sku); // Category IDS
                        $arr_QtyBrandID    = explode(",",$QtyBrandID);
                        $AutoDiscount1 = 0;
                        $found_brand = false; // Use for if coupon valid but category not found in cart;

                        ## Get Active Cat ID
                        $Res_active_BrandID = Manufacture::where('status','=','1')
                                                ->whereIn('imanufactureid',$arr_QtyBrandID)->get();

                        $arr_active_BrandID = array();
                        for($h=0;$h<count($Res_active_BrandID);$h++)
                        {
                            $arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
                        }
						$log['arr_active_BrandID'] = $arr_active_BrandID;
						addLog('ApplyAutoDiscount',$log);
                        if(count($arr_active_BrandID) > 0 )
                        {
                            ## Get Cart Prod ID
                            $tempCart = $CartArr;
                            $temp_prod_id   = array();

                            for ($a=0; $a<count($tempCart); $a++)
                            {
                                $temp_prod_id[$a] = $tempCart[$a]['ProductID'];
                            }
							$log['temp_prod_id'] = $temp_prod_id;
							addLog('ApplyAutoDiscount',$log);
                            $ProdIds = Products::select('products_id')->distinct()
                                        ->whereIn('imanufactureid',$arr_active_BrandID)
                                        ->whereIn('products_id',$temp_prod_id)
                                        ->get();

                            $brand_prod_id  = array();

                            for ($a=0; $a<$ProdIds->count(); $a++)
                            {
                                $brand_prod_id[$a] = $ProdIds[$a]['products_id'];
                            }
							$log['brand_prod_id'] = $brand_prod_id;
							addLog('ApplyAutoDiscount',$log);
                            $SKURemoveArrNew = [];
                            if($SKURemoveArr!='')
                            {
                                $SKURemoveArrNew = explode(",",$SKURemoveArr);
                                $SKURemoveArrNew = array_filter($SKURemoveArrNew);
                                $SKURemoveArrNew = array_values($SKURemoveArrNew);
                            }
                            $totalPrice = 0;
                            for ($a=0; $a<count($tempCart); $a++)
                            {
                                $FreeGift = "";
                                if(isset($tempCart[$a]["IS_Free_Gift"]))
                                    $FreeGift = $tempCart[$a]["IS_Free_Gift"];
                                if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) &&  !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
                                {

									if(!empty($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && isset($AutoRS[$i]["exclude_pocketperfume"]) && $AutoRS[$i]["exclude_pocketperfume"]=="Yes" && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}

									$totalPrice = $totalPrice + $tempCart[$a]['TotPrice'];
                                    if($AutoRS[$i]['type'] == 1 )
                                    {
										$AutoDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $AutoRS[$i]['auto_discount_amount']/100);
										Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'AutoItemWiseDiscout',$AutoDiscountItemWise);
                                        $AutoDiscount1 = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $AutoDiscount1;
                                    }
                                    $found_brand = true;
                                    $SKURemoveArr.= $tempCart[$a]['SKU'].",";
                                }
                            }
                            if($AutoRS[$i]['type'] == 0 && $totalPrice > 0)
                            {
								for ($a=0; $a<count($tempCart); $a++)
								{

									if(!empty($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && isset($AutoRS[$i]["exclude_pocketperfume"]) && $AutoRS[$i]["exclude_pocketperfume"]=="Yes" && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}

									$FreeGift = "";
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];
									if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) &&  !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
										{
											$AutoDiscountItemWise = (($AutoRS[$i]['auto_discount_amount']*100)/$totalPrice);

											$AutoDiscountCal = (($tempCart[$a]['TotPrice']  * $AutoDiscountItemWise)/100);

											Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'AutoItemWiseDiscout',$AutoDiscountCal);

										}
								}

							}
                        }
                        if($found_brand==true)
                        {
                            $QuantityDiscountFlag = $AutoRS[$i]["discount_coupon_flag"];
                            $ExcludeSKUListArr = array();
                            $TotalExcludePrice = 0;
                            if($TotalItems > 0 && $TotalAutoDiscountRecords > 0 && trim($AutoRS[$i]["exclude_sku"])!='')
                            {
                                $ExcludeSKUListArr = array();
                                $ExcludeSKUListArr  = explode(",",$AutoRS[$i]["exclude_sku"]);
                                $ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
                                $ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
                                $TotalExcludePrice = 0;
                                for($p=0;$p<$TotalItems;$p++)
                                {
                                    if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
                                    {

										if(isset($Cart[$p]["TotPrice"]) && $Cart[$p]["TotPrice"] > 0)
										{
											$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
										}
                                    }
                                }
                            }

                            for($p=0;$p<$TotalItems;$p++)
                            {
								if(!empty($tempCart[$p]["CategoryID"]) && $tempCart[$p]["CategoryID"] > 0 && isset($AutoRS[$i]["exclude_pocketperfume"]) && $AutoRS[$i]["exclude_pocketperfume"]=="Yes" && in_array($tempCart[$p]["CategoryID"],$pocketPerfumeCategory))
								{
									if(isset($Cart[$p]["TotPrice"]) && $Cart[$p]["TotPrice"] > 0)
									{
										$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
									}
								}
							}

                            $MatchNewAutoDiscount=  0;
                            if($auto_discount > 0)
                            {
                                $MatchNewAutoDiscount = $auto_discount;
                            }
                            if($AutoRS[$i]['type'] == 1)
                            {
                                $auto_discount = ($AutoDiscount1  * $AutoRS[$i]['auto_discount_amount']/100);
                            }
                            else
                            {
                                $auto_discount = $AutoRS[$i]['auto_discount_amount'];
                            }
                            $auto_discount = $auto_discount + $MatchNewAutoDiscount;
                        }
                    }
					}
                    else
                    {
                        $AutoRS1 = AutoDiscount::where('start_date','<=',DB::raw('curdate()'))
                            ->where('end_date','>=',DB::raw('curdate()'))
                            ->where('end_order_amount','>=',$subTotal)
                            ->where('order_amount','<=',$subTotal)
                            ->where('status','=','1')->where('sku','=','')
                            ->orderBy('end_order_amount','desc')->limit(1)->get();

                        $TotalAutoDiscountRecords1 = $AutoRS1->count();
                        if($TotalAutoDiscountRecords1<=0)
                        {
                            $AutoRS1 = AutoDiscount::where('start_date','<=',DB::raw('curdate()'))
                            ->where('end_date','>=',DB::raw('curdate()'))
                            ->where('end_order_amount','<=',$subTotal)
                            ->where('status','=','1')->where('sku','=','')
                            ->orderBy('end_order_amount','desc')->limit(1)->get();
                            $TotalAutoDiscountRecords1 = $AutoRS1->count();
                        }

                        if($TotalAutoDiscountRecords1 > 0)
                        {
							$log['AutoRS1'] = $AutoRS1;
							addLog('ApplyAutoDiscount',$log);
							$tempCart = $CartArr;

							$SKURemoveArrNew = [];
                            if($SKURemoveArr!='')
                            {
                                $SKURemoveArrNew = explode(",",$SKURemoveArr);
                                $SKURemoveArrNew = array_filter($SKURemoveArrNew);
                                $SKURemoveArrNew = array_values($SKURemoveArrNew);
                            }

                            $ExcludeSKUListArr = array();
							$ExcludeSKUListArr  = explode(",",$AutoRS1[0]["exclude_sku"]);
							$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
							$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
                            $totalPrice = 0;
                            for ($a=0; $a<count($tempCart); $a++)
                            {
								//Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',0);
                                $FreeGift = "";
                                if(isset($tempCart[$a]["IS_Free_Gift"]))
                                    $FreeGift = $tempCart[$a]["IS_Free_Gift"];
                                if (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) &&  !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
                                {

									if(isset($AutoRS1[0]["exclude_pocketperfume"]) && $AutoRS1[0]["exclude_pocketperfume"]=="Yes" && !empty($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0&&  in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory) )
									{
										continue;
									}

									$totalPrice = $totalPrice + $tempCart[$a]['TotPrice'];
                                    if($AutoRS1[0]["type"] == 1 )
                                    {
										$AutoDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $AutoRS1[0]["auto_discount_amount"]/100);
										Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'AutoItemWiseDiscout',$AutoDiscountItemWise);

                                    }
                                    $SKURemoveArr.= $tempCart[$a]['SKU'].",";
                                }
                            }

							if($AutoRS1[0]["type"] == 0 && $totalPrice > 0)
                            {
								for ($a=0; $a<count($tempCart); $a++)
								{
									//Session::put('ShoppingCart.Cart.'.$a.'.AutoItemWiseDiscout',0);
									$FreeGift = "";
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];
									if (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift != "Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) &&  !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
									{

										if(isset($AutoRS1[0]["exclude_pocketperfume"]) && $AutoRS1[0]["exclude_pocketperfume"]=="Yes" && !empty($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}

										$AutoDiscountItemWise = (($AutoRS1[0]['auto_discount_amount']*100)/$totalPrice);
										$AutoDiscountCal = (($tempCart[$a]['TotPrice']  * $AutoDiscountItemWise)/100);
										Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'AutoItemWiseDiscout',$AutoDiscountCal);
									}
								}
							}

                            $discount_coupon_flag = $AutoRS1[0]['discount_coupon_flag'];
                            $subTotal = $subTotal - $TotalExcludePrice;
                            if($AutoRS1[0]['type'] == '1')
                                $auto_discount = ( $subTotal * ($AutoRS1[0]['auto_discount_amount']/100) );
                            else
                                $auto_discount = $AutoRS1[0]['auto_discount_amount'];
                            break;
                        }
                        else
                        {
                             $auto_discount = 0;
                        }
                    }
                 }
            }
            Session::put('ShoppingCart.'.$Flag.'AutoDiscount',NumberFormat($auto_discount));
            Session::put('ShoppingCart.'.$Flag.'AutoDiscountFlag',$discount_coupon_flag);
			$log['AutoDiscount'] = $auto_discount;
			$log['discount_coupon_flag'] = $discount_coupon_flag;
			addLog('ApplyAutoDiscountEnd',$log);
        }
		return NULL;
	}

	public function getSplitAllDiscountBlank($DiscountFlag='',$CartArr=array(),$Flag="Store")
	{
		$Cart = $CartArr;

		if(isset($DiscountFlag) && $DiscountFlag=="Auto")
		{
			$TotalItems = count($CartArr);
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.'.$Flag.'AutoItemWiseDiscout',0);
			}
		}
		if(isset($DiscountFlag) && $DiscountFlag=="Quantity")
		{
			$TotalItems = count($CartArr);
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.'.$Flag.'QuantityItemWiseDiscout',0);
			}
		}
		if(isset($DiscountFlag) && $DiscountFlag=="Coupon")
		{
			$TotalItems = count($CartArr);
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.'.$Flag.'CouponDisItemWiseDiscout',0);
			}
		}
		if(isset($DiscountFlag) && $DiscountFlag=="Reward")
		{
			$TotalItems = count($CartArr);
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.'.$Flag.'RewardItemWiseDiscout',0);
			}
		}
		if(isset($DiscountFlag) && $DiscountFlag=="Bogo")
		{
			$TotalItems = count($CartArr);
			for($p=0;$p<$TotalItems;$p++)
			{
				Session::put('ShoppingCart.Cart.'.$p.'.'.$Flag.'BogoItemWiseDiscout',0);
			}
		}
	}
	public function getSplitDealSubTotal($CartArr=array(),$Flag="Store")
	{
		 $tempCart = $CartArr;
		 $TotalDeal = 0;
		 for($a=0;$a<count($tempCart);$a++)
		 {
			if(isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]=='Yes')
			{
			  $TotalDeal = $TotalDeal + $tempCart[$a]["TotPrice"];
			}
		 }
		 return $TotalDeal;
	}

	public function GetSplitAllDiscounts($DiscountName='',$CartArr=array(),$Flag="Store")
	{
		$log['DiscountName'] = $DiscountName;
		addLog('GetAllDiscountStart',$log);
		$Discounts = [];

		If(Session::has('ShoppingCart.'.$Flag.'AutoDiscount') && Session::get('ShoppingCart.'.$Flag.'AutoDiscount') > 0)
			$Discounts['AutoDiscount'] = ['label' => 'Auto Discount', 'discount' => Session::get('ShoppingCart.'.$Flag.'AutoDiscount')];
		If(Session::has('ShoppingCart.'.$Flag.'YotpoRewardDiscount') && Session::get('ShoppingCart.'.$Flag.'YotpoRewardDiscount') > 0)
			$Discounts['YotpoRewardDiscount'] = ['label' => 'Reward Discount', 'discount' => Session::get('ShoppingCart.'.$Flag.'YotpoRewardDiscount'),'Ricon' => 'Yes', 'dataid' => 'YotpoRewardDiscount'];
		If(Session::has('ShoppingCart.'.$Flag.'QuantityDiscount') && Session::get('ShoppingCart.'.$Flag.'QuantityDiscount') > 0)
			$Discounts['QuantityDiscount'] = ['label' => 'Quantity Discount', 'discount' => Session::get('ShoppingCart.'.$Flag.'QuantityDiscount')];
		$CouponTotal = 0;
		If(Session::has('ShoppingCart.PromoCoupon.'.$Flag.'FirstCouponDiscount') && Session::get('ShoppingCart.PromoCoupon.'.$Flag.'FirstCouponDiscount') > 0)
			$CouponTotal += NumberFormat(Session::get('ShoppingCart.PromoCoupon.'.$Flag.'FirstCouponDiscount'));
		If(Session::has('ShoppingCart.PromoCoupon.'.$Flag.'SecondCouponDiscount') && Session::get('ShoppingCart.PromoCoupon.'.$Flag.'SecondCouponDiscount') > 0)
			$CouponTotal += NumberFormat(Session::get('ShoppingCart.PromoCoupon.'.$Flag.'SecondCouponDiscount'));
		if($CouponTotal > 0)
			Session::put('ShoppingCart.PromoCoupon.'.$Flag.'CouponDiscount',$CouponTotal);

		If(Session::has('ShoppingCart.PromoCoupon.'.$Flag.'CouponDiscount') && Session::get('ShoppingCart.PromoCoupon.'.$Flag.'CouponDiscount') > 0)
			$Discounts['CouponDiscount'] = ['label' => 'Coupon Discount', 'discount' => Session::get('ShoppingCart.PromoCoupon.'.$Flag.'CouponDiscount'),'Ricon' => 'Yes', 'dataid' => 'CouponDiscount'];
		If(Session::has('ShoppingCart.'.$Flag.'GiftCoupon.Value') && Session::get('ShoppingCart.'.$Flag.'GiftCoupon.Value') > 0)
		{
			$GiftCouponDiscount = Session::get('ShoppingCart.'.$Flag.'GiftCoupon.Value');
			$Discounts['GiftCoupon'] = ['label' => 'Gift Certificate Discount', 'discount' => $GiftCouponDiscount,'Ricon' => 'Yes', 'dataid' => 'GiftCoupon'];
		}
		If(Session::has('ShoppingCart.'.$Flag.'AutoReferDiscount') && Session::get('ShoppingCart.'.$Flag.'AutoReferDiscount') > 0)
			$Discounts['AutoReferDiscount'] = ['label' => 'Auto Refer Discount', 'discount' => Session::get('ShoppingCart.'.$Flag.'AutoReferDiscount')];
		If(Session::has('ShoppingCart.Reward_array.'.$Flag.'RewardDiscount') && Session::get('ShoppingCart.Reward_array.'.$Flag.'RewardDiscount') > 0)
			$Discounts['AutoRewardDiscount'] = ['label' => 'Reward Discount', 'discount' => Session::get('ShoppingCart.Reward_array.'.$Flag.'RewardDiscount')];
		If(Session::has('ShoppingCart.'.$Flag.'credit_limit_discount') && Session::get('ShoppingCart.'.$Flag.'credit_limit_discount') > 0)
			$Discounts['CreditLimitDiscount'] = ['label' => 'Credit Limit Discount', 'discount' => Session::get('ShoppingCart.'.$Flag.'credit_limit_discount')];
		If(Session::has('ShoppingCart.'.$Flag.'DogoDiscount') && Session::get('ShoppingCart.'.$Flag.'DogoDiscount') > 0)
			$Discounts['DogoDiscount'] = ['label' => 'Bogo Discount', 'discount' => Session::get('ShoppingCart.'.$Flag.'DogoDiscount')];
		if($DiscountName != '')
		{

			$log['Discounts'] = json_encode($Discounts);
			addLog('GetAllDiscount',$log);
			$DiscountDetail = 0;
			if(isset($Discounts[$DiscountName]))
				$DiscountDetail = NumberFormat($Discounts[$DiscountName]['discount']);
			return $DiscountDetail;
		} else {
			$TotalDiscount = array_sum(array_map('floatval', array_column($Discounts, 'discount')));
			$DiscountInfo  = ['Discounts' => $Discounts, 'TotalDiscount' => NumberFormat($TotalDiscount)];
			$log['DiscountInfo'] = json_encode($DiscountInfo);
			addLog('GetAllDiscount',$log);
			return $DiscountInfo;
		}
	}
	public function ApplySplitQuantityDiscount($CartArr=array(),$Flag="Store")
	{
		$pocketPerfumeCategory = $this->getPocketPerfumeCategory();
		$QuantityDiscount = 0;
		$NewSubTotal = 0;
		$QuantityDiscountItemWise = 0;

		$log['QuantityDiscount'] = $QuantityDiscount;
		$log['NewSubTotal'] = $NewSubTotal;
		$log['QuantityDiscountItemWise'] = $QuantityDiscountItemWise;
		$log['pocketPerfumeCategory'] = json_encode($pocketPerfumeCategory);
		addLog('ApplyQuantityDiscountStart',$log);

		if($CartArr && count($CartArr) > 0)
		{
			$this->getSplitAllDiscountBlank("Quantity",$CartArr,$Flag);
			$normaluser = Auth::user();
			if (Auth::guard('store')->check()) {
				$normaluser = Auth::guard('web')->user();
			}

			//if(Auth::user() && Session::get('eusertype') == "Wholesaler")
			if($normaluser && Session::get('eusertype') == "Wholesaler")
			{
				Session::put('ShoppingCart.QuantityDiscount',0);
				Session::put('ShoppingCart.'.$Flag.'QuantityDiscount',0);
				return null;
			}

			if(Session::has('ShoppingCart.'.$Flag.'SubTotal'))
				$NewSubTotal = NumberFormat(Session::get('ShoppingCart.'.$Flag.'SubTotal'));
			$GiftCertiTotal = 0;
			$GiftCertiCount = 0 ;
			if(Session::has('ShoppingCart.'.$Flag.'GiftCertiTotal'))
			{
				$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.'.$Flag.'GiftCertiTotal'));
				$GiftCertiCount = NumberFormat(Session::get('ShoppingCart.'.$Flag.'GiftCertiCount'));
			}
			$subTotal = $NewSubTotal - $GiftCertiTotal;
			$log['GiftCertiTotal'] = $GiftCertiTotal;
			$Cart = $CartArr;

			$TotalItem 	= Session::get('ShoppingCart.'.$Flag.'TotalItemInCart') - $GiftCertiCount;
			$QuantityDiscountFlag = '';
			if($subTotal <= 0 || $TotalItem <= 0)
			{
				Session::put('ShoppingCart.'.$Flag.'QuantityDiscount', 0);
				Session::put('ShoppingCart.'.$Flag.'QuantityDiscountFlag', '');
				$log['QuantityDiscount'] = '0';
				$log['QuantityDiscountFlag'] = '';
				addLog('ApplyQuantityDiscount',$log);
				return NULL;
			}
			$CouponCode = $this->GetAllCoupons('CouponCode');
			if($CouponCode != '')
			{
				$coupon_res = Coupon::select('quantitydiscount_flag')
								->where('coupon_number','=',$CouponCode)
								->where('status','=','1')
								->where('start_date','<=',DB::raw('curdate()'))
								->where('end_date','>=',DB::raw('curdate()'))
								->get();
				if($coupon_res && $coupon_res->count() > 0)
				{
					$log['coupon_res'] = json_encode($coupon_res);
					if($coupon_res[0]->quantitydiscount_flag == "No")
					{
						Session::put('ShoppingCart.'.$Flag.'QuantityDiscount', 0);
						Session::put('ShoppingCart.'.$Flag.'QuantityDiscountFlag', '');
						$log['QuantityDiscount_1'] = '0';
						$log['QuantityDiscountFlag_1'] = '';
						addLog('ApplyQuantityDiscount',$log);
						return NULL;
					}
				}
			}

			$QtyRS = QuantityDiscount::where('status','=','1')
						->where('start_date','<=',DB::raw('curdate()'))
						->where('end_date','>=',DB::raw('curdate()'))
						->where('quantity','<=',$TotalItem)
						->orderBy('quantity_discount_id','desc')
						->get();
			$TotalQuantityDiscoundRecords = $QtyRS->count();
			$TotalItems = count($CartArr);
			$TotalExcludePrice = 0;
			$log['QtyRS'] = json_encode($QtyRS);
			if($TotalQuantityDiscoundRecords > 0)
			{
			   $TotalQuantity = $QtyRS->count();
			   $SKURemoveArr = '';

			   for($i=0;$i<$TotalQuantity;$i++)
			   {
					$QuantityDiscountFlag = $QtyRS[$i]["discount_coupon_flag"];
					$ExcludeSKUListArr = array();
					$TotalExcludePrice = 0;
					$TotalQty = 0;
					if($TotalItems > 0 && $TotalQuantityDiscoundRecords > 0 && trim($QtyRS[$i]["exclude_sku"])!='')
					{
						$ExcludeSKUListArr = array();
						$ExcludeSKUListArr  = explode(",",$QtyRS[$i]["exclude_sku"]);
						$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
						$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');
						$TotalExcludePrice = 0;
						$log['ExcludeSKUListArr'] = json_encode($ExcludeSKUListArr);

						for($p=0;$p<$TotalItems;$p++)
						{
							if(in_array($Cart[$p]["SKU"],$ExcludeSKUListArr))
							{
								$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
							}

						}
						$log['TotalExcludePrice_1'] = json_encode($TotalExcludePrice);
					}

					if($TotalItems > 0 && $TotalQuantityDiscoundRecords > 0 && trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
					{
						for($p=0;$p<$TotalItems;$p++)
						{
							if(isset($Cart[$p]["CategoryID"]) && $Cart[$p]["CategoryID"] > 0 && in_array($Cart[$p]["CategoryID"],$pocketPerfumeCategory))
							{
								$TotalExcludePrice =  $TotalExcludePrice  + $Cart[$p]["TotPrice"];
							}

						}
						$log['TotalExcludePrice_2'] = json_encode($TotalExcludePrice);
					}

					if($QtyRS[$i]["orders"]=='0')
					{
						$QtySKU = trim($QtyRS[$i]['sku']);
						########### For Multiple SKU ###############
						$arr_QtySKU  = explode(",",$QtySKU);

						$arr_QtySKU  = array_unique(array_map('trim',$arr_QtySKU));
						$arr_QtySKU  = array_filter($arr_QtySKU, 'strlen');

						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;

						$log['arr_QtySKU'] = json_encode($arr_QtySKU);

						if(is_array($arr_QtySKU) and !empty($arr_QtySKU))
						{
							$tempCart  = $CartArr;
							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}
							$total_qty = 0;
							$total_price = 0;
							$TotalAmount =0;
							$TotalQty = 0;

							for($p=0;$p<$TotalItems;$p++)
							{
								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$p]["CategoryID"]) && $tempCart[$p]["CategoryID"] > 0 && in_array($tempCart[$p]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}
								$FreeGift = '';
								if(isset($tempCart[$p]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$p]["IS_Free_Gift"];

								if (in_array( $tempCart[$p]['SKU'] , $arr_QtySKU) && (isset($tempCart[$p]["IsDealProducts"]) && $tempCart[$p]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && !in_array($tempCart[$p]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$p]['SKU'] , $SKURemoveArrNew))
								{

									$TotalQty = $TotalQty + $Cart[$p]["Qty"];
								}
							}
							$log['TotalQty'] = json_encode($TotalQty);

							for ($a=0; $a<count($tempCart); $a++)
							{

								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}

								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];
								if(in_array($tempCart[$a]['SKU'] , $arr_QtySKU) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] ,$SKURemoveArrNew))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($QtyRS[$i]['type'] == 1 )
									{
									   if($TotalQty >= $QtyRS[$i]['quantity'])
										{
										   $QuantityDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $QtyRS[$i]['quantity_discount_amount']/100);
										   Session::put('ShoppingCart.Cart.'.$a.'.QuantityItemWiseDiscout',$QuantityDiscountItemWise);
										   $Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;
										}
									}
									$SKURemoveArr.= $tempCart[$a]['SKU'].",";
								}
							}
							if($QtyRS[$i]['type'] == 0 && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{

									if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
									{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}
									}

									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];
									if(in_array($tempCart[$a]['SKU'] , $arr_QtySKU) && $tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] ,$SKURemoveArrNew))
									{

									  if($TotalQty >= $QtyRS[$i]['quantity'])
										{
										$QuantityDiscountItemWise = (($QtyRS[$i]['quantity_discount_amount']*100)/$TotalAmount);
										$QuantityDiscountCal = (($tempCart[$a]['TotPrice']  * $QuantityDiscountItemWise)/100);
										Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout',$QuantityDiscountCal);
										}

									}
								}
							}
							$MatchNewQTYDiscount=  0;
							if($QuantityDiscount > 0)
							{
								$MatchNewQTYDiscount = $QuantityDiscount;
							}
							if($IS_Any_Matched > 0  && $total_qty >=$QtyRS[$i]['quantity'])
							{
								if($QtyRS[$i]['type'] == 1)
								{
									$QuantityDiscount = ($Matched_Item_Total * ($QtyRS[$i]['quantity_discount_amount']/100));
								}
								else
								{
									$QuantityDiscount = $QtyRS[$i]['quantity_discount_amount'];
								}
							}
						}
					}
					else if($QtyRS[$i]["orders"]=='1')
					{
						$QtySKU = trim($QtyRS[$i]['sku']);
						########### For Multiple SKU ###############
						$QtyCatID    	= trim($QtyRS[$i]['sku']); // Category IDS
						$arr_QtyCatID    = explode(",",$QtyCatID);

						$QuantityDiscount1 = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::select('category_id')->where('status','=','1')
											->whereIn('category_id',$arr_QtyCatID)->get();
						$arr_active_CatID = array();
						for($h=0;$h<count($Res_active_CatID);$h++)
						{
							$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
						}
						if(count($arr_active_CatID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = $CartArr;
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$ProdIds = ProductsCategory::select('products_id')->distinct()
										->whereIn('category_id',$arr_active_CatID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$cat_prod_id  = array();
							for ($a=0; $a< $ProdIds->count(); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							$TotalAmount=0;
							$TotalQty = 0;

							for($p=0;$p<$TotalItems;$p++)
							{
								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$p]["CategoryID"]) && $tempCart[$p]["CategoryID"] > 0 && in_array($tempCart[$p]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}
								$FreeGift = '';
								if(isset($tempCart[$p]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$p]["IS_Free_Gift"];

								if (in_array( $tempCart[$p]['ProductID'] , $cat_prod_id) && (isset($tempCart[$p]["IsDealProducts"]) && $tempCart[$p]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && !in_array($tempCart[$p]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$p]['SKU'] , $SKURemoveArrNew))
								{
									$TotalQty = $TotalQty + $Cart[$p]["Qty"];
								}
							}

							for ($a=0; $a<count($tempCart); $a++)
							{

								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}
								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];

								if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
								{
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$total_price = $total_price + ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($tempCart[$a]['Qty'] >= $QtyRS[$i]['quantity'] || $total_qty >= $QtyRS[$i]['quantity'])
									{

										if($QtyRS[$i]['type'] == 1 )
										{
											$total_percentage = true;
										}
										$found_cat = true;
										$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}

									if($TotalQty >= $QtyRS[$i]['quantity'])
									{
										if($QtyRS[$i]['type'] == 1 )
										{
											if(Session::has('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout') && Session::get('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout')=='')
											{
											$QuantityDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $QtyRS[$i]['quantity_discount_amount']/100);
											Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout',$QuantityDiscountItemWise);
											}
										}
										//$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}

								}
							}
							if($QtyRS[$i]['type'] == 0 && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{
									if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
									{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}
									}
									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];

									if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
									{

										if($TotalQty >= $QtyRS[$i]['quantity'])
										{

											$QuantityDiscountItemWise = (($QtyRS[$i]['quantity_discount_amount']*100)/$TotalAmount);
											$QuantityDiscountCal = (($tempCart[$a]['TotPrice']  * $QuantityDiscountItemWise)/100);
											Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout',$QuantityDiscountCal);
										}

									}
								}
							}
							if($total_percentage == true)
							{
								$QuantityDiscount1 = $total_price + $QuantityDiscount1;
							}
						}
						if($found_cat==true)
						{
							$QuantityDiscountFlag = $QtyRS[$i]["discount_coupon_flag"];

							$MatchNewQTYDiscount=  0;
							if($QuantityDiscount > 0)
							{
								$MatchNewQTYDiscount = $QuantityDiscount;
							}
							if($QtyRS[$i]['type'] == 1)
							{

								$QuantityDiscount = ($QuantityDiscount1  * $QtyRS[$i]['quantity_discount_amount']/100);
							}
							else
							{
								$QuantityDiscount = $QtyRS[$i]['quantity_discount_amount'];
							}

							if($QuantityDiscount > $MatchNewQTYDiscount)
							{
								$QuantityDiscount = $QuantityDiscount;
							}
							elseif($MatchNewQTYDiscount > $QuantityDiscount)
							{
								$QuantityDiscount = $MatchNewQTYDiscount;
							}
							$log['QuantityDiscount_1'] = json_encode($QuantityDiscount);
						}
				   }

				   else if($QtyRS[$i]["orders"]=='2')
				   {
						$QtySKU = trim($QtyRS[$i]['sku']);

						########### For Multiple SKU ###############

						$QtyBrandID    	= trim($QtyRS[$i]['sku']); // Category IDS
						$arr_QtyBrandID    = explode(",",$QtyBrandID);

						$QuantityDiscount1 = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')
												->whereIn('imanufactureid',$arr_QtyBrandID)->get();
						$arr_active_BrandID = array();
						for($h=0;$h<count($Res_active_BrandID);$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}
						if(count($arr_active_BrandID) > 0 )
						{
							## Get Cart Prod ID
							$tempCart  	    = $CartArr;
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}
							$log['temp_prod_id'] = json_encode($temp_prod_id);
							$ProdIds = Products::select('products_id')->distinct()
										->whereIn('imanufactureid',$arr_active_BrandID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$log['brand_prod_id'] = json_encode($brand_prod_id);
							$SKURemoveArrNew = [];
							if($SKURemoveArr!='')
							{
								$SKURemoveArrNew = explode(",",$SKURemoveArr);
								$SKURemoveArrNew = array_filter($SKURemoveArrNew);
								$SKURemoveArrNew = array_values($SKURemoveArrNew);
							}
							$log['SKURemoveArrNew'] = json_encode($SKURemoveArrNew);
							$total_qty = 0;
							$total_price = 0;
							$total_percentage = false;
							$TotalAmount =0;
							$TotalQty = 0;

							for($p=0;$p<$TotalItems;$p++)
							{
								if(trim($QtyRS[$i]["exclude_pocketperfume"])=='Yes')
								{
									if(isset($tempCart[$p]["CategoryID"]) && $tempCart[$p]["CategoryID"] > 0 && in_array($tempCart[$p]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}
								}
								$FreeGift = '';
								if(isset($tempCart[$p]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$p]["IS_Free_Gift"];

								if (in_array( $tempCart[$p]['ProductID'] , $brand_prod_id) && (isset($tempCart[$p]["IsDealProducts"]) && $tempCart[$p]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && !in_array($tempCart[$p]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$p]['SKU'] , $SKURemoveArrNew))
								{
									$TotalQty = $TotalQty + $Cart[$p]["Qty"];
								}

							}

							for ($a=0; $a<count($tempCart); $a++)
							{

								if($QtyRS[$i]["exclude_pocketperfume"]=='Yes')
								{
									if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
									{
										continue;
									}

								 }

								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];

								if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
								{

									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$total_price = $total_price + ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($tempCart[$a]['Qty'] >= $QtyRS[$i]['quantity'] || $total_qty >= $QtyRS[$i]['quantity'])
									{
										if($QtyRS[$i]['type'] == 1 )
										{

											$total_percentage = true;
										}
										$found_brand = true;
										$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}

									if($TotalQty >= $QtyRS[$i]['quantity'])
									{
										if($QtyRS[$i]['type'] == 1 )
										{
											if(Session::has('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout') && Session::get('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout')=='')
											{
											$QuantityDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $QtyRS[$i]['quantity_discount_amount']/100);
											Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout',$QuantityDiscountItemWise);
											}
										}
										//$SKURemoveArr.= $tempCart[$a]['SKU'].",";
									}

								}
							}
							if($QtyRS[$i]['type'] == 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{
									if($QtyRS[$i]["exclude_pocketperfume"]=='Yes')
									{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}

									 }
									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];

									if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && (isset($tempCart[$a]["IsDealProducts"]) && $tempCart[$a]["IsDealProducts"]!="Yes") && $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr) && !in_array($tempCart[$a]['SKU'] , $SKURemoveArrNew))
									{

										if($TotalQty >= $QtyRS[$i]['quantity'])
										{
											$QuantityDiscountItemWise = (($QtyRS[$i]['quantity_discount_amount']*100)/$TotalAmount);
											$QuantityDiscountCal = (($tempCart[$a]['TotPrice']  * $QuantityDiscountItemWise)/100);
											Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout',$QuantityDiscountCal);
										}
									}
								}

							}
							if($total_percentage == true)
							{
								$QuantityDiscount1 = $total_price + $QuantityDiscount1;
							}
						}

						if($found_brand==true)
						{
							$QuantityDiscountFlag = $QtyRS[$i]["discount_coupon_flag"];

							$MatchNewQTYDiscount=  0;
							if($QuantityDiscount > 0)
							{
								$MatchNewQTYDiscount = $QuantityDiscount;
							}
							if($QtyRS[$i]['type'] == 1)
							{
								 $QuantityDiscount = ($QuantityDiscount1  * $QtyRS[$i]['quantity_discount_amount']/100);
							}
							else
							{
								$QuantityDiscount = $QtyRS[$i]['quantity_discount_amount'];
							}

							if($QuantityDiscount > $MatchNewQTYDiscount)
							{
								$QuantityDiscount = $QuantityDiscount;
							}
							elseif($MatchNewQTYDiscount > $QuantityDiscount)
							{
								$QuantityDiscount = $MatchNewQTYDiscount;
							}
						}
				   }
				    else
				    {
					   $QtyRS1 = QuantityDiscount::where('status','=','1')
						->where('start_date','<=',DB::raw('curdate()'))
						->where('end_date','>=',DB::raw('curdate()'))
						->where('quantity','<=',$TotalItem)->where('orders','=','')
						->orderBy('quantity_discount_id')->limit(1)
						->get();
						$IS_Any_Matched 	= 0;
						$total_qty			= 0;
						$TotalAmount 		= 0;
						//echo "<pre>"; print_r($QtyRS1)
						if($QtyRS1 && $QtyRS1->count() > 0)
						{
							$tempCart  = $CartArr;;
							$subTotal = 0;
							for ($a=0; $a<count($tempCart); $a++)
							{
								if(trim($QtyRS1[0]['exclude_pocketperfume'])=='Yes')
								{
										if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 && in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
										{
											continue;
										}
								}
								$FreeGift = '';
								if(isset($tempCart[$a]["IS_Free_Gift"]))
									$FreeGift = $tempCart[$a]["IS_Free_Gift"];
								if($tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$total_qty = $total_qty + $tempCart[$a]['Qty'];
									$subTotal+=$tempCart[$a]['TotPrice'];
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($QtyRS1[0]['type'] == '1')
									{
									$QuantityDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * $QtyRS1[0]['quantity_discount_amount']/100);
									Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout',$QuantityDiscountItemWise);
									}
								}
							}
							if($QtyRS1[0]['type'] == '0' && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{
									if(trim($QtyRS1[0]['exclude_pocketperfume'])=='Yes')
									{
											if(isset($tempCart[$a]["CategoryID"]) && $tempCart[$a]["CategoryID"] > 0 &&  in_array($tempCart[$a]["CategoryID"],$pocketPerfumeCategory))
											{
												continue;
											}
									}
									$FreeGift = '';
									if(isset($tempCart[$a]["IS_Free_Gift"]))
										$FreeGift = $tempCart[$a]["IS_Free_Gift"];
									if($tempCart[$a]["IsDealProducts"]!="Yes" &&  $FreeGift!="Yes" && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
									{
										$QuantityDiscountItemWise = (($QtyRS1[0]['quantity_discount_amount']*100)/$TotalAmount);
										$QuantityDiscountCal = (($tempCart[$a]['TotPrice']  * $QuantityDiscountItemWise)/100);
										Session::put('ShoppingCart.Cart.'.$a.'.'.$Flag.'QuantityItemWiseDiscout',$QuantityDiscountCal);
									}
								}
							}
							if($IS_Any_Matched > 0  && $total_qty >=$QtyRS1[0]['quantity'])
							{
								if($QtyRS1[0]['type'] == '1')
								$QuantityDiscount = ( $subTotal * ($QtyRS1[0]['quantity_discount_amount']/100) );
								else
								$QuantityDiscount = $QtyRS1[0]['quantity_discount_amount'];
							}

							break;
						}
						else
						{
							$QuantityDiscount = 0;
						}
				   }

			   }
			}
			else
			{
				$QuantityDiscountFlag = '';
				$QuantityDiscount = 0;
			}
			Session::put('ShoppingCart.'.$Flag.'QuantityDiscount',NumberFormat($QuantityDiscount));
			Session::put('ShoppingCart.'.$Flag.'QuantityDiscountFlag',$QuantityDiscountFlag);
			$log['QuantityDiscount'] = $QuantityDiscount;
			$log['QuantityDiscountFlag'] = $QuantityDiscountFlag;
			addLog('ApplyQuantityDiscount',$log);
			return NULL;
		}
	}

	public function ApplySplitCouponDiscount($couponCode, $customer_id = NULL,$CartArr=array(),$Flag="Store")
	{
		$log['couponCode'] = $couponCode;
		addLog('ApplyCouponDiscountStart',$log);

		$error = 0;
		$CouponDiscount  = 0.0 ;
		$couponCode 	 = trim($couponCode);
		$customer_id 	 = (int)$customer_id;
		$FreeShippingFlg = false;
		$CouponDiscountItemWise = 0;
		$CartInfo = array();
		if($CartArr && is_array($CartArr))
		{
			$CartInfo 		 = $CartArr;
		}
		$TotalItems 	 = count($CartInfo);
		$is_loggedin = 0;

		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}

		$CouponQry = Coupon::where('coupon_number','=',$couponCode)
							->where('status','=','1')
							->where('start_date','<=',DB::raw('curdate()'))
							->where('end_date','>=',DB::raw('curdate()'));
		//if(Auth::user())
		if($normaluser)
		{
			$is_loggedin = 1;
			/*if(Auth::user()->eusertype !='')
				$CouponQry->where('coupon_user_type','=',Auth::user()->eusertype);
			else
				$CouponQry->where('coupon_user_type','=','Retailer');*/

			if($normaluser->eusertype !='')
				$CouponQry->where('coupon_user_type','=',$normaluser->eusertype);
			else
				$CouponQry->where('coupon_user_type','=','Retailer');
		} else {
			//$CouponQry->where('coupon_user_type','=','Retailer');
			//added during phoneorder in admin 15022024
			if(Session::has('isPhoneOrder') && Session::has('eusertype') && Session::get('eusertype')!=''){
				$eusertype = Session::get('eusertype');
				$CouponQry->where('coupon_user_type','=',$eusertype);
			} else {
				$CouponQry->where('coupon_user_type','=','Retailer');
			}
		}

		$CouponRS = $CouponQry->get();

		$log['CouponRS'] = json_encode($couponCode);
		addLog('ApplyCouponRecordSet',$log);

		//echo "<pre>"; print_r($CouponRS); exit;

		$CouponCode =Session::get('ShoppingCart.'.$Flag.'PromoCoupon.CouponCode');

		$UnsetCart = 0;

		if($CouponRS && $CouponRS->count() > 0 )
		{
			if($CouponRS[0]["source"]!= "Yotpo")
			{
				foreach($CartInfo as $i => $Cart)
				{
					if(isset($Cart["FreeGiftCoupon"]) && $Cart["FreeGiftCoupon"]=="Yes")
					{
						Session::forget('ShoppingCart.'.$Flag.'Cart.'.$i);
						$UnsetCart = 1;
					}

				}
			}
		}
		if($UnsetCart == 1)
		{
			$NewShoppingCart = array_values($CartArr);
			Session::put('ShoppingCart.'.$Flag.'Cart',$NewShoppingCart);
		}

        $CartInfo 		 = Session::get('ShoppingCart.'.$Flag.'Cart');
		$TotalItems 	 = count($CartInfo);

		$IsDeal="Yes";
		$TotalDealPrice = 0;

        $CartItem = "No";
		if($CouponRS && $CouponRS->count() > 0 )
		{
			foreach($CartInfo as $i => $Cart)
			{
				$IsGiftCertificateItem = $this->checkGiftCertificateItem('IsGiftCertificateItem',$Cart);

				if($IsGiftCertificateItem == "No")
				{
					$CartItem = "Yes";
				}

				if((isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="No") || (isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="Yes" && ($Cart["DealDiscountFlag"]=="Yes" ||  (isset($CouponRS[0]["dealdiscount_flag"]) && $CouponRS[0]["dealdiscount_flag"]=="Yes"))))
					$IsDeal = "No";

				if((isset($Cart["IsDealProducts"]) && $Cart["IsDealProducts"]=="Yes") && ($Cart["DealDiscountFlag"]=="No" || $Cart["DealDiscountFlag"]=='') && $CouponRS[0]["dealdiscount_flag"]=="No")
					$TotalDealPrice =  $TotalDealPrice  + $Cart["TotPrice"];

			}
			if($CartItem=="No")
			{
				if(Session::has('ShoppingCart.'.$Flag.'PromoCoupon'))
					Session::forget('ShoppingCart.'.$Flag.'PromoCoupon');
			    //if(Session::has('Niche_Fragrances_Membership'))
					//Session::forget('Niche_Fragrances_Membership');

				if(Session::has('ShoppingCart.'.$Flag.'YotpoRewardCode'))
					Session::forget('ShoppingCart.'.$Flag.'YotpoRewardCode');

				if(Session::has('ShoppingCart.'.$Flag.'YotpoRewardDiscount'))
					Session::forget('ShoppingCart.'.$Flag.'YotpoRewardDiscount');

				$CouponDiscount = 0;
				$couponCode='';
				$msg = "Coupon code does not apply to the item you have in your bag.";
				$error = 1;
				$Info = ['error' => $error, 'message' => $msg];
				addLog('ApplyCouponDiscount',$Info);
				return $Info;
			}
			if($IsDeal == "Yes")
			{
				if(Session::has('ShoppingCart.'.$Flag.'PromoCoupon'))
					Session::forget('ShoppingCart.'.$Flag.'PromoCoupon');
				//if(Session::has('Niche_Fragrances_Membership'))
					//Session::forget('Niche_Fragrances_Membership');
				$CouponDiscount = 0;
				$couponCode='';
				$msg = "Coupon code does not apply to the item you have in your bag.";
				$error = 1;
				$Info = ['error' => $error, 'message' => $msg];
				addLog('ApplyCouponDiscount',$Info);
				return $Info;
			}
			if(trim($couponCode) == '' )
				$CouponDiscount = 0;

			if($CouponRS && $CouponRS->count() > 0)
			{
				if($CouponRS[0]["autodiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.'.$Flag.'AutoDiscount',0.0);
					Session::put('ShoppingCart.'.$Flag.'AutoDiscountFlag', '');
				}
				if($CouponRS[0]["bogodiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.'.$Flag.'DogoDiscount', 0.0);
					Session::put('ShoppingCart.'.$Flag.'BogoDiscountFlag','');
				}
				if($CouponRS[0]["quantitydiscount_flag"]=='No')
				{
					Session::put('ShoppingCart.'.$Flag.'QuantityDiscount', 0.0);
					Session::put('ShoppingCart.'.$Flag.'QuantityDiscountFlag','');
				}

				if($CouponRS && $CouponRS->count() <= 0)
					Session::put('ShoppingCart.'.$Flag.'PromoCoupon.CouponCode','');

				if(Session::has('ShoppingCart.'.$Flag.'PromoCoupon.CouponCode') && Session::get('ShoppingCart.'.$Flag.'PromoCoupon.CouponCode') == '' && $CouponRS[0]["allow_free_gift_product"] == "Yes" && $CouponRS[0]["free_gift_product_value"] != '')
				{
					$this->SplitRemoveFreeGiftValueProduct($CouponRS[0]["free_gift_product_value"]);
					$this->SplitRemoveFreeGiftValueProduct($CouponRS[0]["freegift_product_sku"]);
				}
				if($CouponRS[0]['source']=="Yotpo")
				{
					$this->getSplitAllDiscountBlank("Reward",Session::get('ShoppingCart.'.$Flag.'Cart'),$Flag);
				}
				else
				{
					$this->getSplitAllDiscountBlank("Coupon",Session::get('ShoppingCart.'.$Flag.'Cart'),$Flag);
				}

			}

			$TotalExcludePrice = 0;
			$ExcludeSKUListArr = [];
			if($TotalItems > 0 && $CouponRS && $CouponRS->count() > 0 && trim($CouponRS[0]["exclude_sku"])!='')
			{
				$ExcludeSKUListArr = [];
				$ExcludeSKUListArr  = explode(",",$CouponRS[0]["exclude_sku"]);
				$ExcludeSKUListArr 	= array_unique(array_map('trim',$ExcludeSKUListArr));
				$ExcludeSKUListArr  = array_filter($ExcludeSKUListArr, 'strlen');

				foreach($CartInfo as $i => $Cart)
				{
					if(in_array($Cart["SKU"],$ExcludeSKUListArr))
						$TotalExcludePrice =  $TotalExcludePrice  + $Cart["TotPrice"];
				}
			}

			$GiftCertiTotal = 0;
			if(Session::has('ShoppingCart.'.$Flag.'GiftCertiTotal'))
				$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.'.$Flag.'GiftCertiTotal'));
			$SubTotal = Session::get('ShoppingCart.'.$Flag.'SubTotal');
			$subTotal = NumberFormat($SubTotal - $GiftCertiTotal - $TotalDealPrice - $TotalExcludePrice);

			$shippingCharge = 0;
		    if(isset($Flag) && $Flag=="Website")
		    {
				$shippingCharge = $this->GetShippingCharge();
			}
			$gc_certi_total = 0;
			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_gc_purchase'] == '0' && Session::has('ShoppingCart.'.$Flag.'GiftCertiTotal'))
				$gc_certi_total = Session::get('ShoppingCart.'.$Flag.'GiftCertiTotal');

			$SubTotal = Session::get('ShoppingCart.SubTotal');
			$GrandTotal = $SubTotal - $TotalDealPrice - $TotalExcludePrice;
			$GrandTotalSale = $SubTotal - $TotalDealPrice - $TotalExcludePrice;

			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['count_ship_tax'] == '1')
			{
				$TaxValue = 0;
				if(isset($Flag) && $Flag=="Website")
				{
					$TaxValue = $this->GetAllCharges('TaxValue');
				}
				$GrandTotal = ($GrandTotal - $gc_certi_total) + $shippingCharge + $TaxValue;
				$GrandTotalSale = ($GrandTotalSale  - $gc_certi_total) + $shippingCharge + $TaxValue;
			}else{
				$GrandTotal = $GrandTotal - $gc_certi_total;
				$GrandTotalSale = $GrandTotalSale - $gc_certi_total;
			}

			Session::put($Flag."count_ship_tax",0);
			Session::put($Flag."coupon_per",0);

			if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '1')
			{ // only one time use
				if($CouponRS[0]['source']=="Yotpo")
				{
				$sqlorder = Order::where('Second_coupon_id','=',trim($CouponRS[0]['coupon_number']))->where('status','!=','Declined')->get();
				}
				else
				{
				$sqlorder = Order::where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->where('status','!=','Declined')->get();
				}
				if($sqlorder && $sqlorder->count() > 0 )
					$switchCase = '';
				else
					$switchCase = $CouponRS[0]['orders'];
			}else if($CouponRS && $CouponRS->count() > 0 && $CouponRS[0]['is_once'] == '2' &&  Session::get('sess_icustomerid') != 0){
				// Once per customer

				if($CouponRS[0]['source']=="Yotpo")
				{
				$sqlorder = Order::where('customer_id','=',Session::get('sess_icustomerid'))->where('Second_coupon_id','=',trim($CouponRS[0]['coupon_number']))->where('status','!=','Declined')->get();
				}
				else
				{
				$sqlorder = Order::where('customer_id','=',Session::get('sess_icustomerid'))->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])->where('status','!=','Declined')->get();
				}
				if($sqlorder && $sqlorder->count() > 0 )
				{

					$switchCase = '';
				}else{
					if(Session::get('etype') == "G" && isset($Billing['email']) && $Billing['email']!='')
					{
						$Billing  = Session::get('ShoppingCart.BillingAddress');

						if($CouponRS[0]['source']=="Yotpo")
						{
						$sqlorder = Order::select('orders_id')->where('Second_coupon_id','=',trim($CouponRS[0]['coupon_number']))
									->where('bill_email','=',$Billing['email'])->get();
						}
						else
						{

						$sqlorder = Order::select('orders_id')->where('coupon_id','=',(int)$CouponRS[0]['coupon_id'])
									->where('bill_email','=',$Billing['email'])->get();
						}

						if($sqlorder && $sqlorder->count() > 0 )
							$switchCase = '';
						else
							$switchCase = $CouponRS[0]['orders'];
					}else{
						$switchCase = $CouponRS[0]['orders'];
					}
				}
			}else{
				$switchCase = $CouponRS[0]['orders'];
			}

			switch ($switchCase)
			{
				## On Order Amount
				case '0' :
					$tempsubTotal = $GrandTotal;
					$tempSaleTotal = $GrandTotalSale;
					$log['tempsubTotal'] = $tempsubTotal;
					$log['tempSaleTotal'] = $tempSaleTotal;
					// Added code on 17 July 2012
					if($CouponRS[0]['count_ship_tax']=='1'){
						// Added by CK on 7th Feb, 2012 for Sale Item Coupon
						$tempSaleTotal=$tempSaleTotal;
						Session::put("count_ship_tax",1);
					}else{
						$tempsubTotal = $SubTotal;
						$tempSaleTotal = Session::get('ShoppingCart.'.$Flag.'SubTotal') - $gc_certi_total - $TotalDealPrice - $TotalExcludePrice;
						$tempSaleTotal = $tempSaleTotal;
					}

					if($CouponRS[0]['discount']<=0 && isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='')
					{

						$CouponDiscount = 0;
					}
					if($tempSaleTotal >= $CouponRS[0]['order_amount'])
					{
						if($CouponRS[0]['type'] == 1 )
							$CouponDiscount = ( $tempSaleTotal * ($CouponRS[0]['discount']/100) );
						else
							$CouponDiscount = $CouponRS[0]['discount'];
					}
					else
					{

						$msg = "Coupon code does not apply to the item you have in your bag.";
						$error = 1;
						$log['msg'] = $msg;
					}

					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
						Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}

					if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"] != '' && $error!=1)
					{
							$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);
					}
					$TotalAmount = 0;

					$tempCart  = Session::get('ShoppingCart.'.$Flag.'Cart');

						for ($a=0; $a<count($tempCart); $a++)
						{
							if(($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'],$ExcludeSKUListArr))
							{

								$TotalAmount =  $TotalAmount + $tempCart[$a]['TotPrice'];
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * ($CouponRS[0]['discount']/100));

									if($CouponRS[0]['source']=="Yotpo")
									{
										Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountItemWise);
									}
									else
									{
										Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountItemWise);
									}
								}

							}
						}

					if($CouponRS[0]['type'] == 0 && $TotalAmount > 0)
					{
						$tempCart  = Session::get('ShoppingCart.'.$Flag.'Cart');

						for ($a=0; $a<count($tempCart); $a++)
						{
							if(($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'],$ExcludeSKUListArr))
							{
								$CouponDiscountItemWise = (($CouponRS[0]['discount']*100)/$TotalAmount);
								$CouponDiscountCal = (($tempCart[$a]['TotPrice']  * $CouponDiscountItemWise)/100);

								if($CouponRS[0]['source']=="Yotpo")
								{

								Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountCal);

								}
								else
								{
								Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountCal);
								}

							}
						}
					}
					addLog('ApplyCouponOrderAmount',$log);
					break;
				## On Product SKU
				case '1' :
					$CouponSKU = trim($CouponRS[0]['sku']);
					########### For Multiple SKU ###############
					$arr_CouponSKU  = explode(",",$CouponSKU);
					$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
					$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');

					$Matched_Item_Total = 0;
					$IS_Any_Matched 	= 0;
					$TotalAmount 		= 0;
					if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
					{
						$tempCart  = Session::get('ShoppingCart.'.$Flag.'Cart');
						for ($a=0; $a<count($tempCart); $a++)
						{
							if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
							{
								$IS_Any_Matched = $IS_Any_Matched+1;
								$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
								if($CouponRS[0]['type'] == 1 )
								{
									$CouponDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * ($CouponRS[0]['discount']/100));
									if($CouponRS[0]['source']=="Yotpo")
									{
										Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountItemWise);
									}
									else
									{
										Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountItemWise);
									}
									$Matched_Item_Total = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+ $Matched_Item_Total;
								}
							}
						}
						if($CouponRS[0]['type'] == 0 && $TotalAmount > 0)
						{
							for ($a=0; $a<count($tempCart); $a++)
							{
								if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{

										$CouponDiscountItemWise = (($CouponRS[0]['discount']*100)/$TotalAmount);
										$CouponDiscountCal = (($tempCart[$a]['TotPrice']  * $CouponDiscountItemWise)/100);
										if($CouponRS[0]['source']=="Yotpo")
										{
											Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountCal);
										}
										else
										{
											Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountCal);
										}
								}
							}
						}
					}
					if($IS_Any_Matched >0 )
					{
						if($CouponRS[0]["count_ship_tax"]=='1' && isset($Flag) && $Flag=='Website')
						{
							if($CouponRS[0]['type'] == 1 )
							{
								if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
									$Matched_Item_Total = $Matched_Item_Total + Session::get('ShoppingCart.TaxValue');
								if($shippingCharge > 0)
									$Matched_Item_Total = $Matched_Item_Total + $shippingCharge;
							}
						}
						if($CouponRS[0]["count_gc_purchase"]=='1')
						{
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.'.$Flag.'GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.'.$Flag.'GiftCertiTotal'));
							$Matched_Item_Total = $Matched_Item_Total + $GiftCertiTotal;
						}
						if($CouponRS[0]['discount']<=0 && isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='')
						{
							$CouponDiscount = 0;
						}
						else if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
							}
						}
						elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
						{
							if($CouponRS[0]['type'] == 1)
							{
								$CouponDiscount = ($Matched_Item_Total * ($CouponRS[0]['discount']/100));
							}
							else
							{
								 $CouponDiscount = ($CouponRS[0]['discount'] * $IS_Any_Matched);
							}
						}
						else
						{
							$CouponDiscount = 0;
							$msg = "Coupon code is invalid or does not exists.";
							$error = 1;
						}
					}
					else
					{
						$msg = "Coupon code is invalid or does not exists.";
						$error = 1;
					}
					if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
					{
						Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping', 'Yes');
						Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
						Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
						$FreeShippingFlg = true;
					}
					if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='' && $error!=1)
					{

						$this->SplitFreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"],'',$Flag);
					}
					addLog('ApplyCouponProductSKU');
					####################################################
					break;
				case '7' :
						$CouponSKU = trim($CouponRS[0]['sku']);

						$CouponSKU = unserialize($CouponSKU);
						$arr_CouponSKU = array();
						$arr_CouponDiscount = array();
						for($d=0;$d<count($CouponSKU);$d++)
						{
							if($CouponSKU[$d]["sku"]!='')
							{
								$arr_CouponSKU[] = $CouponSKU[$d]["sku"];
								$arr_CouponDiscount[$CouponSKU[$d]["sku"]] = $CouponSKU[$d]["discount"];
							}
						}
						########### For Multiple SKU ###############
						$arr_CouponSKU 	= array_unique(array_map('trim',$arr_CouponSKU));
						$arr_CouponSKU  = array_filter($arr_CouponSKU, 'strlen');
					//	echo "<pre>"; print_r($arr_CouponSKU); exit;

						$Matched_Item_Total = 0;
						$IS_Any_Matched 	= 0;
						$CouponDiscountCalculate	= 0;
						$CouponDiscount = 0;
						$TotalAmount =0;
						if(is_array($arr_CouponSKU) and !empty($arr_CouponSKU))
						{
							$tempCart  = Session::get('ShoppingCart.'.$Flag.'Cart');
							for ($a=0; $a<count($tempCart); $a++)
							{
								if(in_array( $tempCart[$a]['SKU'] , $arr_CouponSKU) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$IS_Any_Matched = $IS_Any_Matched+1;
									$Current_Item_Total  = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']);
									$Matched_Item_Total = $Current_Item_Total + $Matched_Item_Total;

									$CouponDiscountCalculate = ($Current_Item_Total *($arr_CouponDiscount[$tempCart[$a]['SKU']] /100));
									$CouponDiscountCalculate = NumberFormat($CouponDiscountCalculate);
									$CouponDiscount = $CouponDiscount + $CouponDiscountCalculate;
									//item wise discount for cj
									$tempCart[$a]['ItemWiseCouponDiscount_CJ'] = $CouponDiscountCalculate;
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									//item wise discount for cj
									if($CouponRS[0]['source']=="Yotpo")
									{
										$tempCart[$a]['RewardItemWiseDiscout']  = $CouponDiscountCalculate;
									}
									else
									{
										$tempCart[$a]['CouponDisItemWiseDiscout']  = $CouponDiscountCalculate;
									}
								}
							}
							Session::put('ShoppingCart.'.$Flag.'Cart',$tempCart);
						}

						if($IS_Any_Matched >0 )
						{
							if($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
							{
								$CouponDiscount = $CouponDiscount;
							}
							elseif($Matched_Item_Total >= $CouponRS[0]['minimum_order_amount'])
							{
								$CouponDiscount = $CouponDiscount;
							}
							else
							{
								$CouponDiscount = 0;
								$msg = "Coupon code is invalid or does not exists.";
								$error = 1;
							}
						}
						else
						{
							$msg = "Coupon code is invalid or does not exists.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0)
						{
							Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}

						if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= ''  && $error!=1)
						{
							$this->SplitFreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"],'',$Flag);
						}
						addLog('ApplyCouponCase7');
						####################################################
						break;
				## On Product Brand
				case '2' :
						break;
				## On Product Category
				case '3' :
						$CouponCatID    	= trim($CouponRS[0]['sku']); // Category IDS
						$arr_CouponCatID    = explode(",",$CouponCatID);

						$CouponDiscount = 0;
						$found_cat = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_CatID = Category::where('status','=','1')->whereIn('category_id',$arr_CouponCatID)->get();
						$arr_active_CatID = array();
						if($Res_active_CatID && $Res_active_CatID->count() > 0)
						{
							for($h=0;$h<$Res_active_CatID->count();$h++)
							{
								$arr_active_CatID[] = $Res_active_CatID[$h]['category_id'];
							}
						}

						if(count($arr_active_CatID) > 0 )
						{
							$log['active_cat_id'] = json_encode($arr_active_CatID);
							## Get Cart Prod ID
							$tempCart  	    = Session::get('ShoppingCart.'.$Flag.'Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}

							$ProdIds = ProductsCategory::select('products_id')->distinct()
										->whereIn('category_id',$arr_active_CatID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$cat_prod_id  = array();
							for ($a=0; $a < $ProdIds->count(); $a++)
							{
								$cat_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$log['cat_prod_id'] = json_encode($cat_prod_id);
							$TotalAmount = 0;
							for ($a=0; $a<count($tempCart); $a++)
							{
								if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($CouponRS[0]['type'] == 1 )
									{
										$CouponDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * ($CouponRS[0]['discount']/100));
										if($CouponRS[0]['source']=="Yotpo")
										{
											Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountItemWise);

										}
										else
										{
											Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountItemWise);
										}
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty']) + $CouponDiscount;
									}
									$found_cat = true; // make true if category match
									$log['found_cat'] = json_encode($found_cat);
								}
							}
							if($CouponRS[0]['type'] == 0 && $TotalAmount > 0)
							{
								for ($a=0; $a<count($tempCart); $a++)
								{
									if (in_array( $tempCart[$a]['ProductID'] , $cat_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
									{
										$CouponDiscountItemWise = (($CouponRS[0]['discount']*100)/$TotalAmount);
										$CouponDiscountCal = (($tempCart[$a]['TotPrice']  * $CouponDiscountItemWise)/100);

											if($CouponRS[0]['source']=="Yotpo")
											{
											 Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountCal);
											}
											else
											{
											 Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountCal);
											}

									}
								}
							}
						}
						else
						{
							$CouponDiscount = 0;
						}

						if($found_cat==true)
						{

							if($CouponRS[0]["count_ship_tax"]=='1' && $Flag=="Website")
							{
								if($CouponRS[0]['type'] == 1 )
								{
									if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
										$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
									if($shippingCharge > 0)
										$CouponDiscount  = $CouponDiscount  + $shippingCharge;
								}
						  }
						  if($CouponRS[0]["count_gc_purchase"]=='1')
						  {
								$GiftCertiTotal = 0;
								if(Session::has('ShoppingCart.'.$Flag.'GiftCertiTotal'))
									$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.'.$Flag.'GiftCertiTotal'));
								$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
						  }
						  if($CouponRS[0]['discount']<=0 && isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='')
						  {
							$CouponDiscount = 0;
						  }
						  elseif($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
						  {
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
						  }
						  elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
						  {
							  if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
						  }
						  else
						  {
							  $CouponDiscount = 0;
							  $msg = "Coupon code is invalid or does not exists.";
							  $error = 1;
						  }
						}
						if($found_cat==false)
						{
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
						{
							Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
						}
						/*
						if($CouponRS[0]["allow_free_gift_product"]=="Yes" && $CouponRS[0]["free_gift_product_value"]!='' && $CouponDiscount > 0 && $found_cat==true)
						{
							$this->FreeGiftInsertProductValue($CouponRS[0]["free_gift_product_value"]);
						}
						*/
						if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='' && $found_cat==true)
						{
								$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"],'',$Flag);
						}
						addLog('ApplyDiscountProductCategory',$log);
						break;

				case '5' :
							break;

				## On Product Brand
				case '6' :
						$CouponBrandID    	= trim($CouponRS[0]['sku']); // Brand IDS
						$arr_CouponBrandID  = explode(",",$CouponBrandID);

						$CouponDiscount = 0;
						$found_brand = false; // Use for if coupon valid but category not found in cart;

						## Get Active Cat ID
						$Res_active_BrandID = Manufacture::where('status','=','1')
												->whereIn('imanufactureid',$arr_CouponBrandID)->get();
						$arr_active_BrandID = array();
						for($h=0;$h<count($Res_active_BrandID);$h++)
						{
							$arr_active_BrandID[] = $Res_active_BrandID[$h]['imanufactureid'];
						}
						$log['arr_active_BrandID'] = json_encode($arr_active_BrandID);
						if(count($arr_active_BrandID) > 0 )
						{
							$log['arr_active_BrandID'] = json_encode($arr_active_BrandID);
							## Get Cart Prod ID
							$tempCart = Session::get('ShoppingCart.'.$Flag.'Cart');
							$temp_prod_id   = array();

							for ($a=0; $a<count($tempCart); $a++)
							{
								if(isset($tempCart[$a]['IS_Free_Gift']) && $tempCart[$a]['IS_Free_Gift']=="Yes")
								{
									continue;
								}

								$temp_prod_id[$a] = $tempCart[$a]['ProductID'];
							}

							$ProdIds = Products::select('products_id')->distinct()
										->whereIn('imanufactureid',$arr_active_BrandID)
										->whereIn('products_id',$temp_prod_id)
										->get();
							$brand_prod_id  = array();
							for ($a=0; $a<count($ProdIds); $a++)
							{
								$brand_prod_id[$a] = $ProdIds[$a]['products_id'];
							}
							$log['brand_prod_id'] = json_encode($brand_prod_id);
							$TotalAmount = 0;

							for ($a=0; $a<count($tempCart); $a++)
							{
								if(isset($tempCart[$a]['IS_Free_Gift']) && $tempCart[$a]['IS_Free_Gift']=="Yes")
								{
									continue;
								}
								if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
								{
									$TotalAmount = $TotalAmount + $tempCart[$a]['TotPrice'];
									if($CouponRS[0]['type'] == 1 )
									{
										$CouponDiscountItemWise = (($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])  * ($CouponRS[0]['discount']/100));
										if($CouponRS[0]['source']=="Yotpo")
										{
											if(empty(Session::get('ShoppingCart.'.$Flag.'YotpoRewardDiscount')) || Session::get('ShoppingCart.'.$Flag.'YotpoRewardDiscount') <= 0)
											{
												Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountItemWise);
											}
										}
										else
										{
											Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountItemWise);
										}
										$CouponDiscount = ($tempCart[$a]['Price'] * $tempCart[$a]['Qty'])+$CouponDiscount;
									}
									else
									{
										$CouponDiscount = $CouponRS[0]['discount']  ;
									}
									$found_brand = true; // make true if category match
								}
							}
							if($CouponRS[0]['type'] == 0 && $TotalAmount > 0)
							{

								for ($a=0; $a<count($tempCart); $a++)
								{
									if(isset($tempCart[$a]['IS_Free_Gift']) && $tempCart[$a]['IS_Free_Gift']=="Yes")
									{
										continue;
									}

									if (in_array( $tempCart[$a]['ProductID'] , $brand_prod_id) && ($tempCart[$a]["IsDealProducts"]!="Yes" || ($tempCart[$a]["IsDealProducts"]=="Yes" && ($tempCart[$a]["DealDiscountFlag"]=="Yes" || $CouponRS[0]["dealdiscount_flag"]=="Yes"))) && !in_array($tempCart[$a]['SKU'] , $ExcludeSKUListArr))
									{

										$CouponDiscountItemWise = (($CouponRS[0]['discount']*100)/$TotalAmount);
										$CouponDiscountCal = (($tempCart[$a]['TotPrice']  * $CouponDiscountItemWise)/100);
										if($CouponRS[0]['source']=="Yotpo")
										{
										Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.RewardItemWiseDiscout',$CouponDiscountCal);
										}
										else
										{
										Session::put('ShoppingCart.'.$Flag.'Cart.'.$a.'.CouponDisItemWiseDiscout',$CouponDiscountCal);
										}

									}
								}

							}
						}
						else
						{
							$CouponDiscount = 0;
						}

						if($found_brand==true)
						{
							$log['found_brand'] = json_encode($found_brand);
						  if($CouponRS[0]["count_ship_tax"]=='1' && $Flag == "Website")
						  {
							if($CouponRS[0]['type'] == 1 )
							{
								if(Session::has('ShoppingCart.TaxValue') && Session::get('ShoppingCart.TaxValue') > 0)
									$CouponDiscount  = $CouponDiscount  + Session::has('ShoppingCart.TaxValue');
								if($shippingCharge > 0)
									$CouponDiscount  = $CouponDiscount  + $shippingCharge;
							}
						  }
						  if($CouponRS[0]["count_gc_purchase"]=='1')
						  {
							$GiftCertiTotal = 0;
							if(Session::has('ShoppingCart.'.$Flag.'GiftCertiTotal'))
								$GiftCertiTotal = NumberFormat(Session::get('ShoppingCart.'.$Flag.'GiftCertiTotal'));
							$CouponDiscount  = $CouponDiscount  + $GiftCertiTotal;
						  }

						    if($CouponRS[0]['discount']<=0 && isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!='')
							{
							$CouponDiscount = 0;
						    }
							elseif($CouponRS[0]['minimum_order_amount']==0.00 || $CouponRS[0]['minimum_order_amount']==0)
							{
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
							}
							elseif($CouponDiscount >= $CouponRS[0]['minimum_order_amount'])
							{
								if($CouponRS[0]['type'] == 1)
								{
									$CouponDiscount = ($CouponDiscount  * ($CouponRS[0]['discount']/100));
								}
								else
								{
									 $CouponDiscount = $CouponRS[0]['discount'];
								}
							}
							else
							{
							  $CouponDiscount = 0;
							  $msg = "Coupon code is invalid or does not exists.";
							  $error = 1;
							}

						}

						if($found_brand==false)
						{
							$log['found_brand'] = json_encode($found_brand);
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$log['msg'] = $msg;
							$error = 1;
						}

						if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='' && $CouponDiscount > 0 && $found_brand==true)
						{
							Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
							Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeID',explode(",",$CouponRS[0]["free_shipping_value"]));
							Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
							$FreeShippingFlg = true;
							$log['FreeShippingFlg'] = json_encode($FreeShippingFlg);
						}
						if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '' && $found_brand==true)
						{
						    $this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"],'',$Flag);
						}
						addLog('ApplyDiscountProductBrand',$log);
						break;

				## For Free Shipping
				case '4' :
						$CouponDiscount = 0;
						$Total_Item_count_val  = Session::get('ShoppingCart.'.$Flag.'TotalItemInCart');

						if($CouponRS[0]['minimum_order_amount'] == 0.00 ||  $CouponRS[0]['minimum_order_amount'] == 0)
						{
							if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}

								if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '')
								{
										$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"]);

								}
								$FreeShippingFlg = true;
							}
							else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingModeID',$ShippingID);

									if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
									{
										Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
										Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
										Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
										$FreeShippingFlg = true;
									}

									if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '')
									{
											$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"],'',$Flag);

									}
									$FreeShippingFlg = true;
							}
							else
							{
								$FreeShippingFlg = false;
							}
					   }

					  elseif(Session::get('ShoppingCart.'.$Flag.'SubTotal') >= $CouponRS[0]['minimum_order_amount'])
						{
							if($Total_Item_count_val >= $CouponRS[0]['total_free_shipping'])
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '')
								{
									$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"],'',$Flag);
								}
								$FreeShippingFlg = true;
							}
							else if($Total_Item_count_val==0 ||$Total_Item_count_val =='')
							{
								$ShippingID     = trim($CouponRS[0]['sku']); // Shipping method id
								Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
								Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingModeID',$ShippingID);

								if($CouponRS[0]["allow_free_shipping"]=="Yes" && $CouponRS[0]["free_shipping_value"]!='')
								{
									Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','Yes');
									Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShippingCouponModeID', explode(",",$CouponRS[0]["free_shipping_value"]));
									Session::put('ShoppingCart.PromoCoupon.'.$Flag.'FreeShippingCouponModeIDFlag',"Yes");
									$FreeShippingFlg = true;
								}
								if(isset($CouponRS[0]["freegift_product_sku"]) && $CouponRS[0]["freegift_product_sku"]!= '')
								{
									$this->FreeGiftInsertWithCoupon($CouponRS[0]["freegift_product_sku"],'',$Flag);
								}
								$FreeShippingFlg = true;
							}
							else
							{
								$FreeShippingFlg = false;
							}
					   }
					   else
					   {
							$msg = "Coupon code does not apply to the item you have in your bag.";
							$error = 1;
							$FreeShippingFlg = false;
					   }
					   addLog('ApplyDiscountFreeShipping',$log);
					break;
				default :
						$CouponDiscount = 0;
						$couponCode='';
						$msg = "Coupon code does not apply to the item you have in your bag.";
						$error = 1;
						break;
			}
			if($FreeShippingFlg==false)
			{
				Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FreeShipping','No');
			}
			$CouponDiscount = NumberFormat($CouponDiscount);
			$msg='';

			if(isset($CouponRS[0]['source']) && $CouponRS[0]['source'] == "Yotpo" && strtolower(Session::get('eusertype') ?? '') == "retailer" && Session::get('sess_icustomerid') > 0)
			{
				if(!empty(Session::get('ShoppingCart.'.$Flag.'YotpoRewardDiscount')) && Session::get('ShoppingCart.YotpoRewardDiscount') > 0)
				{
					Session::forget('ShoppingCart.'.$Flag.'YotpoRewardCode');
					Session::forget('ShoppingCart.'.$Flag.'YotpoRewardDiscount');
					Session::put('ShoppingCart.'.$Flag.'YotpoRewardCode',$couponCode);
					Session::put('ShoppingCart.'.$Flag.'YotpoRewardDiscount',$CouponDiscount);
					//$msg = "Remove existing reward discount.";
					//$error = 1;
				}
				else
				{
					if(isset($CouponDiscount) && $CouponDiscount > 0 && strtolower(Session::get('eusertype') ?? '') == "retailer" && Session::get('sess_icustomerid') > 0)
					{
						Session::put('ShoppingCart.'.$Flag.'YotpoRewardCode',$couponCode);
						Session::put('ShoppingCart.'.$Flag.'YotpoRewardDiscount',$CouponDiscount);
						$msg = "Reward discount code applied.";
					}
					else
					{

						Session::forget('ShoppingCart.'.$Flag.'YotpoRewardCode');
						Session::forget('ShoppingCart.'.$Flag.'YotpoRewardDiscount');
						$msg = "Reward discount code not found.";
					}
				}
			}
			else
			{
			if($CouponDiscount > 0 or $FreeShippingFlg==true)
			{
				if($CouponRS[0]['source'] != "Yotpo")
				{
					Session::put('ShoppingCart.'.$Flag.'PromoCoupon.CouponCode',$couponCode);
					Session::put('ShoppingCart.'.$Flag.'Coupon_Detail_CJ.CouponCode',$couponCode);
					Session::put('ShoppingCart.'.$Flag.'Coupon_Detail_CJ.orders',$CouponRS[0]['orders']);
				}
			}
			else
			{
				$CartInfoVal 		 = Session::get('ShoppingCart.'.$Flag.'Cart');
				$FreeGiftCouponVal = "No";
				foreach($CartInfoVal as $i => $Cart)
				{
					if(isset($Cart["FreeGiftCoupon"]) && $Cart["FreeGiftCoupon"]=="Yes")
					{
						$FreeGiftCouponVal = "Yes";
						break;
					}
				}

				if($FreeGiftCouponVal=="Yes")
				{
					Session::put('ShoppingCart.'.$Flag.'PromoCoupon.CouponCode',$couponCode);
					Session::put('ShoppingCart.'.$Flag.'Coupon_Detail_CJ.CouponCode',$couponCode);
					Session::put('ShoppingCart.'.$Flag.'Coupon_Detail_CJ.orders',$CouponRS[0]['orders']);
				}

				if($FreeGiftCouponVal == "No")
				{
					if(Session::has('ShoppingCart.'.$Flag.'PromoCoupon'))
						Session::forget('ShoppingCart.'.$Flag.'PromoCoupon');
					if(Session::has('Niche_Fragrances_Membership'))
						Session::forget('Niche_Fragrances_Membership');
				}
				//Session::put('ShoppingCart.PromoCoupon.CouponCode','');
			}

			if($CouponDiscount > 0 && $CouponRS[0]['source'] != "Yotpo")
			{
				Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FirstCouponDiscount',$CouponDiscount);
			}
			else
			{
				Session::put('ShoppingCart.'.$Flag.'PromoCoupon.CouponDiscount',0);
				Session::put('ShoppingCart.'.$Flag.'PromoCoupon.FirstCouponDiscount',0);
			}
			if(Session::get('ShoppingCart.'.$Flag.'PromoCoupon.CouponCode') !='' && $CouponRS[0]['source'] != "Yotpo")
			{
				$msg = "Coupon code applied successfully.";
			}
			else
			{
				$error = 1;
				$msg = "Invalid Coupon Code.";
				$log['message'] = $msg;
				addLog('ApplyDiscountInvalidCoupon',$log);
				if($is_loggedin == 0 && $CouponRS[0]['source'] == "Yotpo"){
					$error = 1;
					$msg = "Please login or create an account with Maxaroma to use the Referral Coupon code";
					$log['message'] = $msg;
					addLog('ApplyDiscountInvalidYotpoCoupon',$log);
				}
			}
		}
		} else {
			$error = 1;
			$msg = "Invalid Coupon Code.";
			$log['message'] = $msg;
			addLog('ApplyDiscountInvalidCoupon',$log);
		}

		if($CouponRS->count() <= 0)
		{
			if(Session::has('ShoppingCart.'.$Flag.'PromoCoupon'))
                Session::forget('ShoppingCart.'.$Flag.'PromoCoupon');
            if(Session::has('Niche_Fragrances_Membership'))
                Session::forget('Niche_Fragrances_Membership');
		}
			$Info = ['error' => $error, 'message' => $msg];
			addLog('ApplyCouponDiscount',$Info);
			return $Info;
	}

	public function SplitRemoveFreeGiftValueProduct($sku,$CartArr=array(),$Flag="Store")
	{
		if(Session::has('ShoppingCart.'.$Flag.'Cart') && count(Session::get('ShoppingCart.'.$Flag.'Cart')))
		{
			$count = count(Session::get('ShoppingCart.'.$Flag.'Cart'));
			$TempCart = array();
			$CartInfo = Session::get('ShoppingCart.'.$Flag.'Cart');
			foreach($CartInfo as $a => $Cart)
			{
				if($Cart['SKU'] == $sku)
				{
					if(isset($Cart["IS_Free_Gift"]) && $Cart['IS_Free_Gift']=="Yes")
						unset($Cart);
				}else if($Cart['SKU']==""){
					unset($Cart);
				}else{
					$TempCart[] = $Cart;
				}
			}
			Session::put('ShoppingCart.'.$Flag.'Cart',$TempCart);
			$this->SplitCalculateSubTotal($StoreOrderArr,$Flag);
		}
	}
	public function SplitFreeGiftInsertWithCoupon($products_sku="",$type="",$Flag="Store")
	{
		$OutofStockMsg="";
		if(Session::has('ShoppingCart.'.$Flag.'Cart') && count(Session::get('ShoppingCart.'.$Flag.'Cart')) > 0)
		{
			$count = count (Session::get('ShoppingCart.'.$Flag.'Cart'));
			$Cart = Session::get('ShoppingCart.'.$Flag.'Cart');
			$Cart = array_values($Cart);
			for($a=0; $a<$count; $a++)
			{
				if(isset($Cart[$a]["IS_Free_Gift"]) && $Cart[$a]['IS_Free_Gift']=="Yes")
				{
				  unset($Cart[$a]);
				}
			}

			$Cart = array_values($Cart);
			Session::put('ShoppingCart.'.$Flag.'Cart',$Cart);

			if($type == 'CouponRemove')
				return true;

			$FreeGiftProd = Products::where('sku','=',$products_sku)->where('status','=','1')->get();
			if($FreeGiftProd && $FreeGiftProd->count() > 0)
			{
				$free_gift_res = $this->SetProduct($FreeGiftProd[0]);

				if($free_gift_res->current_stock > 0 || ($free_gift_res->cosmo_current_stock > 0 && $free_gift_res->cosmo_sku!='') || ($free_gift_res->nandansons_current_stock > 0 && $free_gift_res->nandansons_sku!='')  || ($free_gift_res->pca_current_stock > 0 && $free_gift_res->pca_sku!=''))
				{
					if(file_exists(config('global.PRD_THUMB_IMG_PATH').$free_gift_res->image) && !empty($free_gift_res->image))
						$thumb_image = config('global.PRD_THUMB_IMG_URL').$free_gift_res->image;
					else
						$thumb_image = config('global.NO_IMAGE_THUMB');

					//$thumb_image = str_replace(config('global.SITE_URL'),config('global.SECURED_PATH'),$thumb_image);

					$free_gift_res->prod_image ='<img src="'.$thumb_image.'" border="0" width="125" />';
					$free_gift_res->image_forpopup ='<img src="'.$thumb_image.'" border="0" width="75" />';
					$free_gift_res->billing_image ='<img src="'.$thumb_image.'" border="0" width="195" alt="'.$free_gift_res->product_name.'" title="'.$free_gift_res->product_name.'"/>';

					$VendorSKU 		= "";
					$IsCosmo  		= "";
					$IsNandansons	= "";
					$IsPerfumePW	= "";
					$IsPCA	= "";

					$breadcrumbs = '';
					$fetch_brand = DB::table('pu_manufacture')->where('imanufactureid','=',$free_gift_res->imanufactureid)->get();

					$fetch_category = DB::table('pu_category as c')
											->join('pu_products_category as pc','c.category_id','=','pc.category_id')
											->join('pu_products as p', 'pc.products_id','=','p.products_id')
											->where('p.products_id','=',$free_gift_res->products_id)->get();

				   $CategoryID	= 0;
					if($fetch_category && $fetch_category->count() > 0)
					{
						$gcat = stripcslashes($fetch_category[0]->category_name);
						$CatInfo = config('CATEGORY_INFO');
						$breadcrumbs = $CatInfo['CatForProd'][$fetch_category[0]->category_id]['subcatbredcrum'];

						$CategoryID	= $fetch_category[0]->category_id;

					}

					if($free_gift_res->stock == "Out")
					{
						if($free_gift_res->cosmo_sku !='' &&  $free_gift_res->cosmo_current_stock > 0 )
						{
							$IsCosmo = "Yes";
							$VendorSKU = $free_gift_res->cosmo_sku;
						}
						else if($free_gift_res->pca_sku !='' &&  $free_gift_res->pca_current_stock > 0)
						{
							$IsPCA  = "Yes";
							$VendorSKU = $free_gift_res->pca_sku;
						}else if($free_gift_res->nandansons_sku !='' &&  $free_gift_res->nandansons_current_stock > 0)
						{
							$IsNandansons = "Yes";
							$VendorSKU = $free_gift_res->nandansons_sku;
						}
					}
					$temp_ary = array();

					if($free_gift_res->WebsiteStock == "In")
					{
						$temp_ary['IsMaxaromaTwoDelivery'] = $free_gift_res->maxtwodaydelivery;
					}

					$temp_ary['ProductID']   		= $free_gift_res->products_id;
					$temp_ary['SKU']         		= "GIFT-".$free_gift_res->sku;
					$temp_ary['ORGSKU']         	= $free_gift_res->sku;
					$temp_ary['CategoryID']         = $CategoryID;
					$temp_ary['ProductName'] 		= stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_gift_res->product_name));
					$temp_ary['short_description'] 	= strip_tags(stripslashes(str_ireplace(array("\r","\n",'\r','\n'),'',$free_gift_res->short_description)));
					$temp_ary['Billing_Image'] 		= $free_gift_res->billing_image;
					$temp_ary['Price']       		= 0;
					$temp_ary['Qty'] 		 		= 1;
					$temp_ary['TotPrice']    		= 0;
					$temp_ary['Image']       		= $free_gift_res->prod_image;
					$temp_ary['Prod_URL']       	= "";
					$temp_ary['IS_Free_Gift']       = "Yes";
					$temp_ary['FreeGiftCoupon']     = "Yes";
					$temp_ary['image_forpopup']		= $free_gift_res->image_forpopup;
					$temp_ary['freeproductsid']		= $free_gift_res->products_id;
					$temp_ary['VendorSKU']			= $VendorSKU;
					$temp_ary['IsCosmo']			= $IsCosmo;
					$temp_ary['IsNandansons']		= $IsNandansons;
					$temp_ary['IsPerfumePW']		= $IsPerfumePW;
					$temp_ary['IsPCA']				= $IsPCA;
					$temp_ary['ImanufactureID']		= $free_gift_res->imanufactureid;
					$temp_ary['IsDealProducts']		= "No";
					$temp_ary['DealDiscountFlag']	= "No";
					$temp_ary['dealdiscount_flag']	= "No";
					$temp_ary['manufactureName']	= $fetch_brand[0]->vmanufacture;
					$temp_ary['CategoryName']		= $breadcrumbs;
					$temp_ary['FinalSale']         	= '';
					$temp_ary['OrderType'] 			= $Flag;

					$Cart[]=$temp_ary;
					Session::put('ShoppingCart.'.$Flag.'Cart',$Cart);
					$this->SplitCalculateSubTotal(Session::get('ShoppingCart.'.$Flag.'Cart'),$Flag);
				}else{
					$OutofStockMsg = "The Free bundle is out of  stock and cannot be added to your order";
					Session::flash('OutOfStockBundle','The Free bundle is out of  stock and cannot be added to your order');
				}
			}
		}
		return $OutofStockMsg;
	}
	public function ApplySplitCreditDiscount($StoreOrderArr,$WebsiteOrderArr)
	{
		//$StoreCreditLimitDiscount = array_sum(array_column($StoreOrderArr, 'CreditLimitItemWiseDiscout'));
		$StoreCreditLimitDiscount = ArraySum($StoreOrderArr, 'CreditLimitItemWiseDiscout');
		$StoreCreditLimitDiscount = NumberFormat($StoreCreditLimitDiscount);
		//$WebsiteCreditLimitDiscount = array_sum(array_column($WebsiteOrderArr, 'CreditLimitItemWiseDiscout'));
		$WebsiteCreditLimitDiscount = ArraySum($WebsiteOrderArr, 'CreditLimitItemWiseDiscout');
		$WebsiteCreditLimitDiscount = NumberFormat($WebsiteCreditLimitDiscount);

		Session::put('ShoppingCart.Websitecredit_limit_discount', $WebsiteCreditLimitDiscount);
		Session::put('ShoppingCart.Storecredit_limit_discount', $StoreCreditLimitDiscount);
	}
	public function GetSplitNetTotal($Flag="Store")
	{

		$GiftCouponInfo = $this->GetSplitAllDiscounts('GiftCoupon',array(),$Flag);
		$giftWrapCharge = 0;
		if(Session::has('ShoppingCart.'.$Flag.'GiftWrapping'))
		{
			$giftWrap = Session::get('ShoppingCart.'.$Flag.'GiftWrapping');
			if(!empty($giftWrap['Charge']) &&$giftWrap['Charge'] > 0 )
			{
				$giftWrapCharge = (float)$giftWrap['Charge'];
			}
		}
		$ShippingSignature = 0;
		if(Session::has('ShoppingCart.ShippingSignature') && $Flag=="Website")
			$ShippingSignature = (float)Session::get('ShoppingCart.ShippingSignature');

		$AllCharges = $this->GetSplitAllCharges('',$Flag);

		$SubTotal = Session::get('ShoppingCart.'.$Flag.'SubTotal');
		$TotalAmount = $SubTotal + $AllCharges['TotalCharges'];

		$AllDiscount = $this->GetSplitAllDiscounts('',$CartArr=array(),$Flag);

		$TotalDiscount = $AllDiscount['TotalDiscount'];
		$NetTotal = $TotalAmount - $TotalDiscount;

		if($NetTotal <= 0)
			$NetTotal = 0;

		return NumberFormat( $NetTotal );
	}
	public function GetSplitAllCharges($ChargeName='',$Flag="Store")
	{
		$log['ChargeName'] = $ChargeName;
		addLog('GetAllChargesStart',$log);
		$Charges = [];
		If(Session::has('ShoppingCart.Shipping.ShippingCharge') && Session::get('ShoppingCart.Shipping.ShippingCharge') > 0 && $Flag=="Website")
			$Charges['ShippingCharge'] = ['label' => 'Shipping Charge', 'charge' => Session::get('ShoppingCart.Shipping.ShippingCharge')];
		if(Session::has('ShoppingCart.'.$Flag.'Tax') && Session::get('ShoppingCart.'.$Flag.'Tax') > 0)
		{
			
			$Charges['Tax'] = ['label' => 'Sales Tax', 'charge' => Session::get('ShoppingCart.'.$Flag.'Tax')];
		}
		if(Session::has('ShoppingCart.'.$Flag.'GiftWrapping'))
		{
			$giftWrap = Session::get('ShoppingCart.'.$Flag.'GiftWrapping');
			if(!empty($giftWrap['Charge'])  && $giftWrap['Charge'] > 0)
				$Charges['GiftWrappingCharge'] = ['label' => 'Gift Wrapping Charge', 'charge' => $giftWrap['Charge']];
		}
		if(Session::has('ShoppingCart.ShippingSignature') && $Flag=="Website")
			$Charges['ShippingSignature'] = ['label' => 'Shipping Signature', 'charge' => Session::get('ShoppingCart.ShippingSignature')];
		if(Session::has('shipping_insurance_charge') && $Flag=="Website")
			$Charges['ShippingInsurance'] = ['label' => 'Shipping Insurance Charge', 'charge' => Session::get('shipping_insurance_charge')];

		if($ChargeName != '')
		{
			$Charge = (isset($Charges[$ChargeName])?NumberFormat($Charges[$ChargeName]['charge']):0);
			$log['Charge'] = $Charge;
			addLog('GetAllCharges',$log);
			return $Charge;
		} else {
			$TotalCharges = array_sum(array_column($Charges,'charge'));
			$ChargesInfo  = ['Charges' => $Charges, 'TotalCharges' => NumberFormat($TotalCharges)];
			$log['ChargesInfo'] = $ChargesInfo;
			addLog('GetAllCharges',$log);
			return $ChargesInfo;
		}
	}
	public function GetSplitAllCoupons($CouponID='',$Flag="Store")
	{
		$Coupons = ['CouponCode' => '','CouponCodeSecond' => '', 'GiftCoupon'];
		If(Session::has('ShoppingCart.PromoCoupon.CouponCode') && Session::get('ShoppingCart.PromoCoupon.CouponCode') != '')
			$Coupons['CouponCode'] = Session::get('ShoppingCart.PromoCoupon.CouponCode');
		If(Session::has('ShoppingCart.PromoCoupon.CouponCodeSecond') && Session::get('ShoppingCart.PromoCoupon.CouponCodeSecond') != '')
			$Coupons['CouponCodeSecond'] = Session::get('ShoppingCart.PromoCoupon.CouponCodeSecond');
		If(Session::has('ShoppingCart.GiftCoupon') && Session::get('ShoppingCart.GiftCoupon') != '')
			$Coupons['GiftCoupon'] = Session::get('ShoppingCart.GiftCoupon');
		if($CouponID != '') {
			if(isset($Coupons[$CouponID]))
				return $Coupons[$CouponID];
		} else {
			return $Coupons;
		}
	}
	public function ApplySplitGiftCoupons($coupon,$Flag="Store")
	{
		$log['coupon'] = json_encode($coupon);
		addLog("ApplyGiftCouponsStart",$log);
		$totvalue = $this->GetNetTotal();

		if($totvalue<=0)
		{
			$totvalue = 0;
		}

		$groupedOrders = collect(Session::get('ShoppingCart.Cart'))->groupBy('OrderType')->toArray();
		$StoreOrderArr = array_values($groupedOrders[$Flag]);
		$CartInfo = $StoreOrderArr;

		$Gifcard = "No";

		$TotalAmount = 0;
		if(count($CartInfo) > 0)
		{
			for($i=0;$i<count($CartInfo);$i++)
			{
				if(isset($CartInfo[$i]['IsGiftCertificateItem']) && $CartInfo[$i]['IsGiftCertificateItem']=="Yes")
				{
					$Gifcard = "Yes";
					break;
				}
				$FreeGift = '';
				if(isset($CartInfo[$i]["IS_Free_Gift"]))
					$FreeGift = $CartInfo[$i]["IS_Free_Gift"];
				if(isset($CartInfo[$i]["IsDealProducts"]) && $CartInfo[$i]["IsDealProducts"]!="Yes" && $FreeGift!="Yes")
				{
					$TotalAmount = $TotalAmount + $CartInfo[$i]['TotPrice'];
				}

			}
		}

		if(isset($Gifcard) &&  $Gifcard=="Yes")
		{
			Session::put('ShoppingCart.'.$Flag.'GiftCoupon.Code','');
			Session::put('ShoppingCart.'.$Flag.'GiftCoupon.Value', 0.0);
			Session::put('ShoppingCart.'.$Flag.'GiftCoupon.Applicable_Value',0.0);
			addLog("ResetGiftCoupon");

			return $Gifcard;
		}

		$coupon = trim($coupon);
		$CouponRS = GiftCertificate::where('remaining_value','>',0)->where('status','=','1')->where('gc_code','=',$coupon)->where('expiry_date','>=',DB::raw('curdate()'))->get();

		$this->SplitCalculateSubTotal($CartInfo,$Flag);

		$SubTotal = Session::get('ShoppingCart.'.$Flag.'SubTotal');
		$log['giftcouponrecordset'] = json_encode($CouponRS);
		addLog("GiftCouponRecordset",$log);

		if($CouponRS && $CouponRS->count() > 0 && $SubTotal >= $CouponRS[0]['minimum_purchase_value'] && isset($CouponRS[0]['gc_id']) && $CouponRS[0]['gc_id'] >0 && isset($CouponRS[0]['is_added_by_admin']) && $CouponRS[0]['is_added_by_admin']=='No')
		{

			$OrderRESData = OrderDetail::where('orders_detail_id','=',$CouponRS[0]['orders_detail_id'])->get();

			if($OrderRESData->count() > 0 && isset($OrderRESData[0]['orders_detail_id']) && $OrderRESData[0]['orders_detail_id'] > 0)
			{

				$OrderResVal = Order::where('orders_id','=',$OrderRESData[0]['orders_id'])->whereIn('status',['Pending','Completed'])->get();

				if($OrderResVal->count() > 0 && isset($OrderResVal[0]['orders_id']) && $OrderResVal[0]['orders_id'] > 0)
				{

					$remainingValue = $CouponRS[0]['remaining_value'];
					if($CouponRS[0]['remaining_value'] >= $totvalue)
					{
						$CouponRS[0]['remaining_value'] = $totvalue;
						$CouponRS[0]['remaining_value'] = NumberFormat($CouponRS[0]['remaining_value']);
					}

					Session::put('ShoppingCart.'.$Flag.'GiftCoupon.Code',$CouponRS[0]['gc_code']);
					Session::put('ShoppingCart.'.$Flag.'GiftCoupon.Value', $CouponRS[0]['remaining_value']);
					Session::put('ShoppingCart.'.$Flag.'GiftCoupon.Applicable_Value', $CouponRS[0]['remaining_value']);
					$NewValue = $remainingValue - Session::get('ShoppingCart.'.$Flag.'GiftCoupon.Applicable_Value');
					if($TotalAmount > 0)
					{
						for($i=0;$i<count($CartInfo);$i++)
						{
							$FreeGift = '';
							if(isset($CartInfo[$i]["IS_Free_Gift"]))
								$FreeGift = $CartInfo[$i]["IS_Free_Gift"];
							if((isset($CartInfo[$i]["IsDealProducts"]) && $CartInfo[$i]["IsDealProducts"]!="Yes") && $FreeGift!="Yes")
							{
								$GiftCertificateDiscountItemWise = (($CouponRS[0]['remaining_value']*100)/$TotalAmount);
								$GiftCertificateDiscountCal = (($CartInfo[$i]['TotPrice']  * $GiftCertificateDiscountItemWise)/100);
								Session::put('ShoppingCart.Cart.'.$i.'.GiftCertificateItemWiseDiscout',$GiftCertificateDiscountCal);
							}

						}

					}
					Session::put('ShoppingCart.GiftCoupon.Remaining_Value',$NewValue);
					$log['giftcouponRemaining_Value'] = json_encode($NewValue);
					addLog("SetGiftCouponRemainingValue",$log);
					return 1;
				 }
				 else
				 {
					Session::put('ShoppingCart.GiftCoupon.Code','');
					Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
					Session::put('ShoppingCart.GiftCoupon.Applicable_Value',0.0);
					addLog("ResetGiftCoupon_2");
					return 2;
				 }

			}
			else
			 {
				Session::put('ShoppingCart.GiftCoupon.Code','');
				Session::put('ShoppingCart.GiftCoupon.Value', 0.0);
				Session::put('ShoppingCart.GiftCoupon.Applicable_Value',0.0);
				addLog("ResetGiftCoupon_3");
				return 2;
			 }
		}
		else if($CouponRS && $CouponRS->count() > 0 && $SubTotal >= $CouponRS[0]['minimum_purchase_value'] && isset($CouponRS[0]['is_added_by_admin']) && $CouponRS[0]['is_added_by_admin']=='Yes'&& isset($CouponRS[0]['gc_id']) && $CouponRS[0]['gc_id'] >0)
		{
			$remainingValue = $CouponRS[0]['remaining_value'];
			if($CouponRS[0]['remaining_value'] >= $totvalue)
			{
				$CouponRS[0]['remaining_value'] = $totvalue;
				$CouponRS[0]['remaining_value'] = NumberFormat($CouponRS[0]['remaining_value']);
			}

			Session::put('ShoppingCart.GiftCoupon.Code',$CouponRS[0]['gc_code']);
			Session::put('ShoppingCart.GiftCoupon.Value', $CouponRS[0]['remaining_value']);
			Session::put('ShoppingCart.GiftCoupon.Applicable_Value', $CouponRS[0]['remaining_value']);
			$NewValue = $remainingValue - Session::get('ShoppingCart.GiftCoupon.Applicable_Value');
			Session::put('ShoppingCart.GiftCoupon.Remaining_Value',$NewValue);
			$log['giftcouponRemaining_Value'] = json_encode($NewValue);

			if($TotalAmount > 0)
			{
				for($i=0;$i<count($CartInfo);$i++)
				{
					$FreeGift = '';
					if(isset($CartInfo[$i]["IS_Free_Gift"]))
						$FreeGift = $CartInfo[$i]["IS_Free_Gift"];
					if((isset($CartInfo[$i]["IsDealProducts"]) && $CartInfo[$i]["IsDealProducts"]!="Yes") && $FreeGift!="Yes")
					{
						$GiftCertificateDiscountItemWise = (($CouponRS[0]['remaining_value']*100)/$TotalAmount);
						$GiftCertificateDiscountCal = (($CartInfo[$i]['TotPrice']  * $GiftCertificateDiscountItemWise)/100);
						Session::put('ShoppingCart.'.$Flag.'Cart.'.$i.'.GiftCertificateItemWiseDiscout',$GiftCertificateDiscountCal);
					}

				}

			}

			addLog("SetGiftCoupon",$log);
			return 1;
		}

		else
		{
			Session::put('ShoppingCart.'.$Flag.'GiftCoupon.Code','');
			Session::put('ShoppingCart.'.$Flag.'GiftCoupon.Value', 0.0);
			Session::put('ShoppingCart.'.$Flag.'GiftCoupon.Applicable_Value',0.0);
			addLog("ResetGiftCoupon_4");
			return 2;
		}
	}

	public function ApplySplitGiftCouponsNew($StoreOrderArr,$WebsiteOrderArr)
	{
		//$StoreGiftDiscount = array_sum(array_column($StoreOrderArr, 'GiftCertificateItemWiseDiscout'));
		$StoreGiftDiscount = ArraySum($StoreOrderArr,'GiftCertificateItemWiseDiscout');
		$StoreGiftDiscount = NumberFormat($StoreGiftDiscount);
		//$WebsiteGiftDiscount = array_sum(array_column($WebsiteOrderArr, 'GiftCertificateItemWiseDiscout'));
		$WebsiteGiftDiscount = ArraySum($WebsiteOrderArr,'GiftCertificateItemWiseDiscout');
		$WebsiteGiftDiscount = NumberFormat($WebsiteGiftDiscount);
		Session::put('ShoppingCart.WebsiteGiftCoupon.Value', $WebsiteGiftDiscount);
		Session::put('ShoppingCart.StoreGiftCoupon.Value', $StoreGiftDiscount);
	}

	public function ApplySplitAutoDiscountNew($StoreOrderArr,$WebsiteOrderArr)
	{
		//$StoreAutoDiscount = array_sum(array_column($StoreOrderArr, 'AutoItemWiseDiscout'));
		$StoreAutoDiscount = ArraySum($StoreOrderArr,'AutoItemWiseDiscout');
		$StoreAutoDiscount = NumberFormat($StoreAutoDiscount);
		//$WebsiteAutoDiscount = array_sum(array_column($WebsiteOrderArr, 'AutoItemWiseDiscout'));
		$WebsiteAutoDiscount = ArraySum($WebsiteOrderArr,'AutoItemWiseDiscout');
		$WebsiteAutoDiscount = NumberFormat($WebsiteAutoDiscount);
		Session::put('ShoppingCart.WebsiteAutoDiscount', $WebsiteAutoDiscount);
		Session::put('ShoppingCart.StoreAutoDiscount', $StoreAutoDiscount);
	}

	public function ApplySplitQuantityDiscountNew($StoreOrderArr,$WebsiteOrderArr)
	{
		//$StoreQuantityDiscount = array_sum(array_column($StoreOrderArr, 'QuantityItemWiseDiscout'));
		$StoreQuantityDiscount = ArraySum($StoreOrderArr,'QuantityItemWiseDiscout');
		$StoreQuantityDiscount = NumberFormat($StoreQuantityDiscount);
		//$WebsiteQuantityDiscount = array_sum(array_column($WebsiteOrderArr, 'QuantityItemWiseDiscout'));
		$WebsiteQuantityDiscount = ArraySum($WebsiteOrderArr,'QuantityItemWiseDiscout');
		$WebsiteQuantityDiscount = NumberFormat($WebsiteQuantityDiscount);
		Session::put('ShoppingCart.WebsiteQuantityDiscount', $WebsiteQuantityDiscount);
		Session::put('ShoppingCart.StoreQuantityDiscount', $StoreQuantityDiscount);
	}

	public function ApplySplitBogoDiscount($StoreOrderArr,$WebsiteOrderArr)
	{
		//$StoreBogoDiscount = array_sum(array_column($StoreOrderArr, 'BogoItemWiseDiscout'));
		$StoreBogoDiscount = ArraySum($StoreOrderArr,'BogoItemWiseDiscout');
		$StoreBogoDiscount = NumberFormat($StoreBogoDiscount);
		//$WebsiteBogoDiscount = array_sum(array_column($WebsiteOrderArr, 'BogoItemWiseDiscout'));
		$WebsiteBogoDiscount = ArraySum($WebsiteOrderArr,'BogoItemWiseDiscout');
		$WebsiteBogoDiscount = NumberFormat($WebsiteBogoDiscount);
		Session::put('ShoppingCart.WebsiteDogoDiscount', $WebsiteBogoDiscount);
		Session::put('ShoppingCart.StoreDogoDiscount', $StoreBogoDiscount);
	}

	public function ApplySplitCouponDiscountNew($StoreOrderArr,$WebsiteOrderArr)
	{
		//$StoreCouponDiscount = array_sum(array_column($StoreOrderArr, 'CouponDisItemWiseDiscout'));
		$StoreCouponDiscount = ArraySum($StoreOrderArr,'CouponDisItemWiseDiscout');
		$StoreCouponDiscount = NumberFormat($StoreCouponDiscount);
		//$WebsiteCouponDiscount = array_sum(array_column($WebsiteOrderArr, 'CouponDisItemWiseDiscout'));
		$WebsiteCouponDiscount = ArraySum($WebsiteOrderArr,'CouponDisItemWiseDiscout');
		$WebsiteCouponDiscount = NumberFormat($WebsiteCouponDiscount);
		Session::put('ShoppingCart.PromoCoupon.WebsiteCouponDiscount', $WebsiteCouponDiscount);
		Session::put('ShoppingCart.PromoCoupon.StoreCouponDiscount', $StoreCouponDiscount);

	}
	public function ApplySplitRewardDiscountNew($StoreOrderArr,$WebsiteOrderArr)
	{
		//$StoreRewardDiscount = array_sum(array_column($StoreOrderArr, 'RewardItemWiseDiscout'));
		$StoreRewardDiscount = ArraySum($StoreOrderArr,'RewardItemWiseDiscout');
		$StoreRewardDiscount = NumberFormat($StoreRewardDiscount);
		//$WebsiteRewardDiscount = array_sum(array_column($WebsiteOrderArr, 'RewardItemWiseDiscout'));
		$WebsiteRewardDiscount = ArraySum($WebsiteOrderArr,'RewardItemWiseDiscout');
		$WebsiteRewardDiscount = NumberFormat($WebsiteRewardDiscount);
		Session::put('ShoppingCart.WebsiteYotpoRewardDiscount', $WebsiteRewardDiscount);
		Session::put('ShoppingCart.StoreYotpoRewardDiscount', $StoreRewardDiscount);

	}

	public function SplitTaxCalculation($Tax,$Flag="Store",$onlyGCPurchased = 0)
	{
		$is_cal_tax = 'Yes';
		if($onlyGCPurchased==1) {
			$is_cal_tax = 'No';
		}

		if($is_cal_tax == 'No')
		{
			Session::put('ShoppingCart.'.$Flag.'Tax', 0);
			return NULL;
		}

		if($Tax > 0)
		{

			$GroupArr1 = collect(Session::get('ShoppingCart.Cart'))->groupBy('OrderType')->toArray();
			$GroupArr = array_values($GroupArr1[$Flag]);

			$SubTotal = Session::get('ShoppingCart.SubTotal');
			$this->SplitCalculateSubTotal($GroupArr,$Flag);
			if(Session::has('ShoppingCart.'.$Flag.'SubTotal') && Session::get('ShoppingCart.'.$Flag.'SubTotal') > 0)
			{
			$SplitSubTotal = Session::get('ShoppingCart.'.$Flag.'SubTotal');
			$Tax = (($Tax * $SplitSubTotal)/$SubTotal);
			$Tax = NumberFormat($Tax);
			Session::put('ShoppingCart.'.$Flag.'Tax', $Tax);
			}
		}

	}
	public function ApplySplitGiftWrapping($Flag='Store',$GroupArr=array())
	{
		addLog('ApplyGiftWrappingStart');
		$shopcart = [];

		$groupedOrders = collect(Session::get('ShoppingCart.Cart'))->groupBy('OrderType')->toArray();
		$shopcart = array_values($groupedOrders[$Flag]);

		if(count($shopcart) > 0)
		{
			$total_gift_charge = 0;
			$GiftWrappingCharge = config('Settings.GIFT_WRAPPING_CHARGE');
			for($i=0;$i<count($shopcart);$i++)
			{
				if((isset($shopcart[$i]['gift_wrap']) && $shopcart[$i]['gift_wrap']=='Yes'))
				{
					$total_gift_charge+=$shopcart[$i]['Qty'] * $GiftWrappingCharge;
				}
			}
			$tempAry['Charge'] 	= NumberFormat($total_gift_charge);
			$tempAry['Applied'] = 'Yes';
			Session::put('ShoppingCart.'.$Flag.'GiftWrapping',$tempAry);
			addLog('ApplyGiftWrapping',$tempAry);
			return null;
		}
	}

	public function GetSplitCreditLimitAmount($Flag="Store")
	{
		$CreditAmt = 0;
		$RemainCreditLimit = 0;
		$CreditLimitFlag = 0;
		$normaluser = Auth::user();
		if (Auth::guard('store')->check()) {
			$normaluser = Auth::guard('web')->user();
		}
		/*if(Auth::user() && $CreditAmt = Auth::user()->credit_limit > 0 && Auth::user()->registration_type == 'M' && config('Settings.WHOLESALE_CREDIT_LIMIT') == 'Yes' && Auth::user()->is_dropshipper != 'Yes')*/
		if($normaluser && $CreditAmt = $normaluser->credit_limit > 0 && $normaluser->registration_type == 'M' && config('Settings.WHOLESALE_CREDIT_LIMIT') == 'Yes' && $normaluser->is_dropshipper != 'Yes')
		{
			$CreditDiscount = $this->GetSplitAllDiscounts('CreditLimitDiscount',array(),$Flag);
			if(Session::has('ShoppingCart.'.$Flag.'customer_remaining_credit_amount'))
				$RemainCreditLimit = $this->Make_Price(Session::get('ShoppingCart.'.$Flag.'customer_remaining_credit_amount'));

			$NetTotal = $this->GetSplitNetTotal($Flag);
			$CreditAmt = $normaluser->credit_limit; //Auth::user()->credit_limit;
			if($CreditDiscount <= 0 && $NetTotal > 0){
				$CreditLimitFlag = 1;
			}elseif($CreditDiscount > 0){
				$CreditLimitFlag = 2;
			}else{
				$CreditAmt = 0;
			}
		}
		return ['CreditLimitFlag' => $CreditLimitFlag, 'CreditLimit' => $CreditAmt, 'RemainCreditLimit' => $RemainCreditLimit];
	}

	public function SplitGoogleTagManager($Data=[],$Flag="Store",$tempCart=array())
	{
		if($Data['page'] == 'order_receipt')
		{
			$Coupon_DiscLevel = "";
			$coupon_code = $this->GetSplitAllCoupons('CouponCode',array(),$Flag);
			$discountInfo = $this->GetSplitAllDiscounts('',array(),$Flag);
			$discount = $discountInfo['TotalDiscount'];

			if($coupon_code != "" && Session::has('ShoppingCart.'.$Flag.'Coupon_Detail_CJ.orders'))
			{
				$coupon_order = Session::get('ShoppingCart.'.$Flag.'Coupon_Detail_CJ.orders');
				if($coupon_order != ""){
					if($coupon_order == 0){
						//on order amount so whole order discount
						$Coupon_DiscLevel = "Order";
					}else if($coupon_order == 4){
						//free shipping so no discount
						$Coupon_DiscLevel = "None";
					}else{
						//category,sku,brand etc.
						$Coupon_DiscLevel = "Item";
					}
				}
			}

			$cj_discount = $discount;
			if($Coupon_DiscLevel == "None" || $Coupon_DiscLevel == "Item"){
				$cj_discount = "0";
			}
		}
		if($Data['page'] == 'shoppingcart' || $Data['page'] == 'billing' || $Data['page'] == 'billing_amazon' || $Data['page'] == 'order_receipt')
		{
			$pid_fstr = '';
			$tot_qty = 0;
			$sf_item_array = array();
			$tempCart = ($tempCart)?$tempCart:[];
			$item_array = array();
			$cnt_row = count($tempCart);
			if($cnt_row > 1)
			{
				for($ir=0;$ir<$cnt_row;$ir++)
				{
					if($tempCart[$ir]['SKU'] != "")
					{
						$product_quantity = $tempCart[$ir]['Qty'];
						$tot_qty += $product_quantity;

						$product_name = $tempCart[$ir]['ProductName'];
						$product_name = str_replace("\"","'",$product_name);

						$item_array[$ir]['product_name'] = $product_name;
						$item_array[$ir]['product_id'] = $tempCart[$ir]['SKU'];
						$item_array[$ir]['product_price'] = $tempCart[$ir]['Price'];
						$item_array[$ir]['product_quantity'] = $product_quantity;

						$ItemWiseDiscount = 0;
						if($Data['page'] == 'order_receipt')
						{
							if(((isset($tempCart[$ir]['ItemWiseCouponDiscount']) && $tempCart[$ir]['ItemWiseCouponDiscount'] > 0) || (isset($tempCart[$ir]['ItemWiseCouponDiscount_CJ']) && $tempCart[$ir]['ItemWiseCouponDiscount_CJ'] > 0)) && $Coupon_DiscLevel == "Item"){
								if($coupon_order == "7"){
									$ItemWiseDiscount = $tempCart[$ir]['ItemWiseCouponDiscount_CJ'];
								}else{
									$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
								}
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						} else {
							if(isset($tempCart[$ir]['ItemWiseCouponDiscount']) && $tempCart[$ir]['ItemWiseCouponDiscount'] > 0){
								$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						}
						$pid_fstr .= $tempCart[$ir]['SKU'].",";

						//sf track cart start
						$sf_item_array[$ir]['item'] = $tempCart[$ir]['SKU'];
						$sf_item_array[$ir]['quantity'] = $product_quantity;
						$sf_item_array[$ir]['price'] = $tempCart[$ir]['Price'];
						$sf_item_array[$ir]['unique_id'] = $tempCart[$ir]['SKU'];
						//sf track cart end
					}
				}
				$pid_fstr = substr($pid_fstr,0,-1);
				if($pid_fstr != "")
				{
					$Data['RemarketingprodID'] = explode(",",$pid_fstr);
				}
			}
			else
			{
				for($ir=0;$ir<$cnt_row;$ir++)
				{
					if($tempCart[$ir]['SKU'] != "")
					{
						$product_quantity = $tempCart[$ir]['Qty'];
						$tot_qty += $product_quantity;

						$product_name = $tempCart[$ir]['ProductName'];
						$product_name = str_replace("\"","'",$product_name);

						$item_array[$ir]['product_name'] = $product_name;
						$item_array[$ir]['product_id'] = $tempCart[$ir]['SKU'];
						$item_array[$ir]['product_price'] = $tempCart[$ir]['Price'];
						$item_array[$ir]['product_quantity'] = $product_quantity;

						$ItemWiseDiscount = 0;
						$tempCart[$ir]['ItemWiseCouponDiscount'] = (isset($tempCart[$ir]['ItemWiseCouponDiscount'])?$tempCart[$ir]['ItemWiseCouponDiscount']:0);
						$tempCart[$ir]['ItemWiseCouponDiscount_CJ'] = (isset($tempCart[$ir]['ItemWiseCouponDiscount_CJ'])?$tempCart[$ir]['ItemWiseCouponDiscount_CJ']:0);
						if($Data['page'] == 'order_receipt')
						{
							if(($tempCart[$ir]['ItemWiseCouponDiscount'] > 0 || $tempCart[$ir]['ItemWiseCouponDiscount_CJ'] > 0) && $Coupon_DiscLevel == "Item"){
								if($coupon_order == "7"){
									$ItemWiseDiscount = $tempCart[$ir]['ItemWiseCouponDiscount_CJ'];
								}else{
									$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
								}
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						} else {
							if($tempCart[$ir]['ItemWiseCouponDiscount'] > 0){
								$ItemWiseDiscount = $tempCart[$ir]['TotPrice'] - $tempCart[$ir]['ItemWiseCouponDiscount'];
							}
							$item_array[$ir]['ItemWiseDiscount'] = number_format($ItemWiseDiscount,2);
						}

						$pid_fstr .= $tempCart[$ir]['SKU'];

						//sf track cart start
						$sf_item_array[$ir]['item'] = $tempCart[$ir]['SKU'];
						$sf_item_array[$ir]['quantity'] = $product_quantity;
						$sf_item_array[$ir]['price'] = $tempCart[$ir]['Price'];
						$sf_item_array[$ir]['unique_id'] = $tempCart[$ir]['SKU'];
						//sf track cart end
					}
				}
				if($pid_fstr != "")
				{
					$Data['RemarketingprodID'] = $pid_fstr;
				}
			}
			$Data['RemarketingtotalValue'] = Session::get('ShoppingCart.'.$Flag.'SubTotal');

			if($Data['page'] != 'order_receipt')
				$Data['SF_TrackCart'] = $sf_item_array;

		}

		$DataLayer = [];
		$DataLayer['RemarketingprodID'] = isset($Data['RemarketingprodID'])?$Data['RemarketingprodID']:'';
		$DataLayer['RemarketingpageType'] = $Data['pagetype'];
		if(isset($Data['RemarketingtotalValue']))
			$DataLayer['RemarketingtotalValue'] = $Data['RemarketingtotalValue'];
		$DataLayer['RemarketingOnly'] = "true";
		if(isset($Data['search_query']))
			$DataLayer['search_query'] = $Data['search_query'];
		if(isset($Data['SF_TrackCart']))
			$DataLayer['SF_TrackCart'] = $Data['SF_TrackCart'];

		if($Data['page'] == 'shoppingcart')
		{
			$DataLayer['order_quantity'] = $tot_qty;
			$DataLayer['currency'] = Session::get('currency_code');
		}
		if($Data['page'] == 'billing' || $Data['page'] == 'billing_amazon')
		{
			$DataLayer['line_items_array'] = $item_array;
			$DataLayer['line_items'] = $item_array;
			$DataLayer['order_quantity'] = $tot_qty;
			$DataLayer['currency'] = Session::get('currency_code');
		}

		if($Data['page'] == 'order_receipt')
		{
			$DataLayer['RemarketingConversionLanguage'] = 'en';
			$DataLayer['RemarketingConversionFormat'] = '3';
			$DataLayer['RemarketingConversionColor'] = 'ffffff';
			$DataLayer['RemarketingConversionLabel'] = 'O5inCKztgGsQkqLpuQM';
			$DataLayer['RemarketingOnly'] = "false";
			$DataLayer['RemarketingCouponCode'] = $coupon_code;
			$DataLayer['RemarketingDiscount'] = $discount;
			$DataLayer['RemarketingDiscountCJ'] = $cj_discount;
			$DataLayer['RemarketingOrderId'] = Session::get('ShoppingCart.'.$Flag.'OrderID');
			$DataLayer['line_items_array'] = $item_array;
			$DataLayer['line_items'] = $item_array;
			$DataLayer['order_quantity'] = $tot_qty;
			$DataLayer['currency'] = Session::get('currency_code');
			$DataLayer['SF_TrackPurchase'] = $sf_item_array;
			$GData['LabelVal'] = 'O5inCKztgGsQkqLpuQM';
		}

		if($Data['pagetype'] == 'other')
		{
			$DataLayer['RemarketingConversionLanguage'] = 'en';
			$DataLayer['RemarketingConversionFormat'] = '3';
			$DataLayer['RemarketingConversionColor'] = 'ffffff';
			$DataLayer['RemarketingOnly'] = "false";
			if($Data['page'] == 'register')
			{
				$DataLayer['RemarketingConversionLabel'] = 'FFhXCJKNhmsQkqLpuQM';
				$DataLayer['lead_type'] = 'RegistrationForm';
				$GData['LabelVal'] = 'FFhXCJKNhmsQkqLpuQM';
			}
			if($Data['page'] == 'wholesaleregister')
			{
				$DataLayer['RemarketingConversionLabel'] = 'SX50CJaUhmsQkqLpuQM';
				$DataLayer['lead_type'] = 'RegistrationForm';
				$GData['LabelVal'] = 'SX50CJaUhmsQkqLpuQM';
			}
			if($Data['page'] == 'myaccount')
			{
				if(Session::get('eusertype') == 'Retailer')
				{
					$DataLayer['RemarketingConversionLabel'] = 'FFhXCJKNhmsQkqLpuQM';
					$GData['LabelVal'] = 'FFhXCJKNhmsQkqLpuQM';
				}
				if(Session::get('eusertype') == 'Wholesaler')
				{
					$DataLayer['RemarketingConversionLabel'] = 'SX50CJaUhmsQkqLpuQM';
					$GData['LabelVal'] = 'SX50CJaUhmsQkqLpuQM';
				}
			}
			if($Data['page'] == 'contact_us')
			{
				$DataLayer['RemarketingConversionLabel'] = 'I4tECI6u7WoQkqLpuQM';
				$GData['LabelVal'] = 'I4tECI6u7WoQkqLpuQM';
			}
		}
		//Log::info($DataLayer);
		$DataLayer = json_encode($DataLayer);
		$GData['pagetype'] = $Data['pagetype'];
		$GData['google_remarketing_codes']="<script type='text/javascript'>dataLayer.push(".$DataLayer.");</script>";
		if(Session::has('sess_icustomerid') && Session::get('sess_icustomerid') > 0 && Session::get('sess_useremail') != "" )
		{
			$GData['google_remarketing_codes'].= "<script type='text/javascript'>
				if(dataLayer.length > 0){
					dataLayer[0].SF_EmailUniqueId='".Session::get('sess_useremail')."';
				}else{
					dataLayer.push({
						'SF_EmailUniqueId': '".Session::get('sess_useremail')."'
					});
				}
			 </script>";
		}
		return $GData;
	}

	public function ApplyLoyaltyDiscount($StoreOrderArr,$WebsiteOrderArr)
	{
		$StoreLoylatyDiscont = 0;
		foreach($StoreOrderArr as $StoreItem)
		{
			if($StoreItem['OldProductPrice'] > 0)
				$StoreLoylatyDiscont+=($StoreItem['OldProductPrice'] - $StoreItem['ItemPrice']) * $StoreItem['Qty'];
		}
		Session::put('ShoppingCart.StoreLoyaltyDiscount', $StoreLoylatyDiscont);

		$WebsiteLoylatyDiscont = 0;
		foreach($WebsiteOrderArr as $WebsiteItem)
		{
			if($WebsiteItem['OldProductPrice'] > 0)
				$WebsiteLoylatyDiscont+=($WebsiteItem['OldProductPrice'] - $WebsiteItem['ItemPrice']) * $WebsiteItem['Qty'];
		}
		Session::put('ShoppingCart.WebsiteLoyaltyDiscount', $WebsiteLoylatyDiscont);
	}
}
