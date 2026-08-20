var token = $('meta[name="csrf-token"]').attr('content');
$(document).ready(function () {
	if ($('.cart-table').length > 0) {
		$('html, body').animate({
			scrollTop: $("body").offset().top
		}, 500);

		//getRecommendedSideCart();
	}
	//DisplayPopupFreeGift();
	$('[data-toggle="tooltip"]').tooltip({html: true });
	/*
	if($("#shopcartpage").length > 0 || $('#proddetails').length > 0)
	{
		AfterpaySetup();
	}
	*/

	$(document).click(function(event) {
        if (!$(event.target).closest('#cart-open, .cart-link').length) {
			if($('#cart-open').css('right')=='0px'){
				$("#imgmodal").removeAttr("style");
				$('#cart-open').animate({
					right: '-480px'
				});
				$('body').toggleClass('slide-open');
				$("#imgmodal").attr("style","display:none;");
			}

        }
    });

	if (!sessionStorage.getItem("freeGiftClosed")) {
        if ($('.cart-table').length > 0) {
            $('html, body').animate({
                scrollTop: $("body").offset().top
            }, 500);
        }
        DisplayPopupFreeGift();
    }

    $(document).on('click', '#FreeGiftViewPopup .close', function () {
        sessionStorage.setItem("freeGiftClosed", "true");
    });

	if (!sessionStorage.getItem("freeSampleClosed")) {
        if ($('.cart-table').length > 0) {
            $('html, body').animate({
                scrollTop: $("body").offset().top
            }, 500);
        }
        DisplayPopupSampleProducts();
    }

	$(document).on('click', '#FreeSampleProductsViewPopup .close', function () {
        sessionStorage.setItem("freeSampleClosed", "true");
    });

});
$(document).on('click', ".addtocart", function () {
	var productID = $(this).attr('data-product');
	if($(this).attr('data-type') == 'sticky')
		//var prodqty = $(".sticky-qty #prodqty").val();
		var prodqty = $("#prodqty-sticky").val();
	else
		var prodqty = $("#prodqty").val();
	AddToCart(productID, prodqty);
});
$(document).on('click', ".prodaddcart", function () {
	var productID = $(this).attr('data-product');
	var prodqty = 1;
	AddToCart(productID, prodqty);
});
$(document).on('click', "#buyitnow", function () {
	var productID = $(this).attr('data-product');
	var prodqty = 1;
	AddToCart(productID, prodqty, 'buynow');
})
$(document).on('click', '#clear-bag', function () {
	ClearBag();
});
$(document).on('click',"#btnpaybycc", function(){
	$('#fund_type').val('card');
	$('#frmfund').submit();
});
$("#btnreorder").click(function(){
	var prodDetails = new Array();
	$(".chkitem").each(function (index, ele) {
		if($(this).prop('checked'))
		{
			var productID = $(this).val();
			var prodqty = $('#prodqty_'+productID).val();
			prodDetails.push({'productID' : productID, 'prodqty' : prodqty});
		}
	});
	if(prodDetails.length > 0 )
	{
		AddToCartForReorder(prodDetails);
	} else {
		alert('Please select product.');
		return false;
	}
});
$(document).on("click",".wproduct",function(){
	var productID = $(this).attr('id');
	var prodqty = $('#prodqty_'+productID).val();
	console.log(productID+'==='+prodqty);
	if(prodqty == '' || prodqty == '0')
	{
		alert('Please add product quantity');
		return false;
	}
	AddToCart(productID, prodqty);
});
$(".btnwproduct").click(function(){
	var prodDetails = new Array();
	$(".wprodqty").each(function (index, ele) {
		if($(this).val() != '' && $(this).val() != '0')
		{
			var ExpID = $(this).attr('id').split('_');
			var productID = ExpID[1];
			var prodqty = $(this).val();
			prodDetails.push({'productID' : productID, 'prodqty' : prodqty});
		}
	});
	if(prodDetails.length > 0 )
	{
		AddToCartForReorder(prodDetails);
	} else {
		alert('Please add quantity for product.');
		return false;
	}
})

