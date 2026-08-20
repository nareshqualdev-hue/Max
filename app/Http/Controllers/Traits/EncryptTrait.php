<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;

use DB;
use Session;

trait EncryptTrait
{	
	private $private_key;
	
	public function constructfunc()
	{
		$this->private_key = md5("PRENCRYPTKEY");
	}
	
	public function encrypt($str1)
	{
		$this->constructfunc();
		
		$len = strlen($str1);
		
		$encrypt_str = "";
		
		for($i=0; $i < $len; $i++) 
		{
			$char = substr($str1, $i, 1);
			
			$keychar = substr($this->private_key, ($i % strlen($this->private_key))-1, 1);
			
			$char = chr((ord($char)+ord($keychar))+ord('&'));
			
			$encrypt_str .= $char;
		}
		
		$encrypt_str = $this->encoding($encrypt_str);
		
		return $encrypt_str;
	}
	
	public function decrypt($string)
	{
		$this->constructfunc();
		
		//echo "<br>".$this->private_key."<br>";
		$string = $this->decoding($string);
		
		$len = strlen($string);
		
		$decrypt_str = "";
	
		for($i=0; $i < $len; $i++) 
		{
			$char = substr($string, $i, 1);
			
			$keychar = substr($this->private_key, ($i % strlen($this->private_key))-1, 1);
			
			$char = chr((ord($char)-ord($keychar))-ord('&'));
			
			$decrypt_str.=$char;

		}
	
		return $decrypt_str;
	}
	
	private function encoding($str)
	{
		return base64_encode(gzdeflate($str));
	}
	
	private function decoding($str)
	{
		return gzinflate(base64_decode($str));
	}
}
