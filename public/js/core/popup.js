/*
* Sales Offer Pop-Up
*/
function DisplayPopupOffer() {

    var token = $('meta[name="csrf-token"]').attr('content');
    $("#sales-offers-popup").html('');
    $("#page-spinner").show();
    $.ajax({
        type: 'POST',
        url: site_url + 'get_sales_offers',
        headers: {
            'X-CSRF-TOKEN': token
        },
        success: function (data) {
            if (data) {
                $("#page-spinner").hide();
                $("#sales-offers-popup").html(data);
            }
        }
    });
}

function DisplaySecondBarPopup(){
    $('#SecondBarPopup').modal('show');
}

/*
* Instant PopUp code 
*/
var coupon_flag = "<?= config('global.INSTANT_COUPON_POPUP_FLAG') ?>";
if(coupon_flag == "Yes"){
    showAddPopUp();
}
function showAddPopUp() {
    var flg = $("#coupon-modal-popup_ajax").attr('data-status');

    if (flg == null || flg == '') {
        $.ajax({
            type: 'POST',
            url: site_url + "show_popup",
            data: "flag=No",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (data) {

                if (data) {
                    $("#coupon-modal-popup_ajax").html(data);
                    $('#coupon-modal-popup').modal('show');
                    setTimeout(function () {
                        $('#coupon-modal-popup').modal('hide')
                    }, 150000);
                }
            },
            error: function (err) {
                console.log(err);
            },
        });
    }
}

/*
* WishList Popup Box
*/
function DisplayPopupBoxWishlist(products_id, ispopup) {
    $("#myModalPopUpLogin").html('');
    $("#page-spinner").show();
    var str = "products_id=" + products_id + "&isAction=" + 'wish_login' + "&isPopup=" + "Yes";

    $.ajax({
        type: "POST",
        url: site_url + "wishlist_add",
        data: str,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }

    }).done(function (msg) {
        $("#page-spinner").hide();
        $("#myModalPopUpLogin").html(msg);
        $('#myModalPopUpLogin').modal('show');
    });
}

/*
* Forgot Password code
*/
function ShowForgetPassword() {
    var str = "isAction=wish_forget" + "&isPopup=" + "Yes";
    $.ajax({
        type: "POST",
        url: site_url + "wishlist_add",
        data: str,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $("#myModalPopUpLogin").html('');
            $("#myModalPopUpLogin").html(response);
        }
    })
}

/*
* WishList Popup code
*/
function showWishCat() {
    var data = "isAction=wish_category";
    $.ajax({
        type: "POST",
        url: site_url + "wishlist_add",
        data: data,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $("#myModalPopUpLogin").html('');
            $("#myModalPopUpLogin").html(response);
        }
    })
}


/*
* NicheFragrances Popup
*/
function NicheFragrances() {
    var data = "";
    $.ajax({
        type: "POST",
        url: site_url + "niche_fragrance_membership",
        data: data,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $("#NicheFragrancesPopup").html('');
            $("#NicheFragrancesPopup").html(response);
            $('#NicheFragrancesPopup').modal('show');
        }
    })
}

/*
* Product Alert Me PopUp code
*/
function DisplayPopupBoxAlertMe(products_id, sku) {
    $("#ProductAlertMePopup").html('');
    $("#page-spinner").show();
    var str = "products_id=" + products_id + "&sku=" + sku;

    $.ajax({
        type: "POST",
        url: site_url + "product_alert_me",
        data: str,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }

    }).done(function (msg) {
        $("#page-spinner").hide();
        $("#ProductAlertMePopup").html(msg);
        $('#ProductAlertMePopup').modal('show');
    });
}

/*
* Email A Friend
*/
$(document).on("click", '#emailafriend', function () {
    var productId = $(this).data("pid");
    var str = "productId=" + productId;
    $("#page-spinner").show();
    $.ajax({
        type: "POST",
        url: site_url + "email_friend",
        data: str,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }

    }).done(function (msg) {
        $("#page-spinner").hide();
        $("#EmailFriendPopup").html(msg);
        $('#EmailFriendPopup').modal('show');
    });
});

