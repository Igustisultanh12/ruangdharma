<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SocialStat extends Model
{
    protected $fillable = ['platform','account_username','followers','total_likes','total_comments','reach','views','posts','recent_comments','daily','fetched_at'];
    protected $casts = ['posts' => 'array', 'recent_comments' => 'array', 'daily' => 'array', 'fetched_at' => 'datetime'];
}