$(document).on('click','#btnfreeSample', function(){

	var ProductIDVal = $('input[name="txtradio[]"]:checked').map(function() {
	  return $(this).val();
	}).get();

	var ProductID =  ProductIDVal.toString().replace(/,/g, ',');

	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			action: 'FreeSample',
			products_id: ProductID
		},
		success: function (data) {
			window.location.reload();
		}
	});
})

$(document).on('click','#btnfreegift', function(){
	var ProductIDVal = $('input[name="txtradio[]"]:checked').map(function() {
	  return $(this).val();
	}).get();
	var FreeGiftProductID = $("#freeproductsid").val();
	var ProductID =  ProductIDVal.toString().replace(/,/g, ',');
	var currentPage = $("#checkout-page").length > 0 ? "checkout" : "cart";

	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			action: 'FreeGift',
			products_id: ProductID,
			freeproductsid: FreeGiftProductID
		},
		success: function (data) {
			//window.location.href=site_url+"shoppingcart";
			$("#cover-spin").hide();
			if($("#Pagename").val()=="PaymentPage")
			{
				window.location.href = site_url + 'payment';
			}
			else if (currentPage === "checkout") {
			window.location.href = site_url + 'checkout';
            } else {
                window.location.href = site_url + 'shoppingcart';
            }
		}
	});
})
$(document).on('click', ".cartItemDel, .shopcartItemDel", function () {
	if(confirm('Are you sure you want to remove the item?'))
	{
        $("#page-spinner").show();
		var CartID = $(this).attr('data-index');
		var Page = "sidecart";
		if ($('.cart-table').length > 0) {
			$("#page-spinner").show();
			Page = "Shopcart";
		}
		$.ajax({
			type: 'POST',
			url: site_url + 'cart',
			headers: {
				'X-CSRF-TOKEN': token
			},
			datatype: 'JSON',
			data: {
				CartID: CartID,
				action: 'remove',
			},
			success: function (data) {
                if(data.IsYotpoFreeProduct && data.IsYotpoFreeProduct == 'Yes')
                {
                    $("#yotpo-loyalty-cart-data").attr('data-has-free-product',false);
                }
				GetCart();
				if (Page == "Shopcart") {
					GetCartPartial(data.message);
				}

				if($("#cart_items").length > 0){
					window.location.reload();
					return;
				}
				$("#page-spinner").hide();
				DisplayPopupFreeGift();
				DisplayPopupSampleProducts();

			}
		});
	}
});
var showspinner = 'Y';
$(document).on('click', '.cart-table .btn-number,#cart_items .btn-number',function (e) {
	e.preventDefault();

	var $btn = $(this);
	var $container;
	if ($btn.closest('.cart-table').length) {
		$container = $btn.closest('.cart-table');
    } else if ($btn.closest('#cart_items').length) {
		$container = $btn.closest('#cart_items');
	}

	fieldName = $(this).attr('data-field');
	type = $(this).attr('data-type');
	var input = $container.find("input[name='" + fieldName + "']"); //$(".cart-table input[name='" + fieldName + "']");
	var currentVal = parseInt(input.val());

	var rwUserType = $(this).attr('data-usertype');
	var userType = (typeof rwUserType === 'undefined' || rwUserType === '') ? 'retailer' : rwUserType.toLowerCase();
	var max = (userType === 'retailer') ? 20 : 9999;
	if (!isNaN(currentVal)) {
		if (type == 'minus') {
			showspinner = 'Y';
			if (currentVal > input.attr('min')) {
				input.val(currentVal - 1).change();
			}
			if (parseInt(input.val()) == input.attr('min')) {
				$(this).attr('disabled', true);
			}
		}
		else if (type == 'plus') {
			showspinner = 'Y';
			if (currentVal < max) {
                newVal = currentVal + 1;
                input.val(newVal).change();
            } else if (userType === 'retailer') {
				showspinner = 'Y';
				newVal = currentVal + 1;
                input.val(newVal).change();
            }
		}
		// else if (type == 'plus') {
		// 	//if (currentVal < input.attr('max')) {
		// 	input.val(currentVal + 1).change();
		// 	//}
		// 	/*if (parseInt(input.val()) == input.attr('max')) {
		// 		$(this).attr('disabled', true);
		// 	}*/
		// }
	} else {
		input.val(0);
	}
});
$(document).on('click', '.cart-table .btn-number,#cart_items .btn-number', function () {
	var ProductID = $(this).attr('data-product');
	//$("#page-spinner").show();
	if(showspinner == 'Y'){
		$("#page-spinner").show();
	}

	var isFromMiniCart = $(this).closest('#shopcart').length > 0;
	var from_mini_cart = 'N';
	if(isFromMiniCart == true){
		from_mini_cart = 'Y';
	}

	//setTimeout(UpdateCart, 1000, ProductID);
	setTimeout(function() {
        UpdateCart(ProductID,'No', from_mini_cart);
    }, 1000);
})

