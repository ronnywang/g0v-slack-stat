<?php
// wget https://jothon-public-files.s3.ap-northeast-1.amazonaws.com/g0v-export-20140816-20210618.zip
// unzip g0v-export-20140816-20210618.zip
ini_set('memory_limit', '4g');
$obj = json_decode(file_get_contents(__DIR__ . '/output/users.json'));
$users = new StdClass;
foreach ($obj as $user) {
    $users->{$user->id} = new StdClass;
    $users->{$user->id}->name = $user->name;
}

if (!file_exists('pool.json')) {
$channels = json_decode(file_get_contents(__DIR__ . '/output/channels.json'));
$pool = [];
foreach ($channels as $channel) {
    $channel_name = $channel->name;
    foreach (glob(__DIR__ . "/output/{$channel_name}/*") as $f) {
        $obj = json_decode(file_get_contents($f));
        foreach ($obj as $message) {
            if ($message->type == 'message' and property_exists($message, 'subtype') and in_array($message->subtype, [
                'channel_join',
                'tombstone',
                'bot_message',
                'bot_remove',
                'slackbot_response',
                'bot_add',
                'me_message',
                'channel_leave',
                'bot_disable',
                'bot_enable',
                'channel_convert_to_private',
                'sh_room_created',
            ])) {
                continue;
            }

            if (!property_exists($message, 'subtype') or in_array($message->subtype, [
                'thread_broadcast',
            ])) {
                $obj = new StdClass;
                $obj->type = 'talk';
                $obj->ts = $message->ts;
                if (property_exists($message, 'attachments')) {
                    $obj->has_link = true;
                }
                if (!property_exists($message, 'user')) {
                    continue;
                }
                $obj->user = $message->user;
                if (property_exists($obj, 'thread_ts') and $obj->thread_ts != $obj->ts) {
                    $obj->is_reply = true;
                }
                $obj->channel = $channel_name;
                $pool[] = $obj;
                if (property_exists($message, 'reactions')) {
                    foreach ($message->reactions as $reaction) {
                        foreach ($reaction->users as $id) {
                            $obj = new StdClass;
                            $obj->type = 'emoji';
                            $obj->ts = $message->ts;
                            $obj->user = $id;
                            $obj->channel = $channel_name;
                            $pool[] = $obj;
                        }
                    }
                }
                continue;
            } else if (in_array($message->subtype, [
                'channel_purpose',
                'channel_topic',
                'pinned_item',
                'channel_name',
                'channel_archive',
                'channel_unarchive',
                'reminder_add',
            ])) {
                $obj = new StdClass;
                $obj->type = $message->subtype;
                $obj->ts = $message->ts;
                $obj->user = $message->user;
                $obj->channel = $channel_name;
                $pool[] = $obj;
                continue;
            } else if (in_array($message->subtype, [
                'file_comment',
                'reply_broadcast',
            ])) {
                $obj = new StdClass;
                $obj->type = 'talk';
                $obj->ts = $message->ts;
                if (!property_exists($message, 'user')) {
                    continue;
                }
                $obj->user = $message->user;
                $obj->is_reply = true;
                $obj->channel = $channel_name;
                $pool[] = $obj;
                continue;
                    
            }
        }
    }
}
usort($pool, function($a, $b) { return $a->ts - $b->ts; });
file_put_contents('pool.json', json_encode($pool));
} else {
    $pool = json_Decode(file_get_contents('pool.json'));
}

$levels = [10, 50, 100, 200, 300, 400, 500, 1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000, 9000, 10000];
$output = fopen('stat.csv', 'w');
fputcsv($output, ['帳號', '時間', '頻道', '成就']);
$add_trophy = function($obj) use ($users, $output){
    $userid = $obj[0];
    $obj[0] = $users->{$userid}->name;
    $obj[1] = date('Y-m-d', $obj[1]);
    fputcsv($output, $obj);
};

foreach ($pool as $message) {
    $user = $message->user;
    $channel = $message->channel;
    if (!property_exists($users->{$user}, 'talk_count')) {
        $users->{$user}->talk_count = 0;
        $users->{$user}->emoji_count = 0;
        $users->{$user}->reply_count = 0;
        $users->{$user}->channels = [];
    }

    if ($message->type == 'talk') {
        $users->{$user}->talk_count ++;
        $c = $users->{$user}->talk_count;
        if (in_array($c, $levels)) {
            $add_trophy([$user, $message->ts, $channel, sprintf("發言 %d 次", $c)]);
        }
        if (!array_key_exists($channel, $users->{$user}->channels)) {
            $users->{$user}->channels[$channel] = 0;
            $c = count($users->{$user}->channels);
            if (in_array($c, $levels)) {
                $add_trophy([$user, $message->ts, $channel, sprintf("在 %d 個頻道發言", $c)]);
            }
        }
        $users->{$user}->channels[$channel] ++;
        $c = $users->{$user}->channels[$channel];
        if (in_array($c, $levels)) {
            $add_trophy([$user, $message->ts, $channel, sprintf("在 %s 頻道發言 %d 次", $channel, $c)]);
        }
    } elseif ($message->type == 'emoji') {
        $users->{$user}->emoji_count ++;
        $c = $users->{$user}->emoji_count;
        if (in_array($c, $levels)) {
            $add_trophy([$user, $message->ts, $channel, sprintf("使用 emoji %d 次", $c)]);
        }
    }
}
