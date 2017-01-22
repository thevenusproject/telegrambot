<?php
	ini_set('display_errors', 'On');
	error_reporting('E_ALL');
	
	const URL = "https://api.telegram.org/bot{Token}";
	
	$dblocation = "localhost"; 			// Имя сервера
	$dbuser = "root";			 	// Имя пользователя
	$dbpasswd = ""; 				// Пароль
	$dbname = "ttb";
	
	$mysqli = mysqli_connect($dblocation, $dbuser, $dbpasswd, $dbname);
	$mysqli->set_charset("utf8");
	
	$content = file_get_contents("php://input");//Получаем аргументы
	$update = json_decode($content, TRUE);
	
	if (strlen($content) <= 1) {//если скрипт вызван без аргументов, то проверяем ресурсы на наличие новых постов
		$res = $mysqli->query("SELECT * FROM `Source`");//получаем все ресурсы из БД
		while ($row = $res->fetch_assoc()) {
			if ($row['ENABLED'] == 1) {
				if ($row['TYPE_SOURCE'] == "FB") {
					$posts = array();
					$content1 = file_get_contents("https://graph.facebook.com/" . $row['ARG1'] . "/feed?fields=id&limit=10&access_token={Token}");
					$update1 = json_decode($content1, TRUE);
					if (isset($update1["data"])) {//если существует массив с постами
						$r = true;
						for ($b = 0; $b < count($update1["data"]); $b++) {
							if ($update1["data"][$b]["id"] == $row['LAST_POST']) {//если в массиве находится последний пост из БД
								$r = false;
								if ($row['PENULTIMATE_POST'] != $update1["data"][$b + 1]["id"])//если предпоследний пост из БД не равен предпоследниму посту из массива
								$mysqli->query("UPDATE `Source` SET `PENULTIMATE_POST`='" . $update1["data"][$b + 1]["id"] . "' WHERE `ID`=" . $row['ID']);//то обновляем данные
								break;
							}
							elseif ($update1["data"][$b]["id"] == $row['PENULTIMATE_POST']) {//если нашли предпоследний пост из БД в массиве и перед ним не было послднего из БД, то значит его удалили
								$mysqli->query("UPDATE `Source` SET `LAST_POST`='" . $row['PENULTIMATE_POST'] . "' WHERE `ID`=" . $row['ID']);
								$mysqli->query("UPDATE `Source` SET `PENULTIMATE_POST`='" . $update1["data"][$b + 1]["id"] . "' WHERE `ID`=" . $row['ID']);
								$r = false;
								break;
							}
							else {//если мы не нашли в массиве ни последнего, ни предпоследнего постов
								$posts[] = $update1["data"][$b]["id"];//то добавляем в массив для публикации текущий пост
							}
						}
						if (count($posts) > 0) {//если хоть один пост для публикации есть 
							if ($r == true) {//если во всем массиве мы не нашли ни одного знакомого поста
								array_splice($posts, 1);//то мы удаляем из массива все посты, кроме последнего
							}
							
							$name = $row['NAME_SOURCE'];
							for ($b = count($posts) - 1; $b >= 0; $b--) {
								newsletter($row['ID'], $name . " \r\n" . "https://fb.com/" . $posts[$b]);//посылаем всем, кто подписан на $row['ID'] сообщение с названием ресурса и ссылкой на пост
							}
							
							$mysqli->query("UPDATE `Source` SET `LAST_POST`='" . $posts[0] . "' WHERE `ID`=" . $row['ID']);//обновляем последний пост в БД
						}
					}
				}
				elseif ($row['TYPE_SOURCE'] == "YT") {//аналогично FB
					$posts = array();
					$content1 = file_get_contents("https://www.googleapis.com/youtube/v3/search?key{Token}=" . $row['ARG1'] . "&part=id&order=date&maxResults=10");
					$update1 = json_decode($content1, TRUE);
					if ($update1['pageInfo']['totalResults'] > 0) {
						$r = true;
						for ($b = 0; $b < count($update1["items"]); $b++) {
							if ($update1["items"][$b]["id"]["videoId"] == $row['LAST_POST']) {
								$r = false;
								if ($row['PENULTIMATE_POST'] != $update1["items"][$b + 1]["id"]["videoId"]) $mysqli->query("UPDATE `Source` SET `PENULTIMATE_POST`='" . $update1["items"][$b + 1]["id"]["videoId"] . "' WHERE `ID`=" . $row['ID']);
								break;
							}
							elseif ($update1["items"][$b]["id"]["videoId"] == $row['PENULTIMATE_POST']) {
								$mysqli->query("UPDATE `Source` SET `LAST_POST`='" . $row['PENULTIMATE_POST'] . "' WHERE `ID`=" . $row['ID']);
								$mysqli->query("UPDATE `Source` SET `PENULTIMATE_POST`='" . $update1["items"][$b + 1]["id"]["videoId"] . "' WHERE `ID`=" . $row['ID']);
								$r = false;
								break;
							}
							else {
								$posts[] = $update1["items"][$b]["id"]["videoId"];
							}
						}
						if (count($posts) > 0) {
							if ($r == true) {
								array_splice($posts, 1);
							}
							
							$name = $row['NAME_SOURCE'];
							for ($b = count($posts) - 1; $b >= 0; $b--) {
								newsletter($row['ID'], $name . " \r\n" . "https://www.youtube.com/watch?v=" . $posts[$b]);
							}
							
							$mysqli->query("UPDATE `Source` SET `LAST_POST`='" . $posts[0] . "' WHERE `ID`=" . $row['ID']);
						}
					}
				}
				else if ($row['TYPE_SOURCE'] == "VK") {//аналогично FB
					$posts = array();
					$content1 = file_get_contents("https://api.vk.com/method/wall.get?owner_id=-" . $row['ARG1'] . "&count=10&v=5.60");
					$update1 = json_decode($content1, TRUE);
					if ($update1["response"]["items"][0]["is_pinned"] == 1) {//если последний пост закреплен
						array_splice($update1["response"]["items"], 0, 1);//то мы удаляем его из массива
					}
					$r = true;
					for ($b = 0; $b < count($update1["response"]["items"]); $b++) {
						if ($update1["response"]["items"][$b]["id"] == $row['LAST_POST']) {
							$r = false;
							if ($row['PENULTIMATE_POST'] != $update1["response"]["items"][$b + 1]["id"]) $mysqli->query("UPDATE `Source` SET `PENULTIMATE_POST`='" . $update1["response"]["items"][$b + 1]["id"] . "' WHERE `ID`=" . $row['ID']);
							break;
						}
						elseif ($update1["response"]["items"][$b]["id"] == $row['PENULTIMATE_POST']) {
							$mysqli->query("UPDATE `Source` SET `LAST_POST`='" . $row['PENULTIMATE_POST'] . "' WHERE `ID`=" . $row['ID']);
							$mysqli->query("UPDATE `Source` SET `PENULTIMATE_POST`='" . $update1["response"]["items"][$b + 1]["id"] . "' WHERE `ID`=" . $row['ID']);
							$r = false;
							break;
						}
						else {
							$posts[] = $update1["response"]["items"][$b]["id"];
						}
					}
					if (count($posts) > 0) {
						if ($r == true) {
							array_splice($posts, 1);
						}
						$name = $row['NAME_SOURCE'];
						for ($b = count($posts) - 1; $b >= 0; $b--) {
							newsletter($row['ID'], $name . " \r\n" . $row['LINK_SOURCE'] . "?w=wall-" . $row['ARG1'] . "_" . $posts[$b]);
						}
						
						$mysqli->query("UPDATE `Source` SET `LAST_POST`='" . $posts[0] . "' WHERE `ID`=" . $row['ID']);
					}
				}
				else if ($row['TYPE_SOURCE'] == "IG") {//аналогично FB
					$posts = array();
					$content1 = file_get_contents("https://www.instagram.com/" . $row['ARG1'] . "/media/");
					$update1 = json_decode($content1, TRUE);
					$r = true;
					for ($b = 0; $b < count($update1["items"]); $b++) {
						if ($update1["items"][$b]["code"] == $row['LAST_POST']) {
							$r = false;
							if ($row['PENULTIMATE_POST'] != $update1["items"][$b + 1]["code"]) $mysqli->query("UPDATE `Source` SET `PENULTIMATE_POST`='" . $update1["items"][$b + 1]["code"] . "' WHERE `ID`=" . $row['ID']);
							break;
						}
						elseif ($update1["items"][$b]["code"] == $row['PENULTIMATE_POST']) {
							$mysqli->query("UPDATE `Source` SET `LAST_POST`='" . $row['PENULTIMATE_POST'] . "' WHERE `ID`=" . $row['ID']);
							$mysqli->query("UPDATE `Source` SET `PENULTIMATE_POST`='" . $update1["items"][$b + 1]["code"] . "' WHERE `ID`=" . $row['ID']);
							$r = false;
							break;
						}
						else {
							$posts[] = $update1["items"][$b]["code"];
						}
					}
					if (count($posts) > 0) {
						if ($r == true) {
							array_splice($posts, 1);
						}
						
						$name = $row['NAME_SOURCE'];
						for ($b = count($posts) - 1; $b >= 0; $b--) {
							newsletter($row['ID'], $name . " \r\n" . 'https://www.instagram.com/p/' . $posts[$b] . '/');
						}
						
						$mysqli->query("UPDATE `Source` SET `LAST_POST`='" . $posts[0] . "' WHERE `ID`=" . $row['ID']);
					}
				}
			}
		}
	}
	else {//если скрипт вызван с аргументами
		$presence = false;
		$typeMessage = 0;
		if (isset($update['callback_query'])) {//если ответ - сообщение, которое пришло от кнопки
			$query = "SELECT * FROM `Chats` WHERE CHAT_ID=" . $update['callback_query']['from']['id'];//составляем запрос на получение из БД данных пользователя по chat_id
			$typeMessage = 1;
			$chatID = $update['callback_query']['from']['id'];
			$from = $update['callback_query']['from'];
			$callbackData = explode("~", $update['callback_query']['data']);//сообщения от кнопки имеет формат "*~*~*", вместо * каие-либо аргументы
		}
		elseif (isset($update['message'])) {//если сообщение написал и отправил пользователь
			$query = "SELECT * FROM `Chats` WHERE CHAT_ID=" . $update['message']['from']['id'];//составляем запрос на получение из БД данных пользователя по chat_id
			$typeMessage = 2;
			$chatID = $update['message']['from']['id'];
			$from = $update['message']['from'];
		}
		$res = $mysqli->query($query);//получаем из БД данные пользователя по chat_id
		while ($row = $res->fetch_assoc()) {
			$presence = true;
			$re = $row;
		}
		if ($presence == false) {//если в БД пользователя с таким chat_id нет
			$mysqli->query("INSERT INTO `Chats`(`CHAT_ID`, `USERNAME`, `FIRST_NAME`, `SECOND_NAME`, `ID_MESSAGE`, `REGISTRATION_TIME`) VALUES 
			(" . $from['id'] . ",'" . $from['username'] . "','" . $from['first_name'] . "','" . $from['last_name'] . "',0," . time() . ")");//то добавляем его
			$res = $mysqli->query("SELECT * FROM `Language` WHERE ID_PHRASE=0");
			$i = - 1;
			while ($row = $res->fetch_assoc()) {//формируем конопки для выбора языка
				$keyboard[round($i / 3) ][] = array(
				"text" => $row['PHRASE'],
				"callback_data" => "0" . "~" . $row['ID_COUNTRY'] . "~0"
				);
				$i++;
			}
			
			$resp = array(
			"inline_keyboard" => $keyboard
			);
			$update1 = sendMessage($chatID, "Choose language:", false, json_encode($resp));
			$mysqli->query("UPDATE `Chats` SET `ID_MESSAGE`=" . $update1['result']['message_id'] . " WHERE `CHAT_ID`=" . $chatID);
			$res = $mysqli->query("SELECT * FROM `Statistics`");//собираем данные для статистики
			while ($row = $res->fetch_assoc()) {
				$mysqli->query("UPDATE `Statistics` SET `INCOMING`=" . ($row['INCOMING'] + 1));
			}
		}
		else {//если пользователь с таким chat_id существует
			if ($re['USERNAME'] != $from['username']) {//если дынные из БД не соответствуют данным пользователя
				$mysqli->query("UPDATE `Chats` SET `USERNAME`='" . $from['username'] . "' WHERE `CHAT_ID`=" . $chatID);//то обнавляем их
			}
			
			if ($re['FIRST_NAME'] != $from['first_name']) {//если дынные из БД не соответствуют данным пользователя
				$mysqli->query("UPDATE `Chats` SET `FIRST_NAME`='" . $from['first_name'] . "' WHERE `CHAT_ID`=" . $chatID);//то обнавляем их
			}
			
			if ($re['SECOND_NAME'] != $from['last_name']) {//если дынные из БД не соответствуют данным пользователя
				$mysqli->query("UPDATE `Chats` SET `SECOND_NAME`='" . $from['last_name'] . "' WHERE `CHAT_ID`=" . $chatID);//то обнавляем их
			}
			
			if ($typeMessage == 2 || ($typeMessage == 1 && $callbackData[0] == 3)) {//если сообщение от пользователя или от кнопки "Назад"
				n2:
				$message = "";
				$res = $mysqli->query("SELECT * FROM `Interface` WHERE 1");
				$i = 0;
				while ($row = $res->fetch_assoc()) {//формируем кнопки с названиями ресурсов
					if ($i < 3) {
						$keyboard[][] = array(
						"text" => $row['PHRASE'],
						"callback_data" => "1" . "~" . $row['ID_COUNTRY'] . "~1"
						);
					}
					else {
						$keyboard[round(($i-1) / 3) + 2][] = array(
						"text" => $row['PHRASE'],
						"callback_data" => "1" . "~" . $row['ID_COUNTRY'] . "~1"
						);
					}
					
					$i++;
				}
				
				$res = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $re['LANG'] . "'");//получаем фразы с нужным языком
				$keyboard[] = array();
				while ($row = $res->fetch_assoc()) {//формируем текст сообщния и дополнительные кнопки
					if ($row['ID_PHRASE'] == 4) {
						$message = $row['PHRASE'];
					}
					else if ($row['ID_PHRASE'] == 5) {
						$keyboard[count($keyboard) - 1][] = array(
						"text" => $row['PHRASE'],
						"callback_data" => "5~0~0"
						);
					}
					else if ($row['ID_PHRASE'] == 9) {
						$keyboard[][] = array(
						"text" => $row['PHRASE'],
						"callback_data" => "6~0~0"
						);
					}
					
					if ($re['ADMIN'] == 1 && $row['ID_PHRASE'] == 11) {
						$keyboard[][] = array(
						"text" => $row['PHRASE'],
						"callback_data" => "8~0~0"
						);
					}
				}
				
				$resp = array(
				"inline_keyboard" => $keyboard
				);
				if ($typeMessage == 2) {//если сообщение от пользователя
					$res = $mysqli->query("SELECT * FROM `Statistics`");
					while ($row = $res->fetch_assoc()) {//обнавляем данные для статистики
						$mysqli->query("UPDATE `Statistics` SET `INCOMING`=" . ($row['INCOMING'] + 1));
					}
					
					$update1 = sendMessage($chatID, $message, false, json_encode($resp) , $re['ID_MESSAGE'], $re['LANG']);
					$mysqli->query("UPDATE `Chats` SET `ID_MESSAGE`=" . $update1['result']['message_id'] . " WHERE `CHAT_ID`=" . $chatID);
				}
				else {//если сообщение от кнопки
					$update1 = editMessageText($chatID, $re['ID_MESSAGE'], $message, json_encode($resp));
				}
			}
			elseif ($typeMessage == 1) {//если сообщение от кнопки
				$message = "";
				if ($callbackData[0] == 1) { //Подписки по странам
					n1:
					$res = $mysqli->query("SELECT * FROM `Interface` WHERE ID_COUNTRY='" . $callbackData[1] . "'");
					while ($row = $res->fetch_assoc()) {
						$res1 = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $re['LANG'] . "' AND ID_PHRASE=2");
						while ($row1 = $res1->fetch_assoc()) {
							$message = $row1['PHRASE'];
						}
						
						$res1 = $mysqli->query("SELECT * FROM `Subscription` WHERE USER_ID=" . $chatID);
						while ($row1 = $res1->fetch_assoc()) {
							$userSub[] = $row1['SOURCE_ID'];
						}
						
						$res1 = $mysqli->query("SELECT * FROM `Source` WHERE ID_COUNTRY='" . $row['ID_COUNTRY'] . "'");
						while ($row1 = $res1->fetch_assoc()) {
							if ($row1['ENABLED'] == 1) {
								if (presence($userSub, $row1['ID']) == false) {//если в массиве $userSub есть $row1['ID']
									$keyboard[][] = array(
									"text" => $row1['NAME_SOURCE'],
									"callback_data" => "2" . "~" . $row1['ID'] . "~1"
									);
								} //При нажатии подписывается
								else {
									$keyboard[][] = array(
									"text" => "\u2714" . $row1['NAME_SOURCE'],
									"callback_data" => "7~" . $row1['ID_COUNTRY'] . "~" . $row1['ID']
									);
								} //При нажатии отписывается
								}
							}
							
							if ($row['ID_CONTAIN_LINK'] != "NO") {
								$res1 = $mysqli->query("SELECT * FROM `Source` WHERE ID_COUNTRY='" . $row['ID_CONTAIN_LINK'] . "'");
								while ($row1 = $res1->fetch_assoc()) {
									if ($row1['ENABLED'] == 1) {
										if (presence($userSub, $row1['ID']) == false) {//если в массиве $userSub есть $row1['ID']
											$keyboard[][] = array(
											"text" => $row1['NAME_SOURCE'],
											"callback_data" => "2" . "~" . $row1['ID'] . "~1"
											);
										} //При нажатии подписывается
										else {
											$keyboard[][] = array(
											"text" => "\u2714" . $row1['NAME_SOURCE'],
											"callback_data" => "7~" . $row1['ID_COUNTRY'] . "~" . $row1['ID']
											);
										} //При нажатии отписывается
									}
								}
							}
							
							if ($row['ID_LINK'] != "NO") {
								$res1 = $mysqli->query("SELECT * FROM `Interface` WHERE ID_COUNTRY='" . $row['ID_LINK'] . "'");
								while ($row1 = $res1->fetch_assoc()) {
									$keyboard[][] = array(
									"text" => $row1['PHRASE'],
									"callback_data" => "1" . "~" . $row1['ID_COUNTRY'] . "~" . $row['ID_COUNTRY']
									);
								}
							}
							
							$res1 = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $re['LANG'] . "' AND ID_PHRASE=3");
							while ($row1 = $res1->fetch_assoc()) {
								if ($callbackData[2] == 1) {
									$keyboard[][] = array(
									"text" => $row1['PHRASE'],
									"callback_data" => "3~0~0"
									);
								}
								else {
									$keyboard[][] = array(
									"text" => $row1['PHRASE'],
									"callback_data" => "1~" . $callbackData[2] . "~1"
									);
								}
							}
							
							$resp = array(
							"inline_keyboard" => $keyboard
							);
						}
					}
					elseif ($callbackData[0] == 2) { //Подписаться
						$mysqli->query("INSERT INTO `Subscription`(`SOURCE_ID`, `USER_ID`) VALUES (" . $callbackData[1] . "," . $chatID . ")");
						$callbackData[0] = 1;
						$callbackData[2] = 1;
						$res1 = $mysqli->query("SELECT * FROM `Source` WHERE ID='" . $callbackData[1] . "'");
						while ($row1 = $res1->fetch_assoc()) {
							$callbackData[1] = $row1['ID_COUNTRY'];
						}
						
						goto n1;
					}
					elseif ($callbackData[0] == 0) { //Выберите язык
						$mysqli->query("UPDATE `Chats` SET `LANG`='" . $callbackData[1] . "' WHERE `CHAT_ID`=" . $chatID);
						$re['LANG'] = $callbackData[1];
						goto n2;
					}
					elseif ($callbackData[0] == 5 || $callbackData[0] == 4) { //Отписаться
						if ($callbackData[1] == 1) {
							$res1 = $mysqli->query("DELETE FROM `Subscription` WHERE SOURCE_ID=" . $callbackData[2] . " AND USER_ID=" . $chatID);
						}
						
						$res1 = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $re['LANG'] . "' AND ID_PHRASE=7");
						while ($row1 = $res1->fetch_assoc()) {
							$message = $row1['PHRASE'];
						}
						
						$res1 = $mysqli->query("SELECT * FROM `Subscription` WHERE USER_ID=" . $chatID);
						while ($row1 = $res1->fetch_assoc()) {
							$res2 = $mysqli->query("SELECT * FROM `Source` WHERE ID=" . $row1['SOURCE_ID']);
							while ($row2 = $res2->fetch_assoc()) {
								$keyboard[][] = array(
								"text" => "\u2714" . $row2['NAME_SOURCE'],
								"callback_data" => "5~1~" . $row1['SOURCE_ID']
								);
							}
						}
						
						$res1 = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $re['LANG'] . "' AND ID_PHRASE=3");
						while ($row1 = $res1->fetch_assoc()) {
							$keyboard[][] = array(
							"text" => $row1['PHRASE'],
							"callback_data" => "3~0~0"
							);
						}
						
						$resp = array(
						"inline_keyboard" => $keyboard
						);
					}
					elseif ($callbackData[0] == 6) { //Выбрать другой язык
						$res1 = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $re['LANG'] . "' AND ID_PHRASE=10");
						while ($row1 = $res1->fetch_assoc()) {
							$message = $row1['PHRASE'];
						}
						
						$res = $mysqli->query("SELECT * FROM `Language` WHERE ID_PHRASE=0");
						$i = 0;
						while ($row = $res->fetch_assoc()) {
							$keyboard[round(($i-1) / 3) ][] = array(
							"text" => $row['PHRASE'],
							"callback_data" => "0" . "~" . $row['ID_COUNTRY'] . "~0"
							);
							$i++;
						}
						
						$res1 = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $re['LANG'] . "' AND ID_PHRASE=3");
						while ($row1 = $res1->fetch_assoc()) {
							$keyboard[][] = array(
							"text" => $row1['PHRASE'],
							"callback_data" => "3~0~0"
							);
						}
						
						$resp = array(
						"inline_keyboard" => $keyboard
						);
					}
					elseif ($callbackData[0] == 7) { //Отписаться сразу
						$res1 = $mysqli->query("DELETE FROM `Subscription` WHERE SOURCE_ID=" . $callbackData[2] . " AND USER_ID=" . $chatID);
						$callbackData[2] = 1;
						goto n1;
					}
					elseif ($callbackData[0] == 8) { //Статистика
						if ($re['ADMIN'] == 0) {
							goto n2;
						}
						
						$res1 = $mysqli->query("SELECT * FROM `Language` WHERE `ID_PHRASE`>=13 AND `ID_PHRASE`<=20 AND `ID_COUNTRY`='" . $re['LANG'] . "'");
						while ($row1 = $res1->fetch_assoc()) {
							$lang[$row1['ID_PHRASE']] = $row1['PHRASE'];
						}
						
						$res1 = $mysqli->query("SELECT COUNT(*) as total FROM `Chats`");
						while ($row1 = $res1->fetch_assoc()) {
							$message = $lang[13] . " " . $row1['total'] . "\r\n";
						}
						
						$res1 = $mysqli->query("SELECT COUNT(*) as total FROM `Chats` WHERE `LANG`='ENG'");
						while ($row1 = $res1->fetch_assoc()) {
							$message.= "ENG: " . $row1['total'] . "\r\n";
						}
						
						$res1 = $mysqli->query("SELECT COUNT(*) as total FROM `Chats` WHERE `LANG`='ESP'");
						while ($row1 = $res1->fetch_assoc()) {
							$message.= "ESP: " . $row1['total'] . "\r\n";
						}
						
						$res1 = $mysqli->query("SELECT COUNT(*) as total FROM `Chats` WHERE `LANG`='RUS'");
						while ($row1 = $res1->fetch_assoc()) {
							$message.= "RUS: " . $row1['total'] . "\r\n";
						}
						
						$res = $mysqli->query("SELECT COUNT(*) as total FROM `Chats` WHERE `ADMIN`=1");
						while ($row = $res->fetch_assoc()) {
							$message.= $lang[14] . " " . $row['total'] . " \r\n";
						}
						
						$res1 = $mysqli->query("SELECT COUNT(*) as total FROM `Subscription`");
						while ($row1 = $res1->fetch_assoc()) {
							$message.= $lang[15] . " " . $row1['total'] . "\r\n";
						}
						
						$res = $mysqli->query("SELECT * FROM `Statistics`");
						while ($row = $res->fetch_assoc()) {
							$message.= $lang[17] . " " . $row['OUTGOING'] . " \r\n";
						}
						
						$message.= $lang[18];
						$res = $mysqli->query("SELECT * FROM `Interface` WHERE 1");
						$i = 0;
						while ($row = $res->fetch_assoc()) {
							if ($i < 3) {
								$keyboard[][] = array(
								"text" => $row['PHRASE'],
								"callback_data" => "9" . "~" . $row['ID_COUNTRY'] . "~1"
								);
							}
							else {
								$keyboard[round(($i-1) / 3) + 2][] = array(
								"text" => $row['PHRASE'],
								"callback_data" => "9" . "~" . $row['ID_COUNTRY'] . "~1"
								);
							}
							
							$i++;
						}
						
						$res1 = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $re['LANG'] . "' AND ID_PHRASE=3");
						while ($row1 = $res1->fetch_assoc()) {
							$keyboard[][] = array(
							"text" => $row1['PHRASE'],
							"callback_data" => "3~0~0"
							);
						}
						
						$resp = array(
						"inline_keyboard" => $keyboard
						);
					}
					elseif ($callbackData[0] == 9) { //Статистика по странам
						$res1 = $mysqli->query("SELECT * FROM `Language` WHERE `ID_PHRASE`>=13 AND `ID_PHRASE`<=20 AND `ID_COUNTRY`='" . $re['LANG'] . "'");
						while ($row1 = $res1->fetch_assoc()) {
							$lang[$row1['ID_PHRASE']] = $row1['PHRASE'];
						}
						
						$res1 = $mysqli->query("SELECT * FROM `Interface` WHERE `ID_COUNTRY`='" . $callbackData[1] . "'");
						while ($row1 = $res1->fetch_assoc()) {
							$message = $lang[20] . " " . $row1['PHRASE'] . ": \r\n";
						}
						
						$message.= $lang[13] . " {0} \r\n";
						$message.= $lang[15] . " {1} \r\n";
						$message.= $lang[19] . " \r\n";
						$amount = 0;
						$users[] = array();
						$res = $mysqli->query("SELECT * FROM `Source` WHERE `ID_COUNTRY`='" . $callbackData[1] . "'");
						while ($row = $res->fetch_assoc()) {
							$res1 = $mysqli->query("SELECT COUNT(*) as total FROM `Subscription` WHERE `SOURCE_ID`='" . $row['ID'] . "'");
							while ($row1 = $res1->fetch_assoc()) {
								$message.= $row['NAME_SOURCE'] . " - " . $row1['total'] . "\r\n";
								$amount+= $row1['total'];
							}
							
							$res1 = $mysqli->query("SELECT * FROM `Subscription` WHERE `SOURCE_ID`='" . $row['ID'] . "'");
							while ($row1 = $res1->fetch_assoc()) {
								$users[] = $row1['USER_ID'];
							}
							
							$users = array_unique($users);
						}
						
						$message = str_replace("{0}", count($users) - 1, $message);
						$message = str_replace("{1}", $amount, $message);
						$res1 = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $re['LANG'] . "' AND ID_PHRASE=3");
						while ($row1 = $res1->fetch_assoc()) {
							$keyboard[][] = array(
							"text" => $row1['PHRASE'],
							"callback_data" => "8~0~0"
							);
						}
						
						$resp = array(
						"inline_keyboard" => $keyboard
						);
					}
					
					
					$update1 = editMessageText($chatID, $re['ID_MESSAGE'], $message, json_encode($resp));
				}
			}
		}
		
		end:
		function sendMessage($chat_id, $message, $needStat, $keyboard, $oldMessage, $lang)//отправить сообщение
		{
			global $mysqli;
			if (isset($oldMessage)) {
				$res1 = $mysqli->query("SELECT * FROM `Language` WHERE ID_COUNTRY='" . $lang . "' AND ID_PHRASE=12");
				while ($row1 = $res1->fetch_assoc()) {
					editMessageText($chat_id, $oldMessage, $row1['PHRASE']);//заменяем старое сообщение на нужную фразу
				}
			}
			
			if ($needStat == true) {
				$res = $mysqli->query("SELECT * FROM `Statistics`");
				while ($row = $res->fetch_assoc()) {
					$mysqli->query("UPDATE `Statistics` SET `OUTGOING`=" . ($row['OUTGOING'] + 1));//обновляем данные статистики
				}
			}
		
		$message = text($message);//заменяем символы с \\
		$keyboard = text($keyboard);//заменяем символы с \\
		if (isset($keyboard)) {
			$content1 = file_get_contents(URL . '/sendMessage?chat_id=' . $chat_id . '&text=' . urlencode($message) . '&reply_markup=' . $keyboard);//отправляем с клавиатурой
		}
		else {
			$content1 = file_get_contents(URL . '/sendMessage?chat_id=' . $chat_id . '&text=' . urlencode($message));//отправляем без клавиатуры
		}
		
		$update1 = json_decode($content1, TRUE);
		return $update1;
	}
	
	function editMessageText($chat_id, $message_id, $message, $keyboard)
	{
		$message = text($message);//заменяем символы с \\
		$keyboard = text($keyboard);//заменяем символы с \\
		if (isset($keyboard)) {
			$content1 = file_get_contents(URL . '/editMessageText?chat_id=' . $chat_id . '&message_id=' . urlencode($message_id) . '&text=' . urlencode($message) . '&reply_markup=' . $keyboard);//меняем на сообщение с клавиатурой
		}
		else {
			$content1 = file_get_contents(URL . '/editMessageText?chat_id=' . $chat_id . '&message_id=' . $message_id . '&text=' . urlencode($message));//меняем на сообщение без клавиатуры
		}
		
		$update1 = json_decode($content1, TRUE);
		return $update1;
	}
	
	function text($str)
	{
		$str = str_replace('\u2714', "✔", $str);
		$str = str_replace('\\\\r\\\\n', " \\r\\n", $str);
		return $str;
	}
	
	function presence($array, $num)
	{
		for ($b = 0; $b < count($array); $b++) {
			if ($array[$b] == $num) {
				return true;
			}
		}
		
		return false;
	}
	
	function newsletter($ID, $str)
	{
		global $mysqli;
		$res = $mysqli->query("SELECT * FROM `Subscription` WHERE SOURCE_ID = " . $ID);
		while ($row = $res->fetch_assoc()) {
			sendMessage($row['USER_ID'], $str, true);
		}
		
		return 0;
	}
	
	mysqli_close($mysqli);
?>