$(document).on('input propertychange paste', '.cart-table .input-number,#cart_items .input-number', function () {
	var ExpProd = $(this).attr('id').split('_');
	var ProductID = ExpProd[1];
	$("#page-spinner").show();

	var isFromMiniCart = $(this).closest('#shopcart').length > 0;
	var from_mini_cart = 'N';
	if(isFromMiniCart == true){
		from_mini_cart = 'Y';
	}
	setTimeout(function() {
        UpdateCart(ProductID,'No', from_mini_cart);
    }, 1000);

	//setTimeout(UpdateCart, 1000, ProductID);
})

$(document).on('click', '.clsgiftbox', function () {
	var giftwrap = 'No';
	if ($(this).prop('checked')) {
		giftwrap = 'Yes';
	}
	var ProductID = $(this).attr('data-product');
	UpdateCart(ProductID, giftwrap);
})
$(document).on('click', "#btncouponapply", function () {
	ApplyCouponCode();
	$('html, body').animate({
		scrollTop: $("body").offset().top
	}, 500);
});
$(document).on('click', "#btncredit", function () {
	ApplyCreditLimit();
	$('html, body').animate({
		scrollTop: $("body").offset().top
	}, 500);
});
$(document).on('click', "#btnremovecred", function () {
	RemoveCreditLimit();
	$('html, body').animate({
		scrollTop: $("body").offset().top
	}, 500);
});

$(document).on('click', "#btngiftcard", function () {
	ApplyGiftCoupon();
	$('html, body').animate({
		scrollTop: $("body").offset().top
	}, 500);
});
$(document).on('click', '.remdis', function () {
	var dataid = $(this).attr('data-id');
	if(dataid == 'CouponDiscount')
	{
		RemoveCouponDiscount();
	}
	if (dataid == 'GiftCoupon') {
		RemoveGiftCoupon();
	}
	if (dataid == 'YotpoRewardDiscount') {
		RemoveYotpoRewardDiscount();
	}

})
$(document).on("click",".max_coupon_box .coupan_boxhd", function(){
	$(this).parents(".max_coupon_box").toggleClass("active")
});
function AddToCart(productID, prodqty, btnFrom = '') {
    $("#page-spinner").show();
    var Page = $("main").attr('id');
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			products_id: productID,
			prodqty: prodqty,
			action: 'insert',
		},
        success:function(data){
            //console.log(data)
            $("#page-spinner").hide();
			if (btnFrom == 'buynow') {
				window.location.href = site_url + 'billing';
			} else {
				GetCart();

				$('#cart-open').animate({
					right: '0px'
				});

				if($('#cart-open').is(':visible')){
					$('body').addClass('slide-open');
				}else{
					$('body').removeClass('slide-open');
				}

				//$('body').toggleClass('slide-open');

				var getPage = '';

				if ($('.cart-table').length > 0) {
				$("#page-spinner").show();
					getPage = "Shopcart";
				}

				if (getPage == "Shopcart") {
					if ($("#shopcartpage").length > 0 || $("#checkout-page").length > 0)  {
						GetCartPartial();
					} else {
						GetCartPartial('','', 'add_to_cart');
					}
					//window.location.reload();
				}

				////////
				//getRecommendedSideCart();
				////////

				/*setTimeout(function() {
					setIntelliSuggestTrackingCart();
				}, 1000);*/
			}
        }
	});
}

