<?php
$botToken = "8581704909:AAET8NbghmL4eH7CMuglpLpdmCNDiHGzfe8";
$adminGroupId = "-1002123151908"; // Admin guruh ID sini o'zgartiring

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $userId = $message['from']['id'];
    $userName = $message['from']['first_name'] ?? 'Foydalanuvchi';
    
    // Foydalanuvchi ma'lumotlarini saqlash uchun
    $userFile = "users/{$userId}.json";
    
    if (!file_exists('users')) {
        mkdir('users', 0777, true);
    }
    
    $userData = [];
    if (file_exists($userFile)) {
        $userData = json_decode(file_get_contents($userFile), true);
    }
    
    // /start komandasi
    if ($text == '/start') {
        $userData = ['step' => 'waiting_destination'];
        file_put_contents($userFile, json_encode($userData));
        
        sendMessage($chatId, "🚖 *Taxi Bot*ga xush kelibsiz!\n\n🎯 Chiqish va borish manzilingizni yozing:\n\n_Masalan: Samarqand - Farg'ona va hokozo_");
    }
    
    // Borish joyi
    elseif ($userData['step'] == 'waiting_destination' && !empty($text)) {
        $userData['destination'] = $text;
        $userData['step'] = 'waiting_phone';
        file_put_contents($userFile, json_encode($userData));
        
        $keyboard = [
            'keyboard' => [
                [['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        
        sendMessage($chatId, "✅ Borish joyi: *{$text}*\n\n📱 Telefon raqamingizni yuboring: masalan +998999558657", $keyboard);
    }
    
    // Telefon raqam qabul qilish (tugma orqali)
    elseif (isset($message['contact']) && $userData['step'] == 'waiting_phone') {
        $contact = $message['contact'];
        $phoneNumber = $contact['phone_number'];
        
        processOrder($chatId, $userId, $userName, $phoneNumber, $userData, $adminGroupId);
    }
    
    // Telefon raqam qabul qilish (qo'lda yozish)
    elseif ($userData['step'] == 'waiting_phone' && !empty($text)) {
        $phoneNumber = $text;
        
        processOrder($chatId, $userId, $userName, $phoneNumber, $userData, $adminGroupId);
    }
    
    else {
        sendMessage($chatId, "");
    }
}

function processOrder($chatId, $userId, $userName, $phoneNumber, &$userData, $adminGroupId) {
    $userData['phone'] = $phoneNumber;
    $userData['step'] = 'completed';
    file_put_contents("users/{$userId}.json", json_encode($userData));
    
    // Buyurtmani admin guruhga yuborish
    $orderText = "🚖 *YANGI TAXI BUYURTMA*\n\n";
    $orderText .= "👤 *Mijoz:* [" . $userName . "](tg://user?id=" . $userId . ")\n";
    $orderText .= "📱 *Telefon:* {$phoneNumber}\n";
    $orderText .= "🎯 *Qayerga:* {$userData['destination']}\n";
    $orderText .= "🕐 *Vaqt:* " . date('d.m.Y H:i') . "\n";
    $orderText .= "🆔 *User ID:* {$userId}";
    
    // Admin guruhga yuborish
    sendMessage($adminGroupId, $orderText);
    
    // Mijozga tasdiqlash
    $keyboard = [
        'keyboard' => [
            [['text' => '🚖 Yana taxi chaqirish']]
        ],
        'resize_keyboard' => true
    ];
    
    sendMessage($chatId, "✅ *Buyurtmangiz qabul qilindi!*\n\nTez orada haydovchi siz bilan bog'lanadi.\n\n📞 Telefon: {$phoneNumber}\n🎯 Qayerga: {$userData['destination']}", $keyboard);
}

function sendMessage($chatId, $text, $keyboard = null) {
    global $botToken;
    
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}
?>