<div class="max_coupon_box" role="region" aria-label="Account Balance Section">
    <div class="coupan_boxhd">
        <div class="float-left text-left">
            <label class="comcheck d-inline-block w-100 checkbox-label" for="chkcreditlimit" aria-label="Use your account balance">
                <div class="chebox">
                    @if($CreditDiscount > 0 && $CartAttr['CreditLimitFlag'] == 2)
                        <input type="checkbox" checked name="chkcreditlimit" id="chkcreditlimit" value="{{$CartAttr['RemainCreditLimit']}}" aria-checked="true" aria-labelledby="credit-balance-label" />
                        <span class="checkmark"></span>
                        <span class="float-left w-100" id="credit-balance-label">
                            Use your account balance, Your account balance is : {{Price($CartAttr['RemainCreditLimit'])}}
                        </span>
                    @else
                        <input type="checkbox" name="chkcreditlimit" id="chkcreditlimit" value="{{$CartAttr['CreditLimit']}}" aria-checked="false" aria-labelledby="credit-balance-label" />
                        <span class="checkmark"></span>
                        <span class="float-left w-100" id="credit-balance-label">
                            Use your account balance, Your account balance is : {{Price($CartAttr['CreditLimit'])}}
                        </span>	
                    @endif
                </div>
            </label>
        </div>
    </div>
</div>