function AddToCartForYotpoFreeGift(products_id,yotpo_free_gift_coupon) {
    var added = false;
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			products_id: products_id,
			action: 'yotpo_free_gift_insert',
            yotpo_free_gift_coupon: yotpo_free_gift_coupon,
		},
        async : false,
		success: function (data) {
			GetCart();
			$('#cart-open').animate({
				right: '0px'
			});
			$('body').toggleClass('slide-open');

            if(data.Added && parseInt(data.Added) == 1)
            {
                added = true;
            } else {
                added = false;
            }
		}
	});
    return added;
}

function AddToCartForReorder(prodDetails) {
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			prodDetails: prodDetails,
			action: 'reorder',
		},
		success: function (data) {
			GetCart();
			$('#cart-open').animate({
				right: '0px'
			});
			$('body').toggleClass('slide-open');
		}
	});
}
function UpdateCart(ProductID, giftwrap = 'No', is_from_minicart = 'N') {
	var prodqty = $("#prodqty_" + ProductID).val();
	if(is_from_minicart == 'N' && $('.cartitem').find('#prodqty_' + ProductID).length > 0){
		prodqty = $('.cartitem').find('#prodqty_' + ProductID).val(); //$("#prodqty_" + ProductID).attr('data-qty');
	}
	//$("#page-spinner").show();
	if(prodqty != '' && prodqty <= 0){
		$("#prodqty_" + ProductID).val("1");
		prodqty = 1;
	}
	if(showspinner == 'Y'){
		$("#page-spinner").show();
	}
	var isMiniCart = 'N';
	var isCheckoutPage = 'N';
	var isShoppingCartPage = 'N';
	if($("#isMiniCart").length > 0){
		isMiniCart = 'Y';
	}
	if($("#cart_items").length > 0){
		isCheckoutPage = 'Y';
	}
	if($(".cartpage-page").length > 0)
	{
		isShoppingCartPage = 'Y';
	}
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			products_id: ProductID,
			prodqty: prodqty,
			giftwrap: giftwrap,
			action: 'update',
		},
		success: function (data) {
			if(isCheckoutPage == 'Y'){
				window.location.reload();
				return;
				// if (data.CartErrors) {
				// 	window.location.href = site_url + 'shoppingcart';
				// 	return
				// } else {
				// 	window.location.reload();
				// 	return;
				// }
			}
			if (data.CartErrors) {
				var msg = '';
				for (var i = 0; i < data.CartErrors.length; i++) {
					msg += data.CartErrors[i] + '<br>';
				}

				var $input = $('#shopcartinfo').find('#prodqty_'+ProductID);
				if ($input.length) {
					var cart_old_qty = $input.attr('data-qty');
					$input.val(cart_old_qty);
				}

				var OldQty = $("#prodqty_" + ProductID).attr('data-qty');
				$("#prodqty_" + ProductID).val(OldQty);

				if ($('#cart-open').css('right') === '0px') {
					if($("#sidec.sp-content").length > 0){
				 		$("#sidec.sp-content").html('<div class="alert alert-danger" role="alert" style="">'+msg+'</div>');
				 	}
				}

				ShowMsg('error_cart', msg);
				$("#page-spinner").hide();
			} else {
				GetCart();
				if(isShoppingCartPage == 'Y' || isCheckoutPage == 'Y')
				{
					GetCartPartial();
				} else {
					GetCartPartial('','','update_side_cart');
				}
				DisplayPopupFreeGift();
				DisplayPopupSampleProducts();
			}
		}
	});
}
function GetCartPartial(message = '',msgtype='', pageFrom='') {
	$.ajax({
		type: 'POST',
		url: site_url + 'getshopcart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			pageFrom: pageFrom,
		},
		success: function (data) {
			if (data.TotalItemInCart > 0) {
				$("#alldata").html(data.Cart);
				$("#cartsubtotal").html(data.SubtotalBoxHTML);
				//$("#checkoutbox").html(data.CheckoutBoxHTML);
				$("#credcoupboxes").html(data.CreditCouponBoxesHtml);
				$("#page-spinner").hide();
				$("#shoptotal").html(data.Total);

				$("#setSectionMainCart").html(data.allSectionRecommendedPrds);

				//Show Bogo Discount Message
				$("#shopcart").html(data.SideCartHTML);
				//Show Bogo Discount Message

				if(msgtype == 'error')
					ShowMsg('error_cart', message);
				else
					ShowMsg('cart_msg', message);
				//AfterpaySetup();
			} else {
				$("#shopcart").html(data.SideCartHTML);
				$("#shopcartpage").html(data.EmptyCartHTML);
				$("#setSectionMainCart").hide();
			}
			$("#page-spinner").hide();
		}
	});
	return true;
}
function GetCart() {
	$.ajax({
		type: 'POST',
		url: site_url + 'getcart',
		headers: {
			'X-CSRF-TOKEN': token
		},
        async:false,
		datatype: 'JSON',
		data: {
			cartpopup: 1,
		},
		success: function (data) {
			$("#shopcart").html(data.ShoppingCart);
			var TotalItemInCart = data.TotalItemInCart;
			if (TotalItemInCart == 0)
				TotalItemInCart = '';
			if(TotalItemInCart != '' && TotalItemInCart > 0)
			{
				$(".cart-qty").show();
				$(".cart-qty").html(TotalItemInCart);
			} else {
				$(".cart-qty").hide();
				$(".cart-qty").html(TotalItemInCart);
			}

			////////
			/*setTimeout(function() {
				//getRecommendedSideCart();
				console.log($('.mini-products-slider').length);

				if (typeof $.fn.slick === 'function') {
					console.log("script loaded");
				}

				$('.mini-products-slider').slick();
			}, 2000); */
			////////

			/*
			if(data.MerchantID && data.MerchantID != '' )
			{
				var authRequest;
					OffAmazonPayments.Button("AmazonPayButtonAll", data.MerchantID, {
						type: "PwA",
						size: "large",
						authorization: function () {
							loginOptions = { scope: "profile postal_code payments:widget payments:shipping_address", popup: true };
							authRequest = amazon.Login.authorize(loginOptions, data.CallBackURL);
					},
					onError: function (error) {
						// something bad happened
					}
				});
			}
			*/
		}
	});
}
function ClearBag() {
	if(confirm('Are you sure you want to remove all items?'))
	{
		$.ajax({
			type: 'POST',
			url: site_url + 'cart',
			headers: {
				'X-CSRF-TOKEN': token
			},
			datatype: 'JSON',
			data: {
				action: 'clear_bag',
			},
			success: function (data) {
				window.location.reload();
			}
		});
	}
}
function ApplyCouponCode() {
	var coupon_number = $("#coupon_number").val();
	if (coupon_number == "") {
		alert("Please enter coupon");
		return false;
	}
	$("#page-spinner").show();
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			coupon_number: coupon_number,
			action: 'apply_coupon',
		},
		success: function (data) {
			if (data.CartErrors) {
				$("#page-spinner").hide();
			} else {
                if(!$("#btncouponapply").hasClass('checkout'))
                {
                    GetCart();
                    var msgType = '';
                    if(data.error && data.error == 1)
                        msgType = 'error';
                    GetCartPartial(data.message,msgType);
                    $("#page-spinner").hide();
                } else {
                    window.location.reload();
                }
			}
		}
	});
}

