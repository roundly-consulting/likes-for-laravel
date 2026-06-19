<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Builds the broadcast channel for a likeable from the package config, honouring
 * the configured prefix and channel type (private/public/presence).
 */
final class BroadcastChannel
{
    public static function for(Model $likeable): Channel
    {
        $prefix = config('likes.broadcast.channel_prefix', 'likes');
        $prefix = is_string($prefix) ? $prefix : 'likes';

        $segment = Str::of($likeable->getMorphClass())
            ->afterLast('\\')
            ->snake()
            ->plural()
            ->toString();

        $name = "{$prefix}.{$segment}.{$likeable->getKey()}";

        $type = config('likes.broadcast.channel_type', 'private');

        return match (is_string($type) ? $type : 'private') {
            'public' => new Channel($name),
            'presence' => new PresenceChannel($name),
            default => new PrivateChannel($name),
        };
    }
}
