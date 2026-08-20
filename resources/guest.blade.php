<div id="guest1" role="region" aria-label="Guest Checkout Section">
    <div class="row">  
        <div class="col-12" id="divguest">
            <form action="{{config('global.SITE_URL')}}login.html" class="mb-0" method="post" id="formguest" aria-labelledby="guest-checkout-heading">
			 @if(session('ShoppingCart.OrderType') === 'Store' && Auth::guard('store')->check())	
		    <input type="hidden" name="onlyGCPurchasedVal" id="onlyGCPurchasedVal" value="{{$CartAttr['onlyGCPurchased']}}" />
            <input type="hidden" name="OrderType" id="OrderType" value="{{Session::get('ShoppingCart.OrderType')}}"/>
            <input type="hidden" name="takeactiong" id="takeactiong" value="TakeAction" />
             <input type="hidden" name="OnlyHeadval" id="OnlyHeadval" value= @if(session('ShoppingCart.OrderType') === 'Store')"0" @endif/>	
			<input type="hidden" name="BillingSkipVariable" id="BillingSkipVariable" value="{{ session('BillingSkipVariable') === 'Yes' ? 'Yes' : '' }}" />			
			<input type="hidden" name="BillingSkipEmail" id="BillingSkipEmail" value="{{ session('BillingSkipEmail') === 'Yes' ? 'Yes' : '' }}" />            
             @endif                
                {{ csrf_field() }} 
                <div class="row">
                    <div class="col-12">
                        @if(Auth::guard('store')->check())
                            <input type="hidden" name="issStore" id="issStore" value="Y" />
                        @endif
                        <h2 id="guest-checkout-heading" class="sr-only">Guest Checkout</h2>
                                                       @if(session('ShoppingCart.OrderType') === 'Store' && Auth::guard('store')->check())
                                <b>Skip Address Information </b>
                                <label class="switch active mb-2" id="insurance">
										<input type="checkbox" id="BillingSkip" value="{{ session('BillingSkipVariable') != 'No' ? 'Yes' : '' }}" {{ session('BillingSkipVariable') != 'No' ? 'checked' : '' }}>
										<span class="slider round"></span>
									</label>
								<br/><b> Skip Email Address </b>	
							   <label class="switch active mb-2" id="insurance">
										<input type="checkbox" id="SkipEmailValidation" value="{{ session('BillingSkipEmail') == 'Yes' ? 'Yes' : '' }}" {{ session('BillingSkipEmail') == 'Yes' ? 'checked' : '' }}>
										<span class="slider round"></span>
									</label>	
                                @endif
                       
                        <div class="form-group required">
                            <label for="guest_email">
                            @if(Auth::guard('store')->check())    
                            Email Address / Name / Phone Number
                            @else
                            Email Address
                            @endif
                        </label>
                            <input type="email" name="guest_email" id="guest_email" class="form-control" 
							   required aria-required="true" aria-label="Email Address" 
							   value="{{ !empty(session('ShoppingCart.Store.Email')) ? session('ShoppingCart.Store.Email') : ($Billing['email'] ?? '') }}">
                               <div class="autocomplete_icons_checkout">
                               <i class="bi bi-x clear-input d-none" style="padding:10px;"></i>
                               <i class="bi bi-chevron-down dropdown-arrow d-none"></i>
                               <span class="input-loader"></span></div>
                            <x-message :attr="[ 'classname' => 'frmerror', 'message' => '', 'mid' => 'error_guest_email']" />
                            @if ($errors->has('email'))
                                <x-message :attr="[ 'classname' => 'frmerror frmerror_shw', 'message' => $errors->first('guest_email') ]" />
                            @endif
                            
                           <p id="chlabelval">
							@if(Auth::guard('store')->check() && session('BillingSkipEmail') == 'Yes') 
								This order will be linked to the store email address
							@elseif(!Auth::guard('store')->check())
								No account? That's fine - checkout as a guest.
							@endif
                            
							</p>
                        </div>
                         @if(session('ShoppingCart.OrderType') === 'Store' && Auth::guard('store')->check())
                         <div class="form-group required" id="fname">
                            <label for="firstname">First Name</label>
                            <input type="text" name="firstname" id="firstname" class="form-control"   aria-label="First Name" value="{{ session('ShoppingCart.Store.FirstName') ?? '' }}">
                        </div>
                        <div class="form-group required" id="lname">
                            <label for="lastname">Last Name</label>
                            <input type="text" name="lastname" id="lastname" class="form-control"   aria-label="Last Name" value="{{ session('ShoppingCart.Store.LastName') ?? '' }}">
                        </div>
                        
                         <div class="form-group required" id="cphone">
                            <label for="phonenumber">Phone Number</label>
                            <input type="number" name="phonenumber" id="phonenumber" class="form-control"  aria-label="Phone Number" value="{{ session('ShoppingCart.Store.Phone') ?? '' }}">
                        </div>
                        @endif
                        
                    </div>
                    <div class="col-12">
                        <label class="checkbox-label f12 lheight-18 mb-0" for="chknews1" aria-label="Subscribe to Max Aroma news and offers">
                            <div class="chebox">
                                <input type="checkbox" id="chknews1" name="chknews1" checked="" aria-checked="true"><span class="checkmark"></span>
                            </div>
                            Keep me up to date with Max Aroma news, promotions, and exclusive offers by email and SMS. I
                            can update my preferences at any time by contacting Max Aroma or using unsubscribe links.
                        </label>
                    </div>
                    <div class="col-12">
                        <div class="chekout-main-btn" role="region" aria-label="Continue as Guest Button Section">
                            <div class="mt-md-3 pt-md-1 mb-md-3">
                                

                                

                                <a class="max_continuesp_btn btn btn-primary w-100" id="btnguest" href="javascript:void(0)" role="button" aria-label="Continue Address">Continue Address</a>
                                <a class="max_continuesp_btn btn btn-primary w-100" id="btnasguest" href="javascript:void(0)" style="display:none;" role="button" aria-label="Continue as Guest">Continue as Guest</a>
                                
                            </div>
                        </div>
                    </div>
                    <div class="col-12 d-md-none">
                        <div class="mobile-shadow mb-0" aria-hidden="true">&nbsp;</div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script type="text/javascript">
    var sess_storeuseremail = "<?php echo Session::get('sess_store_email') != '' ? Session::get('sess_store_email') : ''; ?>";
    window.storeAuth = {
        check: @json(Auth::guard('store')->check()),
    };
</script>