function ApplyCreditLimit() {
	$("#page-spinner").show();
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			action: 'apply_credit_limit',
		},
		success: function (data) {
			if (data.CartErrors) {
				$("#page-spinner").hide();
			} else {
				GetCart();
				GetCartPartial(data.message);
				if(data.NetTotal > 0)
				{
					$("#Paymethods").show();
				} else {
					$("#Paymethods").hide();
				}
			}
		}
	});
}
function RemoveCreditLimit() {
	$("#page-spinner").show();
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			action: 'remove_credit_limit',
		},
		success: function (data) {
			if (data.CartErrors) {
				$("#page-spinner").hide();
			} else {
				GetCart();
				GetCartPartial(data.message);
				if(data.NetTotal > 0)
				{
					$("#Paymethods").show();
				} else {
					$("#Paymethods").hide();
				}
			}
		}
	});
}
function ApplyGiftCoupon() {
	var giftcard = $("#txtgiftcard").val();
	if (giftcard == "") {
		alert("Please enter gift card");
		return false;
	}
	$("#page-spinner").show();
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			giftcard: giftcard,
			action: 'apply_gift_coupon',
		},
		success: function (data) {
			if(!$("#btncouponapply").hasClass('checkout'))
			{

				GetCart();
				var msgType = '';
				if(data.error && data.error == 1)
					msgType = 'error';
				GetCartPartial(data.message,msgType);
			} else {
				window.location.reload();
			}
		}
	});
}