/*
* Email A Friend
*/
// $(document).on("click", '.productratingsreview', function () {
//     var productId = $(this).data("pid");
//     var str = "productId=" + productId;
//     $("#page-spinner").show();
//     $.ajax({
//         type: "POST",
//         url: site_url + "ratings_review",
//         data: str,
//         headers: {
//             'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
//         }

//     }).done(function (msg) {
//         $("#page-spinner").hide();
//         $("#ProductRatingsReviewPopup").html(msg);
//         $('#ProductRatingsReviewPopup').modal('show');
//     });
// });

/*
* Login - Product Details
*/
$(".openloginpopup").click(function () {
    var productId = $(this).data("pid");
    var str = "productId=" + productId;
    $("#page-spinner").show();
    $.ajax({
        type: "POST",
        url: site_url + "login_pdetail_page",
        data: str,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }

    }).done(function (data) {
        $("#page-spinner").hide();
        $("#myModalPopUpLogin").html(data);
        $('#myModalPopUpLogin').modal('show');
    });
});

function openQuickViewModal() {
    var $modal = $("#ProductQuickViewPopup");

    if (document.activeElement) {
        document.activeElement.blur();
    }

    $(".modal-backdrop").remove();

    $modal
        .addClass("in show")
        .attr("aria-hidden", "false")
        .attr("aria-modal", "true")
        .css("display", "block");

    $("body").addClass("modal-open");

    $("body").append('<div class="modal-backdrop fade in show"></div>');
}

function closeQuickViewModal() {
    var $modal = $("#ProductQuickViewPopup");

    if (document.activeElement) {
        document.activeElement.blur();
    }

    $modal
        .removeClass("in show")
        .attr("aria-hidden", "true")
        .removeAttr("aria-modal")
        .hide()
        .html("");

    $(".modal-backdrop").remove();
    $("body").removeClass("modal-open").css("padding-right", "");
}

$(document).on("click", "#ProductQuickViewPopup [data-dismiss='modal'], #ProductQuickViewPopup .close, .modal-backdrop", function () {
    closeQuickViewModal();
});

/*
* Product - Quick View
*/

function productquickview(pid, cid = 0) {
    var productId = pid;
    var str = "productId=" + productId + "&category_id=" + cid;
    var $modal = $("#ProductQuickViewPopup");

    $("#page-spinner").show();
    
    if (document.activeElement) {
        document.activeElement.blur();
    }

    $modal.html('');
    $modal
        .removeClass("in show")
        .removeAttr("style")
        .removeAttr("aria-hidden")
        .removeAttr("aria-modal");

    $(".modal-backdrop").remove();
    $("body").removeClass("modal-open").css("padding-right", "");

    $.ajax({
        type: "POST",
        url: site_url + "product_quick_view",
        data: str,
        cache: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    }).done(function (data) {

        $("#page-spinner").hide();

        $modal.html(data);

        if (typeof Yotpo !== "undefined" && typeof yotpo !== "undefined") {
            var api = new Yotpo.API(yotpo);
            api.refreshWidgets();
        }

        checkReview(productId);

        //$modal.modal('show');
        openQuickViewModal();
        setTimeout(function () {
            if (!$('.yotpo.bottomLine').find('.yotpo-stars').length) {
                $('.yotpo.bottomLine').html(
                    '<div class="yotpo-bottomline pull-left star-clickable">' +
                        '<span class="yotpo-stars">' +
                            '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span>' +
                            '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span>' +
                            '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span>' +
                            '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span>' +
                            '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span>' +
                        '</span>' +
                        '<div class="yotpo-clr"></div>' +
                    '</div>'
                );
            }
        }, 1500);

    }).fail(function () {
        $("#page-spinner").hide();
    });
}

