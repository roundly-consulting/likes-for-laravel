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
 * the configured prefix and channel type (private/public/presence; anything else throws).
 */
final class BroadcastChannel
{
    public static function for(Model $likeable): Channel
    {
        $prefix = LikesConfig::channelPrefix();

        $segment = Str::of($likeable->getMorphClass())
            ->afterLast('\\')
            ->snake()
            ->plural()
            ->toString();

        $name = "{$prefix}.{$segment}.{$likeable->getKey()}";

        return match (LikesConfig::channelType()) {
            'public' => new Channel($name),
            'presence' => new PresenceChannel($name),
            default => new PrivateChannel($name),
        };
    }
}
