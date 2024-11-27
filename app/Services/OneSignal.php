<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Notification;
use App\Models\User;
use App\Models\DeviceDetail;

class OneSignal
{
    public function send(Model $sender, Model $receiver, string $notificationType, $item = null, $deceasedUser = null): bool
    {
        $notification = self::generateNotification($sender, $notificationType, $deceasedUser, $item);

        if ($notification) {
            [$title, $message, $data] = $notification;
            $this->store($sender, $receiver, $title, $message, $notificationType, $item, $deceasedUser);

            $tokens = $this->token($receiver->id);
            if ($tokens && self::hasPermission($receiver)) {
                return $this->sendNotification($title, $message, $data, $tokens);
            }
        }
        return true;
    }

    // test Notification
    public function sendToAllUsers(Model $sender, $title, $message): bool
    {
        $data = [
            'type' => "deceased",
            'sender' => [
                "user_name" => trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')),
                "user_image" => $sender->image ?? null,
                "user_id" => $sender->id ?? null,
            ],
        ];
        $tokens = DeviceDetail::where(['is_user_logged' => 1])
            ->whereNotNull('device_token')
            ->pluck('device_token')
            ->toArray();
        if ($this->sendNotification($title, $message, $data, $tokens)) {
            return true;
        }
        return false;
    }

    // get the Device Token
    private function token(int $receiver_id): array|bool
    {
        $tokens = DeviceDetail::where(['user_id' => $receiver_id, 'is_user_logged' => 1])
            ->pluck('device_token')
            ->toArray();
        return $tokens == [] ? false : $tokens;
    }


    //send the sms
    public function sendNotification(string $title, string $message, array $data, array $external_ids): bool
    {
        // Constants for the OneSignal API
        $appId = env('ONESIGNAL_APP_ID');
        $channelId = env('ONESIGNAL_APP_CHANNEL_ID');
        $authKey = env('ONESIGNAL_APP_SECRET');
        $url = 'https://onesignal.com/api/v1/notifications';

        // Remove dd() for testing
        // dd($appId , $channelId, $authKey, $url , $external_ids);

        // Prepare the notification content
        $fields = [
            "app_id" => $appId,
            'android_channel_id' => $channelId,
            "include_player_ids" => $external_ids,
            "channel_for_external_user_ids" => "push",
            "data" => $data,
            'contents' => ["en" => $message],
            'headings' => ["en" => $title],
        ];

        // Set headers for the cURL request
        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $authKey,
        ];

