<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
/**
 * @property int        $customer_id
 * @property string     $first_name
 * @property string     $last_name
 * @property string     $user_name
 * @property string     $password
 * @property string     $email
 * @property string     $company_name
 * @property string     $address1
 * @property string     $address2
 * @property string     $phone
 * @property string     $fax
 * @property string     $city
 * @property string     $state
 * @property string     $zip
 * @property string     $country
 * @property int        $reg_datetime
 * @property int        $upd_datetime
 * @property string     $customer_ip
 * @property string     $customer_browser
 * @property string     $salestax_id
 * @property string     $annual_revenue
 * @property int        $iRewardpoint
 * @property string     $fb_social_url
 * @property int        $fb_id
 * @property string     $google_url
 * @property string     $google_id
 * @property string     $dropshipperfund_history
 * @property Date       $birthday
 * @property string     $hear_text
 * @property string     $youtube_text
 * @property string     $instagram_text
 * @property string     $email_text
 * @property string     $amazon_id
 * @property string     $einnumber
 * @property string     $referenced_by
 * @property string     $upload_sales_tax
 * @property string     $company_website
 * @property string     $stores
 * @property string     $webiste_url
 * @property string     $policy
 * @property string     $knowledge
 * @property string     $ftp_type
 * @property string     $ftp_host
 * @property string     $ftp_username
 * @property string     $ftp_password
 * @property int        $ftp_port
 * @property string     $ftp_file_path
 * @property int        $ftp_timestamp
 * @property string     $is_monday
 * @property string     $is_wednesday
 * @property string     $is_friday
 * @property string     $admin_note
 * @property string     $dropshipper_ftp_type
 * @property string     $dropshipper_ftp_host
 * @property string     $dropshipper_ftp_username
 * @property string     $dropshipper_ftp_password
 * @property int        $dropshipper_ftp_port
 * @property string     $dropshipper_ftp_file_path
 * @property string     $dropshipper_ftp_file_name
 * @property string     $dropshipper_ftp_picktime_order
 * @property int        $dropshipper_ftp_timestamp
 * @property Date       $dropship_enable_date
 * @property Date       $dropship_disable_date
 * @property Date       $wholesale_approve_date
 * @property string     $warehouse
 * @property int        $salesperson_id
 * @property string     $subadmin
 * @property string     $merge_log
 * @property string     $sf_accountid
 * @property string     $sf_contactid
 * @property Date       $sf_process_date
 * @property string     $code
 * @property string     $gender
 * @property string     $genderoption
 */
