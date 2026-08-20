<table class="table text-left table-border-none table-borderless mb-0" role="table" aria-label="Order Subtotal and Charges">
    <tbody>
        <tr>
            <td>Subtotal</td>
            <td class="text-right">
                @if(isset($CartDetails['SubTotal']) && $CartDetails['SubTotal'] > 0)
                {{Price($CartDetails['SubTotal'])}}
                @else
                $0.00
                @endif
            </td>
        </tr>
        @foreach($AllCharges as $key => $Charge)
            @if($Charge['charge'] > 0)
            <tr>
                <td>{{$Charge['label']}}</td>
                <td class="text-right">{{Price($Charge['charge'])}}</td>
            </tr>
            @endif
        @endforeach
        @foreach($AllDiscounts as $key => $Discount)
            @if($Discount['discount'] != '' && $Discount['discount'] > 0)
            <tr>
                <td>
                    @if(auth()->guard('store')->check())
                        @if($CurrentRoute != 'billing-payment')
                            @if(isset($Discount['Ricon']) && $Discount['Ricon'] == 'Yes')
                                <a class="label-cart-outstock remdis checkout" href="javascript:void(0);" rel="nofollow" data-id="{{$Discount['dataid']}}" aria-label="Remove {{$Discount['label']}}" role="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                                    </svg>
                                </a>
                            @endif
                        @endif
                    @else
                        @if(isset($Discount['Ricon']) && $Discount['Ricon'] == 'Yes')
                            <a class="label-cart-outstock remdis checkout" href="javascript:void(0);" rel="nofollow" data-id="{{$Discount['dataid']}}" aria-label="Remove {{$Discount['label']}}" role="button">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                                </svg>
                            </a>
                        @endif
                    @endif
                    {{$Discount['label']}}
                </td>
                <td class="text-right">-{{Price($Discount['discount'])}}</td>
            </tr>
            @endif
        @endforeach
        <tr class="cart-total">
            <td id="total-amount-label">Total Amount</td>
            <td class="text-right">
				<!-- <input type="hidden" name="min_ap_amt" value="@if(Session::has('Afterpay.Min_AP_AMT')){{Session::get('Afterpay.Min_AP_AMT')}}@endif" id="min_ap_amt">
				<input type="hidden" name="max_ap_amt" value="@if(Session::has('Afterpay.Max_AP_AMT')){{Session::get('Afterpay.Max_AP_AMT')}}@endif" id="max_ap_amt"> -->
                <strong id="net_total_amt" data-amt="{{$NetTotal}}" aria-labelledby="total-amount-label">{{Price($NetTotal)}}</strong>
            </td>
        </tr>
    </tbody>
</table>
<script type="text/javascript">
{!! $GAShippingTag ?? '' !!}
</script>