        // Initialize cURL session
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => json_encode($fields),
        ]);

        // Execute the cURL request and check for errors
        $result = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            Log::error('cURL error: ' . curl_error($ch));
            return false;
        }

        // Check HTTP response code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        Log::info("HTTP response code: " . $httpCode);

        // Decode response and check if it was successful
        $response = json_decode($result, true);
        if ($httpCode == 200 && isset($response['id'])) {
            Log::info("Notification sent successfully: " . json_encode($response));
            return true;
        } else {
            Log::error("Notification failed: " . $result);
            return false;
        }

        // Close cURL session
        curl_close($ch);
    }

    private static function generateNotification($sender, $type, $deceasedUser = null, $item = null): bool|array
    {
        $senderDetails = [
            "user_name" => trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')),
            "user_image" => $sender->image ?? null,
            "user_id" => $sender->id ?? null,
        ];

        $senderName = $sender->first_name ?? null;
        $deceasedName = $deceasedUser ? trim($deceasedUser->first_name . ' ' . $deceasedUser->last_name) : 'Deceased Member';
        $notifications = collect([
            'like' => [
                'title' => "New Like",
                'message' => "$senderName liked your post",
                'data' => [
                    "type" => "like",
                    "sender" => $senderDetails,
                    "post" => $item,
                    "item_id" => $item ? $item->id : null,
                ],
            ],
            'follow' => [
                'title' => "New Follow",
                'message' => "$senderName started following you.",
                'data' => [
                    "type" => "follow",
                    "sender" => $senderDetails,
                    "item_id" => $item ? $item->id : null,
                ],
            ],
            'invite' => [
                'title' => "New Invited User",
                'message' => "$senderName invites you to join the family",
                'data' => [
                    "type" => "invite",
                    "sender" => $senderDetails,
                    "item_id" => $item ? $item->id : null,
                ],
            ],
            'post' => [
                'title' => "New Posted Post",
                'message' => "Posted this post.",
                'data' => [
                    "type" => "post",
                    "sender" => $senderDetails,
                    "item_id" => $item ? $item->id : null,
                ],
            ],
            'deceased' => [
                'title' => "$deceasedName deceased",
                'message' => "$senderName has marked $deceasedName as deceased.",
                'data' => [
                    "type" => "deceased",
                    "sender" => $senderDetails,
                    "deceased_user" => $item,
                    "item_id" => $item ? $item->id : null,
                ],
            ],
            'self' => [
                'title' => "$deceasedName deceased",
                'message' => "$senderName has marked $deceasedName as deceased.",
                'data' => [
                    "type" => "self",
                    "sender" => $senderDetails,
                    "deceased_user" => $item,
                    "item_id" => $item ? $item->id : null,
                ],
            ],
            'accept' => [
                'title' => "Accepted your request",
                'message' => "Your request has been accepted by $senderName.",
                'data' => [
                    "type" => "accept",
                    "sender" => $senderDetails,
                    "item_id" => $item ? $item->id : null,
                ],
            ],
            'invite_user' => [
                'title' => "Accepted your invitation",
                'message' => "Your invitation has been accepted by $senderName.",
                'data' => [
                    "type" => "invite_user",
                    "sender" => $senderDetails,
                    "item_id" => $item ? $item->id : null,
                ],
            ],
            'last_log' => [
                'title' => "Last Log",
                'message' => "Last Log of Your Buddy $senderName.",
                'data' => [
                    "type" => "invite_user",
                    "sender" => $senderDetails,
                    "item_id" => $item ? $item->id : null,
                ],
            ],
        ]);

        // Search for the notification type
        $notification = $notifications->get($type);

        if ($notification) {
            return [
                $notification['title'],
                $notification['message'],
                $notification['data'],
            ];
        } else {
            return false;
        }
    }


    // When user in the sleep mode then he can't receive notifications
    private static function hasPermission($user): bool
    {
        return !$user?->setting?->sleep_mode;
    }

    public function store(Model $sender, Model $receiver, string $title, string $message, string $type, $item = null, $deceasedById = null): bool
    {
        $notification = new Notification;
        $notification->sender_id = $sender?->id;
        $notification->receiver_id = $receiver?->id;
        $notification->title = $title;
        $notification->message = $message;
        $notification->type = $type;
        $notification->item_id = is_object($item) ? $item->id : null;
        $notification->marked_user_id = $deceasedById;
        $notification->save();
        return true;
    }
    public function logUpdate(Model $sender, Model $receiver, string $title, string $message, string $type, $item = null, $deceasedById = null): bool
    {
        // Find the existing notification by sender_id and receiver_id
        $notification = Notification::where('sender_id', $sender?->id)
            ->where('receiver_id', $receiver?->id)
            ->where('type', $type)
            ->first();
        // Check if the notification exists
        if ($notification) {
            // Update the notification fields
            $notification->title = $title;
            $notification->message = $message;
            $notification->type = $type;
            $notification->item_id = $item?->id;
            $notification->marked_user_id = $deceasedById;
            // Save the updated notification
            $notification->save();
            return true; // Return true if the update was successful
        } else {
            $notification = new Notification;
            $notification->sender_id = $sender?->id;
            $notification->receiver_id = $receiver?->id;
            $notification->title = $title;
            $notification->message = $message;
            $notification->type = $type;
            $notification->item_id = $item?->id;
            $notification->marked_user_id = $deceasedById;
            $notification->save();
            return true;
        }
    }


    public function lastActiveLog(Model $sender, Model $receiver): bool
    {
        $item = $receiver->logs()->latest()->first();
        if (!$item) {
            return false;
        }
        $title = "Last Log";
        $message = "Your buddy, {$sender->first_name} {$sender->last_name}, last logged in at " . $item?->created_at?->format('h:i A');
        $senderDetails = [
            "user_name" => trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')),
            "user_image" => $sender->image ?? null,
            "user_id" => $sender->id ?? null,
        ];
        $data = [
            "type" => "invite_user",
            "sender" => $senderDetails,
            "item_id" => $item?->id,
        ];
        $notificationType = "last_log";
        $this->logUpdate($sender, $receiver, $title, $message, $notificationType, $item);
        $tokens = $this->token($receiver->id);
        if ($tokens && self::hasPermission($receiver)) {
            return $this->sendNotification($title, $message, $data, $tokens);
        }
        return false;
    }

    public function sendLogNotification(Model $sender, Model $receiver, $item, $message, $type): bool
    {
        $item = $receiver->logs()->latest()->first();
        if (!$item) {
            return false;
        }
        $title = "Alert";
        // $message = "Your buddy, {$sender->first_name} {$sender->last_name}, last logged in at " . $item?->created_at?->format('h:i A');
        $senderDetails = [
            "user_name" => trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')),
            "user_image" => $sender->image ?? null,
            "user_id" => $sender->id ?? null,
        ];
        $data = [
            "type" => "invite_user",
            "sender" => $senderDetails,
            "item_id" => $item?->id,
        ];
        $this->logUpdate($sender, $receiver, $title, $message, $type, $item);
        $tokens = $this->token($receiver->id);
        if ($tokens && self::hasPermission($receiver)) {
            return $this->sendNotification($title, $message, $data, $tokens);
        }
        return false;
    }
}