function productquickview_bk1(pid,cid=0) {
    var productId = pid;
    var str = "productId=" + productId+"&category_id=" + cid;
    $("#page-spinner").show();
    $.ajax({
        type: "POST",
        url: site_url + "product_quick_view",
        data: str,
        cache: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }

    }).done(function (data) {
        $("#ProductQuickViewPopup").html('');
        $("#page-spinner").hide();
        $("#ProductQuickViewPopup").html(data);
        var api = new Yotpo.API(yotpo);
        api.refreshWidgets();  
        checkReview(productId);
        //$('#ProductQuickViewPopup').modal('show');

        setTimeout(() => {            
            if (!$('.yotpo.bottomLine').find('.yotpo-stars').length) {
                $('.yotpo.bottomLine').html(
                    '<div class="yotpo-bottomline pull-left  star-clickable">' +
                        '<span class="yotpo-stars">' +
                        '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span>' +
                        '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span>' +
                        '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span>' +
                        '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span>' +
                        '<span class="yotpo-icon yotpo-icon-empty-star pull-left"></span></span>'+
                        '<div class="yotpo-clr"></div>'+
                    '</div>'
                );
            }

        }, 1500);

    });
}

function checkReview(productId){
    var i = 1;
    var checkData = setInterval(function() {
        if ($('#ProductQuickViewPopup').find("a.text-m").length == 0) {
            //if(i == 2){
                //console.log("not found"+i);
                $("#star_rating").show();
                $("#prd_review").show();
                $("#qk_pipe").show();
                $("#ask_question").show();
                clearInterval(checkData);
                $('#ProductQuickViewPopup').modal('show');
            //}    
        } else {
            //console.log("found");
            //$("#star_rating_"+productId).hide();
            clearInterval(checkData);
            $("#yotpo_write_review").show();
            $("#yotpo_qk_pipe").show();
            $("#yotpo_ask_question").show();
            $('#ProductQuickViewPopup').modal('show');
        }
        i++;
      }, 1000);
    
      
}

$(document).on('click', '#quickviewaddtocart_sort_button .btn-number', function (e) {
    e.preventDefault();
    fieldName = $(this).attr('data-field');

    type = $(this).attr('data-type');
    var input = $("input[name='" + fieldName + "']");
    var currentVal = parseInt(input.val());
    //var userType = $(this).attr('data-usertype') === '' ? 'retailer' : $(this).attr('data-usertype').toLowerCase();

    var rwUserType = $(this).attr('data-usertype');
    var userType = (typeof rwUserType === 'undefined' || rwUserType === '') ? 'retailer' : rwUserType.toLowerCase();

    if (userType === 'retailer') {
        max = (navailable_stock < 20) ? navailable_stock : 20;
    } else if (userType === 'wholesaler') {
        max = navailable_stock;
    } else {
        max = 9999;
    }

    if (!isNaN(currentVal)) {
        if (type == 'minus') {
            if (currentVal > input.attr('min')) {
                input.val(currentVal - 1).change();
            }
            // if (parseInt(input.val()) == input.attr('min')) {
            //     $(this).attr('disabled', true);
            // }
        } 
        else if (type == 'plus') {
            if (currentVal < max) {
                newVal = currentVal + 1;
                input.val(newVal).change();
            } else if (userType === 'retailer' || userType === 'wholesaler') {
                alert("The maximum quantity you can add is "+max+" pieces.");
                return;
            }
        // else if (type == 'plus') {
        //     input.val(currentVal + 1).change();
        //     //if (currentVal < input.attr('max')) {
        //     input.val(currentVal + 1).change();
        //     //}
        //     // if (parseInt(input.val()) == input.attr('max')) {
        //     //     $(this).attr('disabled', true);
        //     // }
        }
    } else {
        input.val(0);
    }
    GetWholesalePricePopup(input.val(),userType);
});

function GetWholesalePricePopup(quantity=1,usertype='retailer')
{    
	//var usertype = $("#proddetails").attr('data-type');
	if(usertype.toLowerCase() == 'wholesaler')
	{
		//$("#page-spinner").show();
		var products_id = $(".btn-addtocart").attr('data-product');
		$.ajax({
			type: 'POST',
			url: site_url + 'get-wholesale-price',
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			},
			datatype: 'JSON',
			data: {
				products_id: products_id,
				quantity: quantity
			},
			success: function (data) {
				//$("#page-spinner").hide();
				if(data.Price)
				{
					if($(".prodprice").attr('data-flag') == 'deal_price')
					{
						$(".prodprice").html('Deal Price: '+data.Price);
					} else {
						$(".prodprice").html(data.Price);
					}
				}
			}
		});
	}
    return true;
}

