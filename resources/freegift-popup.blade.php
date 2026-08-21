<div class="vertical-alignment-helper" role="dialog" aria-modal="true" aria-labelledby="pick-gift-title" aria-describedby="pick-gift-desc">
    <div class="modal-dialog vertical-align-center">
        <div class="modal-content">
            <a class="close" type="button" data-dismiss="modal" aria-label="Close dialog">
                <svg class="sv-close vam" aria-hidden="true" role="img" width="16" height="16">
                    <use href="#sv-close" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-close"></use>
                </svg>
            </a>
            <div class="modal-body pick-gift">
                <div class="friend-mail-modal">
                    <div class="modal-hd">
                        <h1 id="pick-gift-title">PICK YOUR GIFT</h1>
                    </div>
                    <div class="modal-space" id="pick-gift-desc">
                        @php $counter = 0; @endphp
                        <div class="alert alert-success text-center" role="alert" aria-live="polite">
                            @if ($TotalListItems > 1)
                            	You can select only up to {{$TotalListItems}} items from the list.
                            @else
                            	You can select only up to one item from the list.
                            @endif
                        </div>
                        <div class="row row5 pb-2 d-flex justify-content-center flex-wrap" role="list" aria-label="Free Gift Options">
                            @foreach($Free_Gift_Res as $key => $Product)
                            <div class="col-sm-3 col-6 text-center pb-2" role="listitem">
                                <div class="pick-box">
                                    <label class="radio" aria-label="Select {{ $Product['product_name'] }}">
                                        <div class="pick-images pb-2 pt-2">
                                            <div class="chebox">
                                                <div class="gift-checkbox">
                                                    <input type="hidden" name="productsid{{$key}}" id="productsid{{$key}}" value="{{$Product['products_id']}}">
                                                    <input type="hidden" name="freeproductsid" id="freeproductsid" value="{{$Product['free_gift_products_id']}}">
                                                    @if(isset($Product["FoundSku"]) && $Product["FoundSku"]=="No")
                                                    <input type="checkbox" class="rdofreegift" name="txtradio[]" id="txtradio{{$key}}" value="{{$Product['products_id']}}" onclick="return freegiftselect({{$Product['freegift_add_count']}})" aria-labelledby="gift-label-{{$key}}">
                                                    @endif	
                                                    <input type="hidden" name="sku{{$key}}" id="sku{{$key}}" value="{{$Product['sku']}}">
                                                    @if(isset($Product["FoundSku"]) && $Product["FoundSku"]=="No")
                                                    <span class="checkmark" style="border-radius:1px !important"></span>
                                                    @else
                                                        @php
                                                            $counter++;
                                                        @endphp
                                                    @endif
                                                </div>
                                                <img width="90" height="90" title="{{$Product['product_name']}}" alt="{{$Product['product_name']}}" src="{{ config('global.SPEED_SIZE_URL')}}{{$Product['thumb_image']}}" role="img" aria-label="Image of {{ $Product['product_name'] }}">
                                            </div>
                                        </div>
                                        <div class="pick-text" id="gift-label-{{$key}}">
                                            <div class="pb-2">{{ getMetaTitleDescription($Product['product_name']) }}</div>			  
                                            <div class="pb-2"><strong>Item SKU: </strong>{{ $Product['sku'] }}</div>
                                            <div class="pb-2">{{ getMetaTitleDescription($Product['short_description']) }}</div>
                                        </div>
                                    </label>
                                </div>	
                            </div>
                            @endforeach
                            <input type="hidden" name="CountValue" value="{{$counter}}" id="CountValue">		
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div class="pb-3 text-center">
                                    <x-button btype="button" btntext="Add To Cart" classname="btn btn-primary" bid="btnfreegift" aria-label="Add selected gifts to cart" />
                                </div>
                            </div>	
                        </div>
                    </div> 
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
	function freegiftselect(total=0){
		chkCnt = 0;
		$("INPUT[id^='txtradio'][type=checkbox]:checked").each(function(){
			chkCnt += 1;
		});
		chkCnt = chkCnt + parseInt($("#CountValue").val());
		if(chkCnt > total ) {
			if(total==1)
			{
				alert("You can select only up to one item from the list.");
			}
			else
			{
				alert("You can select only up to "+total + " items from the list.");
			}
			return false;
		}
	}
</script>
