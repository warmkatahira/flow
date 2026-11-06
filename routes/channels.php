<?php

use App\Broadcasting\ProjectChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('projects.{project}', ProjectChannel::class);