/*
* Product Cancel Order PopUp code
*/
function DisplayPopupBoxCancelOrder(orders_id) {
    $("#myModalPopUpCancelOrder").html('');
    $("#page-spinner").show();
    var str = "orders_id=" + orders_id;

    $.ajax({
        type: "POST",
        url: site_url + "cancel_order",
        data: str,
        cache: false,
        dataType: "html",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (msg) {
            $("#page-spinner").hide();
            $("#myModalPopUpCancelOrder").html(msg);
            $('#myModalPopUpCancelOrder').modal('show');
        });
}

/*
* Product Cancel Order PopUp code for track order page
*/
function DisplayPopupBoxCancelTrackOrder(orders_id) {
    $("#myModalPopUpCancelOrder").html('');
    $("#page-spinner").show();
    var str = "orders_id=" + orders_id + "&action=trackorder";

    $.ajax({
        type: "POST",
        url: site_url + "cancel_order",
        data: str,
        cache: false,
        dataType: "html",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (msg) {
            $("#page-spinner").hide();
            $("#myModalPopUpCancelOrder").html(msg);
            $('#myModalPopUpCancelOrder').modal('show');
        });
}


/*
* Product Return Order PopUp code
*/
function DisplayPopupBoxReturnOrder(orders_id,qty,order_details_id) {
    $("#myModalPopUpReturnOrder").html('');
    $("#page-spinner").show();
    var str = "orders_id=" + orders_id + "&qty="+qty+ "&order_details_id="+order_details_id;

    $.ajax({
        type: "POST",
        url: site_url + "return_order",
        data: str,
        cache: false,
        dataType: "html",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (msg) {
            $("#page-spinner").hide();
            $("#myModalPopUpReturnOrder").html(msg);
            $('#myModalPopUpReturnOrder').modal('show');
        });
}

/*
* Product Return Track Order PopUp code
*/
function DisplayPopupBoxReturnTrackOrder(orders_id,qty,order_details_id) {
    $("#myModalPopUpReturnOrder").html('');
    $("#page-spinner").show();
    var str = "orders_id=" + orders_id + "&qty="+qty+ "&order_details_id="+order_details_id+ "&action=trackorder";

    $.ajax({
        type: "POST",
        url: site_url + "return_order",
        data: str,
        cache: false,
        dataType: "html",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (msg) {
            $("#page-spinner").hide();
            $("#myModalPopUpReturnOrder").html(msg);
            $('#myModalPopUpReturnOrder').modal('show');
        });
}

/*
* Add Fund Popup
*/

$(document).on('click',"#btnAdddFund, #btnDropshipAdddFund, #btnAdddFundBilling, .add-fund, #btnImportDropshipOrders",function () {
	var pageFrom = 'shoppingcart';
	if($(this).attr('id') == 'btnDropshipAdddFund')
		pageFrom = 'dropshipfund';
	else if($(this).attr('id') == 'btnAdddFundBilling' || $(this).hasClass('add-fund'))
		pageFrom = 'billing';
	else if($(this).attr('id') == 'btnImportDropshipOrders')
		pageFrom = 'import-dropship-orders';
	else
		pageFrom = 'shoppingcart';
    addFundPopUp(pageFrom);
});
function addFundPopUp(pageFrom) {
    var data = "";
    $.ajax({
        type: "POST",
        url: site_url + "add_fund",
        data: {pageFrom : pageFrom},
        cache: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $("#AddFundPopup").html('');
            $("#AddFundPopup").html(response);
			$("#page_from").val(pageFrom);
			var paypal_return = site_url + 'paypal_fund_response/'+pageFrom
			$("#paypal_return").val(paypal_return);
            $('#AddFundPopup').modal('show');
			var call_url= $("#amazon_fund_callback").val();
			var authRequest;
				OffAmazonPayments.Button("AmazonPayFundButton", "{{config('MERCHANT_ID')}}", {
					type: "PwA",
					size: "medium",
					authorization: function () {
						loginOptions = { scope: "profile postal_code payments:widget payments:shipping_address", popup: true };
						authRequest = amazon.Login.authorize(loginOptions, call_url);
				},
				onError: function (error) {
					// something bad happened
				}
			});
			$("#AmazonPayFundButton").hide();
			$("#AmazonPayFundButton").addClass('mt-3 ml-3');
        }
    })
}

/*
* Add Fund Popup
*/

$(document).on("click", '.shippingCalculate', function () {
    addShippingCalculate();
});
function addShippingCalculate() {
    var data = "";
    $.ajax({
        type: "POST",
        url: site_url + "shipping_calculate",
        // data: data,
        cache: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $("#ShippingCalculatePopup").html('');
            $("#ShippingCalculatePopup").html(response);
            $('#ShippingCalculatePopup').modal('show');
        }
    })
}


function DisplayFreeShippingPopUp() {
    var data = "";
    $.ajax({
        type: "POST",
        url: site_url + "free_shipping",
        // data: data,
        cache: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $("#FreeShippingPopup").html('');
            $("#FreeShippingPopup").html(response);
            $('#FreeShippingPopup').modal('show');
        }
    })
}

function DisplayShippingServicePopUp() {
    var data = "";
    $.ajax({
        type: "POST",
        url: site_url + "shipping_service",
        // data: data,
        cache: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $("#ShippingServicePopup").html('');
            $("#ShippingServicePopup").html(response);
            $('#ShippingServicePopup').modal('show');
        }
    })
}

