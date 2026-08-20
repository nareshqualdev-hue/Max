<?php
namespace App\Http\Controllers\Traits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Traits\EncryptTrait;
use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;

use App\Models\PaymentMethod;
use App\Models\StoreCardReader;

use DB;
use Session;
trait StripeTrait
{
    use EncryptTrait;
    public function GetStripeKey()
    {
        $STRIPE_KEY = "";
        $PUBLISH_KEY = "";

        $sess_useremail = Session::get('sess_useremail') ?? '';

        $db_res = PaymentMethod::select('pm_details')
                        ->where('pm_group_name','PAYMENT_STRIPE')
                        ->where('pm_status','Active')
                        ->first();

        if ($db_res) {
            $arrPEVar = unserialize($db_res->pm_details);
            $STRIPE_KEY = $this->decrypt($arrPEVar['Secret_Key']);
            $PUBLISH_KEY = $this->decrypt($arrPEVar['Publishable_Key']);
        }
        $devEmails = [
            'wgequaldev@gmail.com',
            'gequaldev@gmail.com',
            'qqualdev@gmail.com',
            'testing12345678@gmail.com',
            'tetn@gmail.com',
            'maxqual11@gmail.com',
            'tempchecknew1223@gmail.com',
            //'hamed@maxaroma.com'
        ];

        if (in_array(strtolower($sess_useremail), $devEmails))
        {
        }

        //Sandbox Keys
        //$STRIPE_KEY = config('services.stripe.secret');
        //$PUBLISH_KEY = config('services.stripe.striptkey');
        return ['STRIPE_KEY' => $STRIPE_KEY, 'PUBLISH_KEY' => $PUBLISH_KEY];
    }
    public function GetCardReaderStatus($log=0)
	{
		$ReaderData = [];
		$ReaderData = ['status' => "OFFLINE"];
		$StripeKeyDetail = $this->GetStripeKey();
        $CustomerEmail="";
        if(Auth::guard('web')->check())
        {
            $CustomerEmail = Auth::guard('web')->user()->email;
        }
		if(Session::has('ConnectedCardReaderId') && !empty(Session::get('ConnectedCardReaderId')))
		{
			try{
				$stripe = new \Stripe\StripeClient($StripeKeyDetail['STRIPE_KEY']);
				$selReader = Session::get('ConnectedCardReaderId');
				$selReaderName = Session::get('ConnectedCardReaderName');
				$reader = $stripe->terminal->readers->retrieve($selReader, []);
				if(isset($reader->status))
				{
					$Status = strtoupper($reader->status);
					$ReaderData = [
						'status' => $Status,
						'reader_id' => $selReader,
						'reader_name' => $selReaderName
					];
				}
                if($log == 1)
                {
                    $message = $reader->toArray();
                    $this->ReaderLog('RETRIEVE_READER_STATUS',json_encode($message));
                }
			}catch (ApiErrorException $e) {
                if($log == 1)
                {
                    $message = $e->getHttpStatus()."#".$e->getStripeCode()."#".$e->getMessage();
                    $this->ReaderLog('RETRIEVE_READER_STATUS',$message);
                }
				$ReaderData = ['status' => "OFFLINE"];
			} catch (\Exception $e) {
                if($log == 1)
                {
                    $this->ReaderLog('RETRIEVE_READER_STATUS',$e->getMessage());
                }
				$ReaderData = ['status' => "OFFLINE"];
			}
		}
		return $ReaderData;
	}

    public function ReaderLog($event,$eventMessage)
    {
        if(empty($event) && empty($eventMessage))
        {
            return;
        }
        $CustomerEmail="";
        if(Auth::guard('web')->check())
        {
            $CustomerEmail = Auth::guard('web')->user()->email;
        }

        $PaymentLog = [
            'response' => $eventMessage,
            'event_name' => $event,
            'storeuser_email' => Auth::guard('store')->user()->store_user_email,
            'customer_email' => $CustomerEmail
        ];
        DB::table('pu_payment_logs')->insert($PaymentLog);
    }
}
