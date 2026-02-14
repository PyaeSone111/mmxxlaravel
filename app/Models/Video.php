<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'file_path', 'thumbnail_path',
        'thumbnail_url', 'mediafire_url', 'category_id', 'duration_seconds',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($video) {
            if (empty($video->slug)) {
                $video->slug = Str::slug($video->title);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