function DisplayWholesalerShippingPolicyPopUp() {
    var data = "";
    $.ajax({
        type: "POST",
        url: site_url + "wholesaler_shipping_policy",
        // data: data,
        cache: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $("#WholesalerShippingPolicyPopup").html('');
            $("#WholesalerShippingPolicyPopup").html(response);
            $('#WholesalerShippingPolicyPopup').modal('show');
        }
    })
}

$(document).on("click", '.signinsignup', function () {
    var dataaction = $(this).attr("data-action");
    var checkloginPop ="";
   
    if($("#checkloginPop").val()!='')
    {
		checkloginPop = $("#checkloginPop").val();
    }
    $("#signInSignUp").html('');
    var txtemail = "";
    if($(this).attr('id') == 'rewardpoint')
    {
        $("#error_email").hide();
        txtemail = $('#txtemail').val();
        txtemail = txtemail.trim();
        var mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
        /*
        if(txtemail == "")
        {
            $("#error_email").html("Please enter email address!");
            $("#error_email").show();
            $('#txtemail').focus();
            return false;
        }*/
        if(txtemail != '' && !txtemail.match(mailformat))
        {
            $("#error_email").html("Please enter valid email address!");
            $("#error_email").show();
            $('#txtemail').focus();
            return false;
        }
    }
    $("#page-spinner").show();
    $.ajax({
        type: "POST",
        //url: site_url + 'signin_signup',
		url: site_url + 'signin_signup_new',
         data: {isAction : dataaction,checkloginPopval : checkloginPop},
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    
    }).done(function (msg) {
        $("#page-spinner").hide();
        $("#signInSignUp").html(msg);
        $("#email").val(txtemail);
        $('#signInSignUp').modal('show');
    });
});

function DisplayWholesalerTerms() {
    var token = $('meta[name="csrf-token"]').attr('content');
    $("#WholesalerTerms").html('');
    $("#page-spinner").show();
    $.ajax({
        type: 'POST',
        url: site_url + 'wholesaler-terms',
        headers: {
            'X-CSRF-TOKEN': token
        },
        success: function (data) {
            if (data) {
                $("#page-spinner").hide();
                $("#WholesalerTerms").html(data);
				$('#WholesalerTerms').modal('show');
            }
        }
    });
}

