<? 
// File: app/Models/TempUser.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TempUser extends Model
{
    protected $table = 'temp_users'; // Explicitly defining the table name
    // Define the relationship with TempPost
    public function posts()
    {
        return $this->hasMany(TempPost::class, 'user_id');
    }
}
?>