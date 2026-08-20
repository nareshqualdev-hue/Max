<?
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TempPost extends Model
{
    protected $table = 'temp_posts'; // Explicitly defining the table name
    // Define the relationship with TempUser
    public function user()
    {
        return $this->belongsTo(TempUser::class, 'user_id');
    }
}
?>