function RemoveCouponDiscount() {
	$("#page-spinner").show();
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			action: 'remove_coupon',
		},
		success: function (data) {
			if (data.CartErrors) {
				$("#page-spinner").hide();
			} else {
                if($(".remdis").hasClass('checkout'))
                {
                    window.location.reload();
                } else {
                    GetCart();
				    GetCartPartial(data.message);
                }
			}
		}
	});
}

function RemoveYotpoRewardDiscount() {
	$("#page-spinner").show();
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			action: 'remove_yotporeward',
		},
		success: function (data) {
			if (data.CartErrors) {
				$("#page-spinner").hide();
			} else {
				if($(".remdis").hasClass('checkout'))
                {
                    window.location.reload();
                } else {
                    GetCart();
				    GetCartPartial(data.message);
                }

			}
		}
	});
}

function RemoveGiftCoupon() {
	$("#page-spinner").show();
	$.ajax({
		type: 'POST',
		url: site_url + 'cart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
			action: 'remove_gift_coupon',
		},
		success: function (data) {
			if (data.CartErrors) {
				$("#page-spinner").hide();
			} else {
				if($(".remdis").hasClass('checkout'))
                {
                    window.location.reload();
                } else {
                    GetCart();
				    GetCartPartial(data.message);
                }
			}
		}
	});
}

function ShowMsg(msgid, message) {
	if (message != '') {
		$("#" + msgid).show().html(message);
	}
}

function DisplayPopupSampleProducts(){
	if ($("#shopcartpage").length > 0 || $("#checkout-page").length > 0)  {
		var token = $('meta[name="csrf-token"]').attr('content');
		$.ajax({
				type: 'POST',
				url: site_url + 'get_freesample_products',
				data: { },
				headers: {
					'X-CSRF-TOKEN': token
				},
				success: function (data) {
					if (data) {
						if (typeof $.fn.modal === 'undefined') {
							$.getScript(modal_js, function () {
								$("#cover-spin").hide();
								$("#FreeSampleProductsViewPopup").html(data);
								$("#FreeSampleProductsViewPopup").modal('show');
							});
						}
						else
						{
							$("#cover-spin").hide();
							$("#FreeSampleProductsViewPopup").html(data);
							$("#FreeSampleProductsViewPopup").modal('show');
						}
					}
				}
			});
	}
}

