<?php
    require_once("config.php");
    require_once("logger.php");
    require_once("telegram.php");
    require_once("organizer.php");

    set_error_handler("app_error_logger");

    $json = GetUpdate();
    CheckChatSupported($json);

    $message = GetTextMessage($json);
    $chatId = GetChatId($json);
    $userId = GetUserId($json);
    
    $isBotCommand = IsBotCommand($json);
        $parameters = array();
    if($isBotCommand)
    {
        if(strpos($message, " ")!==false)
        {
            $parameters = explode(" ", $message);
            $lowmsg = $parameters[0];
        }
        else
            $lowmsg = $message;
    }
    else
        $lowmsg = strtolower($message);
        
    switch($lowmsg)
    {
        case "/nuova":
        case "/nuova@".BOT_NAME:
            if(!IsAdmin($json))
                break;
            if(!IsGameEnded($chatId))
            {
                SendMessage($chatId, "Una partita è ancora in corso. Terminala per crearne un'altra");
                return;
            }
            if(CreateNewGame($chatId, $userId))
            {
                SendMessage($chatId, "Partita creata");
                SendInfoGame($chatId);
            }
            else
                SendMessage($chatId, "Non è stato possibile creare una nuova partita");
            break;
        case "/termina":
        case "/termina@".BOT_NAME:
            if(!IsAdmin($json))
                break;
            if(EndGame($chatId))
                SendMessage($chatId, "Partita terminata con *SUCCESSO*", true);
            else
                SendMessage($chatId, "Non sono riuscito a terminare la partita");
            break;
        case "/info":
        case "/info@".BOT_NAME:
            SendInfoGame($chatId);
            break;
        case "presente":
        case "ci sono":
        case "/ci_sono":
        case "/ci_sono@".BOT_NAME:
            if(IsGameEnded($chatId))
                return;
            $userId = GetUserId($json);
            $firstname = GetFirstName($json);
            $lastName = GetLastName($json);
            $nickname = GetNickName($json);
            $game = GetLastGame($chatId);
            if($game == null)
                return;
            $players = GetPlayers($chatId, $game["id"]);
            if(count($players) >= $game["players"])
            {
                SendMessage($chatId, "Mi dispiace $firstname, ma la partita è già al completo");
                break;
            }
            if(AddPlayer($chatId, $userId, $firstname, $lastName, $nickname))
            {
                SendMessage($chatId, "Si è unito anche $firstname");
                SendInfoGame($chatId);
            }
            break;
        case "assente":
        case "non ci sono":
        case "non ci sono più":
        case "non ci sono piu":
        case "/non_ci_sono":
        case "/non_ci_sono@".BOT_NAME:
            if(IsGameEnded($chatId))
                return;
            $userId = GetUserId($json);
            if(RemovePlayer($chatId, $userId))
            {
                $firstname = GetFirstName($json);
                SendMessage($chatId, "$firstname non verrà alla partita");
                SendInfoGame($chatId);
            }
            break;
        case "/info_esterni":
        case "/info_esterni@".BOT_NAME:
            $game = GetLastGame($chatId);
            if($game != null)
                SendMessage($chatId, ($game["external"] ? "Giocatori esterni al gruppo *ABILITATO*" : "Giocatori esterni al gruppo *NON ABILITATO*"), true);
            break;
        case "/cambia_esterni":
        case "/cambia_esterni@".BOT_NAME:
            if(!IsAdmin($json))
                return;
            if(ChangeExternalPlayerStatus($chatId))
            {
                $game = GetLastGame($chatId);
                if($game != null)
                    SendMessage($chatId, $game["external"] ? 
                            "Giocatori esterni al gruppo *ABILITATO*" : 
                            "Giocatori esterni al gruppo *NON ABILITATO*", true);
            }
            break;
        case "/riserva_posto":
        case "/riserva_posto@".BOT_NAME:
            if(IsGameEnded($chatId))
                return;
            $game = GetLastGame($chatId);
            if($game==null || !$game["external"])
                return;
            
            $spots = 1;
            $externalName = NULL;
            $userFullName = trim(GetFirstName($json)." ".GetLastName($json));

            if(count($parameters) > 1)
            {
                if(is_numeric($parameters[1]))
                {
                    $tempVal = intval($parameters[1]);
                    $spots = $tempVal > 0 ? $tempVal : 1;
                }
                else
                {
                    unset($parameters[0]);
                    $externalName = implode(" ", $parameters);
                }
            }
            $players = GetPlayers($chatId, $game["id"]);
            $freeSpots = $game["players"]-count($players);
            if($spots > $freeSpots)
            {
                SendMessage($chatId, "Posti non riservati. Sono disponibili $freeSpots posti");
                break;
            }
            $spotsReserved = ReservePlayer($userId, $chatId, $externalName, $userFullName, GetNickname($json), $spots);
            SendMessage($chatId,"Riservato/i $spotsReserved posto/i da *$userFullName*", true);
            if($spotsReserved > 0)
                SendInfoGame($chatId);
            break;
        case "/libera_posto":
        case "/libera_posto@".BOT_NAME:
            if(IsGameEnded($chatId))
                return;
            $userFullName = trim(GetFirstName($json)." ".GetLastName($json));
            $spots = 1;
            $externalName = NULL;
    
            if(count($parameters) > 1)
            {
                if(is_numeric($parameters[1]))
                {
                    $tempVal = intval($parameters[1]);
                    $spots = $tempVal > 0 ? $tempVal : 1;
                }
                else
                {
                    unset($parameters[0]);
                    $externalName = implode(" ", $parameters);
                }
            }
            $spotsFreed = $externalName != NULL ? FreeSpotByName($chatId, $userId, $externalName) : FreeSpot($chatId, $userId, $spots);

            SendMessage($chatId,"Liberato/i $spotsFreed posto/i da *$userFullName*", true);
            if($spotsFreed > 0)
                SendInfoGame($chatId);
            break;
        case "/imposta_giorno":
        case "/imposta_giorno@".BOT_NAME:
            if(!IsAdmin($json))
                return;
            if(isset($parameters) && count($parameters) > 1){
                $newDay = $parameters[1];
                if(SetDay($chatId, $newDay))
                    SendInfoGame($chatId);
                else
                    SendMessage($chatId, "Giorno non cambiato");
            }
            else
                SendMessage($chatId, "Modalità d'uso: /imposta_giorno giorno\ngiorno può assumere i seguenti valori: oggi,domani,dopodomani,lunedi,martedi,mercoledi,giovedi,venerdi,sabato,domenica");
            break;
        case "/imposta_ora":
        case "/imposta_ora@".BOT_NAME:
            if(!IsAdmin($json))
                return;
            if(isset($parameters) && count($parameters) > 1){
                $hour = $parameters[1]; //verificare il formato dell'ora
                if(SetHour($chatId, $hour))
                    SendInfoGame($chatId);
                else
                    SendMessage($chatId, "Ora non cambiata");
            }
            else
                SendMessage($chatId, "Modalità d'uso: /imposta_ora HH:MM");
            break;
        case "/imposta_giocatori":
        case "/imposta_giocatori@".BOT_NAME:
            if(!IsAdmin($json))
                return;
            if(isset($parameters) && count($parameters) > 1 && is_numeric($parameters[1]))
            {
                $numPlayers = intval($parameters[1]);
                if(SetPlayers($chatId, $numPlayers))
                    SendInfoGame($chatId);
                else
                    SendMessage($chatId, "Si è verificato un errore");
            }
            else
                SendMessage($chatId, "Modalità d'uso: /imposta_giocatori N");
            break;
        case "/imposta_campo":
        case "/imposta_campo@".BOT_NAME:
            if(!IsAdmin($json))
                return;
            if(isset($parameters))
            {
                unset($parameters[0]);
                $fieldName = implode(" ", $parameters);
                if(SetField($chatId, $fieldName))
                    SendInfoGame($chatId);
                else
                    SendMessage($chatId, "Si è verificato un errore");
            }
            else
                SendMessage($chatId, "Modalità d'uso: /imposta_campo NomeCampo");
            break;
        case "/rimuovi":
        case "/rimuovi@".BOT_NAME:
            if(!isAdmin($json))
                return;
            SendMessage($chatId, "Non implementato");
            // rimuove un giocatore in base al nome ed il cognome
            break;
        case "/rimuovi_esterni":
        case "/rimuovi_esterni@".BOT_NAME:
            if(!isAdmin($json))
                return;
            SendMessage($chatId, "Non implementato");
            break;
        case "/recupera":
        case "/recupera@".BOT_NAME:
            if(!isAdmin($json))
                return;
            SendMessage($chatId, "Non implementato");
            // cancella l'ultima gara creata ed utilizza la penultima
            break;
        case "/statistiche":
        case "/statistiche@".BOT_NAME:
            if(!isAdmin($json))
                return;
            SendMessage($chatId, "Non implementato");
            break;
        default:
            ManageMessage($chatId, $json);
            break;
    }

    function SendInfoGame($chatId)
    {
        $game = GetLastGame($chatId);
        if($game == NULL)
        {
            SendMessage($chatId, "Al momento non ci sono partite in programma");
            return;
        }
        $players = GetPlayers($chatId);
        $elenco = "";
        $indexPlayer = 1;
        foreach ($players as $player) {
            $playerName = GetPlayerNameForList($player, $player["userId"]<0);
            $elenco.=$indexPlayer." - ".$playerName."\n";
            $indexPlayer++;
        }

        $date_item = DateTime::createFromFormat("Y-m-d", $game["startDay"]);
        $formattedData = date("d-m-Y", $date_item->getTimestamp());
        $italianDay = GetItalianDay(date("l", $date_item->getTimestamp()));

        $hour_split = explode(":", $game["startHour"]);
        unset($hour_split[2]);
        $formattedTime = implode(":", $hour_split);
        
        SendMessage($chatId,"La prossima partita si giocherà *$italianDay*, *$formattedData* alle ore *".$formattedTime."*"
        .(!empty($game["field"]) ? " al campo *".$game["field"]."*\n" : "\n")
        .(count($players) > 0 ? "*\nElenco partecipanti:*\n$elenco\n" : "\n")
        .(count($players) == $game["players"] ? "*Siamo al completo*" : "Mancano *".($game["players"]-count($players)."* giocatori")), true);
    }
    function GetPlayerNameForList($user, $isFriend=false)
    {
        if($isFriend)
        {
            $name = "Amico di "
                    .(empty($user["lastname"]) ? $user["nickname"] : $user["lastname"])
                    .(empty($user["firstname"]) ? "" : " (".$user["firstname"].")");
        }
        else
        {
            $name = trim(($user["firstname"]!=NULL ? $user["firstname"] : "")." ".($user["lastname"]!=NULL ? $user["lastname"] : ""));
            if(empty($name))
                $name = !empty($user["nickname"]) ? $user["nickname"] : "Utente sconosciuto";
        }
        return $name;
    }
    function CheckChatSupported($messageJson)
    {
        $chatId = GetChatId($messageJson);
        if(!IsGroup($messageJson) && !IsSupergroup($messageJson))
        {
            SendMessage($chatId, "Questo bot supporta soltanto i gruppi e i supergruppi");
            exit;
        }
    }
    function ManageMessage($chatId, $json)
    {
        $newChatId = CheckChangeChatId($json);
        if($newChatId !== NULL && $chatId != $newChatId)
        {
            if(!ChangeGroupId($chatId, $newChatId))
                file_put_contents("errore_cambio_id.txt", "origine: $chatId destinazione: $newChatId");
        }
    }
    function IsGameEnded($chatId)
    {
        $game = GetLastGame($chatId);
        if($game == NULL)
            return true;
        $timeNow = time()-600;
        $timeEnd = GetTimestampFromDateTime($game["startDay"], $game["startHour"]);
        return $timeNow > $timeEnd;
    }
    function GetTimestampFromDateTime($date,$time)
    {
        $date_item = DateTime::createFromFormat("Y-m-d H:i:s", $date." ".$time);
        return $date_item->getTimestamp();
    }
?>