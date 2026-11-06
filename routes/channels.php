<?php

use App\Broadcasting\ProjectChannel;
use App\Broadcasting\UserChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('projects.{project}', ProjectChannel::class);
Broadcast::channel('users.{id}', UserChannel::class);