function DisplayPopupFreeGift() {
	if ($("#shopcartpage").length > 0 || $("#checkout-page").length > 0)  {
		//var TotalValue = parseFloat($(".business_page").attr('data-total'));
		//if (TotalValue > 0) {
			var token = $('meta[name="csrf-token"]').attr('content');
			$("#FreeGiftViewPopup").html('');
			$("#cover-spin").show();
			$.ajax({
				type: 'POST',
				url: site_url + 'get_freegift_products',
				data: { },
				headers: {
					'X-CSRF-TOKEN': token
				},
				success: function (data) {
					if (data) {

					if (typeof $.fn.modal === 'undefined') {
					$.getScript(modal_js, function () {
					$("#cover-spin").hide();
							$("#FreeGiftViewPopup").html(data);
							$("#FreeGiftViewPopup").modal('show');
					});
				}
				else
				{
					$("#cover-spin").hide();
							$("#FreeGiftViewPopup").html(data);
							$("#FreeGiftViewPopup").modal('show');
				}

					}
				}
			});
		//}
	}
}

/*
function loadIntelliSuggestTrackingScript(lib) {
  var script = document.createElement('script');
  script.type = 'text/javascript';
  script.setAttribute('src', lib);
  document.getElementsByTagName('head')[0].appendChild(script);
  return script;
}

function isLoadedScript(lib) {
  return document.querySelectorAll('[src="' + lib + '"]').length > 0
}

$(".header_icon .cart-link").click(function() {
	setIntelliSuggestTrackingCart();
});

function setIntelliSuggestTrackingCart(){
	$.ajax({
		type: 'POST',
		url: site_url + 'setIntelliSuggestTrackingCart',
		headers: {
			'X-CSRF-TOKEN': token
		},
		datatype: 'JSON',
		data: {
		},
		success: function (data) {
			//console.log(data);
			if(data != ''){

				if (!isLoadedScript('//cdn.searchspring.net/intellisuggest/is.min.js')) {
					loadScript(lib);
					document.head.appendChild(script);
				}

				var getData = data.split("@@@");
				var mySkuArr = JSON.parse(getData[0]);
				var myIDArr = JSON.parse(getData[1]);
				var myPriceArr = JSON.parse(getData[2]);
				var myQtyArr = JSON.parse(getData[3]);
				var skus = getData[4];

				try{
					IntelliSuggest.init({siteId: 'faltym', context: 'Basket', seed: [skus]});

					for(var i=0;i<mySkuArr.length;i++){
						IntelliSuggest.haveItem({
							uid: myIDArr[i],
							childUid: '',
							sku: mySkuArr[i],
							childSku: '',
							price: myPriceArr[i],
							qty: myQtyArr[i]
						});
					}
					IntelliSuggest.setCurrency({ code: 'USD' });
					IntelliSuggest.inBasket({});
				} catch(err) {}
			}
		}
	});
}
*/

function getRecommendedSideCart(){
	$('.mini-products-slider').on('init', function(event, slick){
		$('.cover-spin').hide();
		//$('.sectionMiniCart').find(".cover-spin").hide();
	});

	$('.mini-products-slider').slick({
		lazyLoad: 'ondemand',
		dots: true,
		infinite: false,
		speed: 300,
		arrows: true,
		centerMode: true,
		centerPadding: '0',
		slidesToShow: 1,
		slidesToScroll: 2,
		/*responsive: [
			{ breakpoint: 1600, settings: { slidesToShow: 5, slidesToScroll: 5 } },
			{ breakpoint: 1200, settings: { slidesToShow: 4, slidesToScroll: 4 } },
			{ breakpoint: 992, settings: { slidesToShow: 3, slidesToScroll: 3 } },
			{ breakpoint: 768, settings: { slidesToShow: 2, slidesToScroll: 2 } }
		],*/
		nextArrow: '<button class="slick-next round-btn-sl" aria-label="Next recommended product"><svg class="svg svg-slick-right" aria-hidden="true" width="24" height="24" role="img"><use href="#sv-right-arrow" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-right-arrow"></use></svg></button>',
		prevArrow: '<button class="slick-prev round-btn-sl" aria-label="Previous recommended product"><svg class="svg svg-slick-left" width="24" height="24" aria-hidden="true" role="img"><use href="#sv-left-arrow" xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#sv-left-arrow"></use></svg></button>',
	});
}
