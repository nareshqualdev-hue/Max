<?php
namespace App\Http\Controllers\Traits;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Products;
use App\Models\ProductsReview;
use DB;
use Session;
trait VendorTrait
{
	public function getSystemStockAvalilable($Product, $OrderType = "Website")
	{
		if ($OrderType === "Store") {
			return ($Product->store_currentStock ?? 0) > 0 ? "In" : "Out";
		}
		$websiteStockAvailable = $Product->current_stock > $Product->minimum_stock;
		
		$vendors = [
						['stock' => $Product->cosmo_current_stock ?? 0, 'sku' => $Product->cosmo_sku ?? ''],
						['stock' => $Product->nandansons_current_stock ?? 0, 'sku' => $Product->nandansons_sku ?? ''],
						['stock' => $Product->pca_current_stock ?? 0, 'sku' => $Product->pca_sku ?? ''],
						['stock' => $Product->perfumeworldwide_currentstock ?? 0, 'sku' => $Product->perfumeworldwide_sku ?? ''],
						['stock' => $Product->nd_current_stock ?? 0, 'sku' => $Product->nd_sku ?? ''],
					];

		
		
		$vendorHasStock = false;
		foreach ($vendors as $vendor) {
			if ($vendor['stock'] > 0 && $vendor['sku'] !== '') {
				$vendorHasStock = true;
				break;
			}
		}
		if ($websiteStockAvailable || $vendorHasStock) {
			return "In";
		}
		return "Out";
	}       

	public function SetProduct($Product,$OrderType="Website")
	{
		
		if(isset($OrderType) && $OrderType=="Store")
		{
			$Product->stock = $this->getSystemStockAvalilable($Product,$OrderType);
			$Prices = explode("##", $this->getSystemStockProductPrice($Product,$OrderType));
			$Product->WebsiteStock = "Out";
			if($Product->stock=="In")
			{
				$Product->WebsiteStock = "In";
			}
			$Product->product_price = $Prices[0];
		}
		else
		{
			$Product->stock = $this->getSystemStockAvalilable($Product);  
			$Prices = explode("##", $this->getSystemStockProductPrice($Product));
			$Product->WebsiteStock = $this->getSystemWebsiteCurrentStock($Product);
			$Product->product_price = $Prices[0];
			
			if($Product->product_price > $Product->retail_price)
			{
				$Product->retail_price = $Product->product_price;
			}
		}
		$Product->retail_price = isset($Prices[1]) ? $Prices[1] : 0;
		$Product->current_stock = isset($Prices[2]) ? $Prices[2] : 0;
		return $Product;
	} 
	public function getReferencedProducts_Counter_ListingDev($products_id, $variation_id, $category_id = '', $CatArrVal = array(), $ReffProds)
	{
		$cnt_referance_prod = [];
		if (!$CatArrVal) {
			$CatArrVal = [];
		}
		if ($ReffProds && $ReffProds->count() > 0) {
			foreach ($ReffProds as $Product) {
				if ($Product->products_id == $products_id)
					continue;
				if ($Product->variation_id != $variation_id)
					continue;

				$Product = $this->SetProduct($Product);
				if ($Product->is_atomizer == "Yes" && $Product->stock == "Out")
					continue;
				if ($Product->stock == "Out" && $Product->is_atomizer == "No")
					continue;

				if ($Product->is_atomizer == "No") {
					if ($Product->stock == "In" && $Product->category_id == $category_id) {
						$cnt_referance_prod[0] = $Product;
						if (count($CatArrVal) > 0) {
							$isAtom1 = 'No';
							for ($j = 0; $j < count($CatArrVal); $j++) {
								if ($CatArrVal[$j] != 68 && $CatArrVal[$j] != 70 &&  $CatArrVal[$j] != 71 &&  $CatArrVal[$j] != 69) {
									$isAtom1 = 'Yes';
								}
							}
							if ($isAtom1 == 'Yes') {
								break;
							}
						} else {
							break;
						}
					} else if ($Product->stock == "In") {
						$cnt_referance_prod[0] = $Product;
					} else {
						if ($cnt_referance_prod[0]->is_atomizer != 'Yes' && $cnt_referance_prod[0]->stock != 'In') {
							$cnt_referance_prod[0] = $Product;
						}
					}
				} else {

					if ($Product->stock == "In" && isset($cnt_referance_prod[0]) && ($cnt_referance_prod[0]->stock != 'In' || in_array(68, $CatArrVal) || in_array(70, $CatArrVal) || in_array(71, $CatArrVal) || in_array(69, $CatArrVal))) {
						$cnt_referance_prod[0] = $Product;
						$isAtom = 'No';
						for ($j = 0; $j < count($CatArrVal); $j++) {
							if ($CatArrVal[$j] == 68 || $CatArrVal[$j] == 70 ||  $CatArrVal[$j] == 71 ||  $CatArrVal[$j] == 69) {
								$isAtom = 'Yes';
							}
						}
						if ($isAtom == 'Yes')
							break;
					}
				}
			}
		}
		if (count($cnt_referance_prod) <= 0) {
			$cnt_referance_prod = $ReffProds->count();
		}
		if ($ReffProds->count() <= 1) {
			$cnt_referance_prod = 1;
		}
		return $cnt_referance_prod;
	}