class Customer extends Authenticatable
{
	use HasFactory, Notifiable;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'pu_customer';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'customer_id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
		'first_name', 'last_name', 'user_name', 'password', 'email', 'company_name', 'address1', 'address2', 'phone', 'fax', 'city', 'state', 'zip', 'country', 'registration_type', 'reg_datetime', 'upd_datetime', 'status', 'customer_ip', 'customer_browser', 'eusertype', 'salestax_id', 'annual_revenue', 'eclub_member', 'iRewardpoint', 'is_fb', 'fb_social_url', 'fb_id', 'is_google', 'google_url', 'google_id', 'credit_limit', 'available_funds', 'is_dropshipper', 'dropshipperfund_history', 'birthday', 'hear_about', 'hear_text', 'youtube_text', 'instagram_text', 'email_text', 'is_amazon', 'amazon_id', 'einnumber', 'reward_discount_block', 'referenced_by', 'upload_sales_tax', 'company_website', 'stores', 'sell_online', 'website', 'webiste_url', 'policy', 'knowledge', 'ftp_type', 'ftp_host', 'ftp_username', 'ftp_password', 'ftp_port', 'ftp_file_path', 'ftp_flag', 'ftp_timestamp', 'ftp_sendfeed', 'send_email_feed', 'is_quantity', 'is_monday', 'is_wednesday', 'is_friday', 'is_newarrival_email', 'is_pricelist_email', 'is_mailchimp', 'is_fakeemail', 'admin_note', 'dropshipper_ftp_type', 'dropshipper_ftp_host', 'dropshipper_ftp_username', 'dropshipper_ftp_password', 'dropshipper_ftp_port', 'dropshipper_ftp_file_path', 'dropshipper_ftp_file_name', 'dropshipper_ftp_picktime_order', 'dropshipper_ftp_flag', 'dropshipper_ftp_timestamp', 'dropship_enable_date', 'dropship_disable_date', 'wholesale_approve_date', 'dropship_enable_sent_email', 'dropship_disable_sent_email', 'wholesale_sent_email', 'DownloadSpecialPricelist', 'warehouse', 'block_customer_flag', 'salesperson_id', 'assign_to_subadmin', 'subadmin', 'is_deleted', 'merge_log', 'payment_amount', 'sf_accountid', 'sf_contactid', 'sf_process_date', 'code', 'sf_process_status', 'download_product_price', 'gender', 'genderoption', 'omnisend_accountid'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'customer_id' => 'int', 'first_name' => 'string', 'last_name' => 'string', 'user_name' => 'string', 'password' => 'string', 'email' => 'string', 'company_name' => 'string', 'address1' => 'string', 'address2' => 'string', 'phone' => 'string', 'fax' => 'string', 'city' => 'string', 'state' => 'string', 'zip' => 'string', 'country' => 'string', 'reg_datetime' => 'timestamp', 'upd_datetime' => 'timestamp', 'customer_ip' => 'string', 'customer_browser' => 'string', 'salestax_id' => 'string', 'annual_revenue' => 'string', 'iRewardpoint' => 'int', 'fb_social_url' => 'string', 'fb_id' => 'int', 'google_url' => 'string', 'google_id' => 'string', 'dropshipperfund_history' => 'string', 'birthday' => 'date', 'hear_text' => 'string', 'youtube_text' => 'string', 'instagram_text' => 'string', 'email_text' => 'string', 'amazon_id' => 'string', 'einnumber' => 'string', 'referenced_by' => 'string', 'upload_sales_tax' => 'string', 'company_website' => 'string', 'stores' => 'string', 'webiste_url' => 'string', 'policy' => 'string', 'knowledge' => 'string', 'ftp_type' => 'string', 'ftp_host' => 'string', 'ftp_username' => 'string', 'ftp_password' => 'string', 'ftp_port' => 'int', 'ftp_file_path' => 'string', 'ftp_timestamp' => 'timestamp', 'is_monday' => 'string', 'is_wednesday' => 'string', 'is_friday' => 'string', 'admin_note' => 'string', 'dropshipper_ftp_type' => 'string', 'dropshipper_ftp_host' => 'string', 'dropshipper_ftp_username' => 'string', 'dropshipper_ftp_password' => 'string', 'dropshipper_ftp_port' => 'int', 'dropshipper_ftp_file_path' => 'string', 'dropshipper_ftp_file_name' => 'string', 'dropshipper_ftp_picktime_order' => 'string', 'dropshipper_ftp_timestamp' => 'timestamp', 'dropship_enable_date' => 'date', 'dropship_disable_date' => 'date', 'wholesale_approve_date' => 'date', 'warehouse' => 'string', 'salesperson_id' => 'int', 'subadmin' => 'string', 'merge_log' => 'string', 'sf_accountid' => 'string', 'sf_contactid' => 'string', 'sf_process_date' => 'date', 'code' => 'string', 'gender' => 'string', 'genderoption' => 'string'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'reg_datetime', 'upd_datetime', 'birthday', 'ftp_timestamp', 'dropshipper_ftp_timestamp', 'dropship_enable_date', 'dropship_disable_date', 'wholesale_approve_date', 'sf_process_date'
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    // Scopes...

    // Functions ...

    // Relations ...
}
