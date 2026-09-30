<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeployLog extends Model
{
    protected $fillable = ['user_id', 'action', 'status', 'commit_before', 'commit_after', 'output', 'finished_at'];

    protected $casts = ['finished_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appendOutput(string $text): void
    {
        $this->output = ($this->output ?? '') . $text;
        $this->save();
    }
}
