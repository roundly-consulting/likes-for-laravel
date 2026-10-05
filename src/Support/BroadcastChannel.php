<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the broadcast channel for a likeable from the package config, honouring
 * the configured prefix and channel type (private/public/presence; anything else throws):
 * `{prefix}.{morph class, dotted}.{id}`, e.g. `likes.App.Models.Post.42`.
 */
final class BroadcastChannel
{
    public static function for(Model $likeable): Channel
    {
        $prefix = LikesConfig::channelPrefix();

        // The full morph class with dots for backslashes, as Laravel's own model broadcasting
        // names channels (a morph-map alias as-is). The class basename alone put Blog\Post and
        // Forum\Post on one channel, leaking each one's events to the other's subscribers.
        $segment = str_replace('\\', '.', $likeable->getMorphClass());

        $name = "{$prefix}.{$segment}.{$likeable->getKey()}";

        return match (LikesConfig::channelType()) {
            'public' => new Channel($name),
            'presence' => new PresenceChannel($name),
            default => new PrivateChannel($name),
        };
    }
}