	public function getReferencedProducts_CounterDev($products_id, $variation_id, $ReffProds)
	{
		$cnt_referance_prod = 0;
		foreach ($ReffProds as $Product) {

			$Product = $this->SetProduct($Product);
			if ($Product->variation_id == $variation_id) {
				$cnt_referance_prod++;
			}
		}
		if ($cnt_referance_prod > 0)
			return $cnt_referance_prod;
		else
			return 1;
	}
	public function setPriceRange($variation_id, $Products)
	{
		return $this->GetMinMaxPrice($variation_id, $Products);
	}

	public function GetMinMaxPrice($variation_id, $Products)
	{
		$Price = [];
		$YouSave = [];	
		foreach ($Products as $ObjProduct) {
			if (isset($ObjProduct->variation_id) && isset($variation_id) && $ObjProduct->variation_id != $variation_id)
				continue;
			$Product = $ObjProduct;
			$Price[] = $Product->product_price;
			$NewPrice = (int)$Product->retail_price - (int)$Product->product_price;
			if ($NewPrice > 0 )
				$Save = (($Product->retail_price - $Product->product_price) / $Product->retail_price) * 100;
			else
				$Save = 0;
			$YouSave[] = $Save;

		}
		$MinPrice = min($Price);
		$MaxPrice = max($Price);
		$PriceArray = ['MinPrice' => $MinPrice, 'MaxPrice' => $MaxPrice, 'YouSave' => max($YouSave)];
		return $PriceArray;
	}

	
    public function getSystemStockProductPrice($Product,$OrderType="Website")
	{
		
	   if(isset($OrderType) && $OrderType=="Store")
		{
			if($Product->sale_price > 0 &&  $Product->sale_price < $Product->our_price) {
					return $Product->sale_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
				} else {
					if ($Product->our_price > 0)
						return $Product->our_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
				}
		}	
		
	  $isWholesaler = Session::get('eusertype') && strtolower(Session::get('eusertype')) === 'wholesaler';
	
	  if ($Product->current_stock > $Product->minimum_stock) {
        if ($isWholesaler) {
            if ($Product->wholesale_price > 0) {
                return $Product->wholesale_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
            }
            if ($Product->our_price > 0) {
                return $Product->our_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
            }
        } else {
            if ($Product->sale_price > 0 && $Product->sale_price < $Product->our_price) {
                return $Product->sale_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
            }
            if ($Product->our_price > 0) {
                return $Product->our_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
            }
        }
    }

	
	$vendors = [
				'cosmo' => [
					'sku' => $Product->cosmo_sku ?? '',
					'stock' => $Product->cosmo_current_stock ?? 0,
					'price' => $isWholesaler ? ($Product->cosmo_wholesale_price ?? 0) : ($Product->cosmo_our_price ?? 0),
					'retail_price' => $Product->cosmo_retail_price ?? 0,
				],
				'pca' => [
					'sku' => $Product->pca_sku ?? '',
					'stock' => $Product->pca_current_stock ?? 0,
					'price' => $isWholesaler ? ($Product->pca_wholesale_price ?? 0) : ($Product->pca_our_price ?? 0),
					'retail_price' => $Product->retail_price ?? 0,
				],
				'nandansons' => [
					'sku' => $Product->nandansons_sku ?? '',
					'stock' => $Product->nandansons_current_stock ?? 0,
					'price' => $isWholesaler ? ($Product->nandansons_wholesale_price ?? 0) : ($Product->nandansons_our_price ?? 0),
					'retail_price' => $Product->retail_price ?? 0,
				],
				'nd' => [
					'sku' => $Product->nd_sku ?? '',
					'stock' => $Product->nd_current_stock ?? 0,
					'price' => $isWholesaler ? ($Product->nd_wholesale_price ?? 0) : ($Product->nd_our_price ?? 0),
					'retail_price' => $Product->nd_retail_price ?? 0,
				],
				'perfumeworldwide' => [
					'sku' => $Product->perfumeworldwide_sku ?? '',
					'stock' => $Product->perfumeworldwide_currentstock ?? 0,
					'price' => $isWholesaler ? ($Product->perfumeworldwide_wholesale_price ?? 0) : ($Product->perfumeworldwide_our_price ?? 0),
					'retail_price' => $Product->perfumeworldwide_retail_price ?? 0,
				],
			];
	

		$availableVendors = array_filter($vendors, function($v) {
			return $v['sku'] !== '' && $v['stock'] > 0 && $v['price'] > 0;
		});

		if (!empty($availableVendors)) {
			uasort($availableVendors, fn($a, $b) => $a['price'] <=> $b['price']);
			$bestVendorName = array_key_first($availableVendors);
			$bestVendor = $availableVendors[$bestVendorName];

			return "{$bestVendor['price']}##{$bestVendor['retail_price']}##{$bestVendor['stock']}";
		}

		if ($isWholesaler) {
			if ($Product->wholesale_price > 0) {
				return $Product->wholesale_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
			}
			if ($Product->our_price > 0) {
				return $Product->our_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
			}
		} else {
			if ($Product->sale_price > 0 && $Product->sale_price < $Product->our_price) {
				return $Product->sale_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
			}
			if ($Product->our_price > 0) {
				return $Product->our_price . "##" . $Product->retail_price . "##" . $Product->current_stock;
			}
		}
	}
	
	
	
