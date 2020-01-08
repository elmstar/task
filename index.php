<?php
/**
 * API решил сильно не раскидывать по разным файлам:
 *  
 * ЧПУ и поправки в переменную $uri
 */ 
$uri = explode('/',trim(preg_replace('#(\?.*)#','',$_SERVER['REQUEST_URI']),'/'));
if (empty(trim($uri[0]))) $uri[0] = 'tasks';
if (!isset($uri[1])) $uri[1] = 'list';

/**
 * Подключаем базовую конфигурацию для доступа к Базе данных
 */ 
include 'config.php';
/**
 * подключаем основной класс-ядро всего API
 * В результате переменная $db с объектом
 */ 
include 'kernel.php';
/**
 * переменная-экземпляр класса
 */ 
$db = new DB($uri, $dbHost, $dbUser, $dbPass, $dbName);
/**
 * Переменная с запросом, с формы клиентской программы
 */ 
$request = $_REQUEST;

/*
 * Конструкция switch-case(с вложенными switch-case) играют роль своеобразного контроллера
 * структура как у клиентской части
 */ 
switch ($uri[0]) {
    case 'tasks':
        switch ($uri[1]) {
            case 'list':
                    echo $db->selectTasksUser($_REQUEST);
                break;
            case 'new':
                if (isset($request['act'])) {
                    $db->insert($request, $uri);
                } else {
                echo json_encode($_POST);
                }
                break;
            case 'edit':
                if (isset($_REQUEST['act'])) {
                    echo $db->update($_REQUEST, $uri);
                } else {
                    echo $db->selectTasksUser();
                }
                break;
            case 'delete':
                if (isset($uri[2])) {
                    echo $db->delete($uri);
                }
                break;
            default:
                echo json_encode(['запрос для раздела задач оказался не верным']);
            break;
    }
    break;
    case 'users':
        switch ($uri[1]) {
            case 'list':
                echo $db->select($uri[0]);
            break;
            case 'new':
                if ($_REQUEST['FIO']) {
                    $answer = $db->insert($_REQUEST, $uri);
                    echo json_encode($answer);
                }
                break;
            case 'edit':
                if (isset($_REQUEST['act'])) {
                    if ($_REQUEST['act'] == 'edit') {
                        echo $db->update($_REQUEST, $uri);
                    }
                    break;
                }
                if (isset($uri[2])) {
                    echo $db->selectOne($uri[0], $uri[2]);
                    break;
                }
            case 'delete':
                if (isset($uri[2])) {
                    echo $db->delete($uri);
                }
                break;
            default:
                echo json_encode(['запрос для раздела задач оказался не верным']);
                break;
        }
    break;
    case 'statuses':
        echo $db->select('statuses');
    break;
    
    default:
    echo json_encode(['запрос для раздела задач оказался не верным']);
    break;
}