/*
* Product Claim Order PopUp code
*/
function DisplayPopupBoxClaimOrder(orders_id,qty,order_details_id,billfname,billlname,billemail,orders_no) {
    $("#myModalPopUpClaimOrder").html('');
    $("#page-spinner").show();
    var str = "orders_id=" + orders_id + "&qty="+qty+ "&order_details_id="+order_details_id+ "&billfname="+billfname+ "&billlname="+billlname+ "&billemail="+billemail+ "&orders_no="+orders_no;
	
    $.ajax({
        type: "POST",
        url: site_url + "claim_order",
        data: str,
        cache: false,
        dataType: "html",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (msg) {
            $("#page-spinner").hide();
            $("#myModalPopUpClaimOrder").html(msg);
            $('#myModalPopUpClaimOrder').modal('show');
        });
}

function DisplayPopupBoxClaimOrderTrackOrder(orders_id,qty,order_details_id,billfname,billlname,billemail,orders_no) {
    $("#myModalPopUpClaimOrder").html('');
    $("#page-spinner").show();
    var str = "orders_id=" + orders_id + "&qty="+qty+ "&order_details_id="+order_details_id+ "&billfname="+billfname+ "&billlname="+billlname+ "&billemail="+billemail+ "&orders_no="+orders_no+"&action=trackorder";
	
    $.ajax({
        type: "POST",
        url: site_url + "claim_order",
        data: str,
        cache: false,
        dataType: "html",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (msg) {
            $("#page-spinner").hide();
            $("#myModalPopUpClaimOrder").html(msg);
            $('#myModalPopUpClaimOrder').modal('show');
        });
}

function DisplayPopupBoxClaimedOrder(orders_id) {
    $("#myModalPopUpClaimedOrder").html('');
    $("#page-spinner").show();
    var str = "orders_id=" + orders_id;
	
    $.ajax({
        type: "POST",
        url: site_url + "claimed_order",
        data: str,
        cache: false,
        dataType: "html",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (msg) {
            $("#page-spinner").hide();
            $("#myModalPopUpClaimedOrder").html(msg);
            $('#myModalPopUpClaimedOrder').modal('show');
        });
}

function DisplayPopupBoxClaimPoliceReportOrder(orders_id,is_police_report) {
    $("#myModalPopUpPoliceReport").html('');
    $("#page-spinner").show();
    var str = "orders_id=" + orders_id+"&is_police_report="+is_police_report;
	
    $.ajax({
        type: "POST",
        url: site_url + "claimed_order_police_report",
        data: str,
        cache: false,
        dataType: "html",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
        .done(function (msg) {
            $("#page-spinner").hide();
            $("#myModalPopUpPoliceReport").html(msg);
            $('#myModalPopUpPoliceReport').modal('show');
        });
}

// Return functionality popup
function DisplayPopupBoxReturnOrderNew(orderId, custEmail, requestPage=''){
	$("#myModalPopUpReturnOrder").html('');
    $("#page-spinner").show();
    var str = "orders_id=" + orderId + "&customer_email=" + custEmail + "&requestedPage="+requestPage;
	
    $.ajax({
        type: "POST",
        url: site_url + "return_item_popup",
        data: str,
        cache: false,
        dataType: "html",
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    })
    .done(function (msg) {
		$("#page-spinner").hide();
		$("#myModalPopUpReturnOrder").html(msg);
		$('#myModalPopUpReturnOrder').modal('show');
	});
}

/*
* Product Claim Track Order PopUp code
*/

$(document).on('click', '#claimtermclose', function() {
    $(".modal.claim-term").removeClass('in')
      .attr('aria-hidden', true)
      .hide()
      .off('click.dismiss.bs.modal')
      .off('mouseup.dismiss.bs.modal');
      
    if($(".modal-backdrop").length > 1){
		$(".modal-backdrop:last").remove();
	}
});

$(document).on('click',"#agreepolicy",function () {console.log('dsadas');
	if($(this).is(":checked")){
		$("#myModalPopUpClaimTerms").html('');
		$("#page-spinner").css('z-index', 99999);
		$("#page-spinner").show();
		
		$.ajax({
			type: "POST",
			url: site_url + "claim_terms_popup",
			cache: false,
			dataType: "html",
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			}
		})
		.done(function (msg) {
			$("#page-spinner").css('z-index', 9999);
			$("#page-spinner").hide();
			$("#myModalPopUpClaimTerms").show().html(msg).addClass('in');
			$('#myModalPopUpClaimTerms').modal('show');
		});
	}
});
$(document).on("click", '.insuranceSignature', function () {
    $("#paypal-button-container-checkout").hide();
    $("#gpay_applepay_paypal").text("Gpay/ApplePay");
    $("#payment-request-button").show();
    $('#ShippingSignInsu').modal('show');
});

