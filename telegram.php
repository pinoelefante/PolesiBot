<?php
    require_once("config.php");

    function SendMessage($chatId, $message, $markdown = false)
    {
        $url = WEB_ENDPOINT."/sendMessage?chat_id=".$chatId.($markdown ? "&parse_mode=Markdown" : "")."&text=".urlencode($message);
        $res = file_get_contents($url);
        // file_put_contents("last_send_message.txt", $res);
    }
    function GetUpdate()
    {
        $content = file_get_contents("php://input");
        // file_put_contents("last_request.txt", $content);
        return json_decode($content, true);
    }
    function GetTextMessage($content)
    {
        return $content["message"]["text"];
    }
    function GetChatId($content)
    {
        return $content["message"]["chat"]["id"];
    }
    function GetMessageTimestamp($content)
    {
        return $content["message"]["date"];
    }
    function IsBot($message)
    {
        return $content["message"]["from"]["is_bot"];
    }
    function GetUserId($content)
    {
        return $content["message"]["from"]["id"];
    }
    function GetFirstName($content)
    {
        return $content["message"]["from"]["first_name"];
    }
    function GetLastName($content)
    {
        return $content["message"]["from"]["last_name"];
    }
    function GetNickName($content)
    {
        return $content["message"]["from"]["username"];
    }
    function IsAllAdmin($content)
    {
        if(array_key_exists("all_members_are_administrators", $content["message"]["chat"]))
            return $content["message"]["chat"]["all_members_are_administrators"];
        return false;
    }
    function IsAdmin($message)
    {
        if(IsAllAdmin($message))
            return TRUE;
        $userId = GetUserId($message);
        $admin_resp = GetAdministrators(GetChatId($message));
        if(!$admin_resp["ok"])
            return false;
        foreach ($admin_resp["result"] as $admin) {
            if($admin["user"]["id"] == $userId)
                return TRUE;
        }
        return FALSE;
    }
    function IsBotCommand($content)
    {
        if(!array_key_exists("entities", $content["message"]))
            return false;
        return $content["message"]["entities"][0]["type"] == "bot_command";
    }
    function GetAdministrators($chatId)
    {
        $url = WEB_ENDPOINT."/getChatAdministrators?chat_id=".$chatId;
        $resp = file_get_contents($url);
        return json_decode($resp, true);
    }
?>