	public function getSystemWebsiteCurrentStock($Product)
	{
		$currentStock = $Product->current_stock ?? 0;
		$minimumStock = $Product->minimum_stock ?? 0;

		return ($currentStock > $minimumStock) ? "In" : "Out";
	}
	function Jsn_Chtr_Remove($str)
	{
		$str1 = '';
		$str1 = str_replace("\n", "\\n", $str);
		$str1 = str_replace("\r", "", $str1);
		$str1 = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/', '$1"$3":', $str1);
		$str1 = preg_replace('/(,)\s*}$/', '}', $str1);
		$str1 = str_replace("'", "", $str);
		return $str1;
	}
	function clean_new($string)
	{
		$string = preg_replace('/[^A-Za-z0-9\- . ?,\' ; @ : \/n]/', '', $string); // Removes special chars.
		return preg_replace('/-+/', '-', $string); // Replaces multiple hyphens with single one.
	}
	public function getProductReview($sku)
	{
		$result =  ProductsReview::select(
			'review_id',
			'products_id',
			'sku',
			'star_rate',
			'first_name',
			'city',
			'state',
			'country',
			'user_review',
			'customer_id',
			'date',
			'approved',
			'ip_address',
			'email'
		)->where('approved', '=', 'Yes')
			->where('star_rate', '!=', '0')
			->where('sku', '=', $sku)
			->orderBy('review_id', 'DESC')->get();
			//->offset(0)->limit(8)->get();
		if (count($result) > 0) {
			for ($p = 0; $p < count($result); $p++) {
				$average_rate = ceil($result[$p]->star_rate);
				if ($average_rate > 5)
					$average_rate = 5;
				$result[$p]['date'] = date('F d,Y ', strtotime($result[$p]->date));
				$result[$p]['user_review'] = $this->clean_new($result[$p]->user_review);
				$result[$p]['average_rate'] = $average_rate;
			}
		}
		return $result;
	}
	public function getProductReviewByStar($sku, $totalreview)
	{
		$result =  ProductsReview::select(
			'review_id',
			'products_id',
			'sku',
			'star_rate',
			'first_name',
			'city',
			'state',
			'country',
			'user_review',
			'customer_id',
			'date',
			'approved',
			'ip_address',
			'email',
			DB::raw('COUNT(star_rate) as star')
		)->where('approved', '=', 'Yes')
			->where('star_rate', '!=', '0')
			->where('sku', '=', $sku)
			->groupBy('star_rate')
			->orderBy('star_rate', 'DESC')
			->get();

		$reviewBystar = array();
		$star = array();
		$p = 0;
		if (count($result) > 0) {
			for ($p; $p < count($result); $p++) {
				$reviewpercentage = ($result[$p]->star * 100)/($totalreview);
				$reviewBystar[$p]['reviewpercentage'] = $reviewpercentage;
				$reviewBystar[$p]['starcount'] = $result[$p]->star;
				$reviewBystar[$p]['star_rate'] = (int) $result[$p]->star_rate;
				array_push($star, (int) $result[$p]->star_rate);
			}
		}
		$newkey = $p;
		for ($m = 5; $m > 0; $m--) {
			if (!in_array($m, $star)) {
				$reviewBystar[$newkey]['starcount'] = 0;
				$reviewBystar[$newkey]['star_rate'] = $m;
				$reviewBystar[$newkey]['reviewpercentage'] = 0;
				$newkey++;
			}
		}
		return $reviewBystar;
	}
	public function getProductAverageRating($sku)
	{
		$average_rate = 0;
		$result =  ProductsReview::select('star_rate')->where('approved', '=', 'Yes')
			->where('star_rate', '!=', '0')
			->where('sku', '=', $sku)
			->orderBy('review_id', 'DESC')->get();
		$TotalReview = count($result);
		$TotalRate = 0;
		if (count($result) > 0) {
			for ($p = 0; $p < count($result); $p++) {
				$TotalRate = $TotalRate + $result[$p]['star_rate'];
			}
			$average_bottom = (int)@($TotalRate / $TotalReview);
			$average_real = @($TotalRate / $TotalReview);
			if (($average_real - $average_bottom) >= 0.5)
				$average_rate = ceil($TotalRate / $TotalReview);
			else
				$average_rate =  $average_bottom;
			if ($average_rate > 5)
				$average_rate = 5;
		}
		return $average_rate;
	}
	function uniqueAssocArray($array, $uniqueKey)
	{
		if (!is_array($array)) {
			return array();
		}
		$uniqueKeys = array();
		$uniqueKeys1 = array();
		foreach ($array as $key => $item) {
			if (!in_array($item->$uniqueKey, $uniqueKeys)) {
				$uniqueKeys[$item->$uniqueKey] = (array)$item;
			}
		}
		$new_arr = array_values($uniqueKeys);
		return $new_arr;      
	}
}
