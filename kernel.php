<?php
/**
 * Установка соединения с базой. Если базы данных с обозначенным именем нет - она создаётся
 * То же самое происходит с таблицами в базе данных
 * Главное, чтобы адрес Базы данных, логин и пароль были указаны верно
 * После создания таблиц проверяем: есть с таблице статусы записи.
 * Если нет - создаём внешние ключи и заносим начальные статусы для задачь
 * Повторный запуск исключает повторное создание таблиц, ключей и записей
 */
class DB {
    protected $dbHost = "127.0.0.1";
    protected $dbUser = "root";
    protected $dbPass = "Gtht[dfn127";
    protected $dbName = "tasks";
    protected $dbConnection;
    protected $uri;
    /**
     * В конструктор заложен функционал инсталлятора. Для корректрой 
     * отработки достаточно ввести в переменные файла config.php следующие данные:
     * логин и пароль учётки в MySQL(с полномочиями для создания базы данных)
     * и корректное название для базы(которой в MySQL нет)
     * Инсталлятор создаст таблицы, ключи и связи, а так же заполнит список статусов, которые из программы не редактируются
     * (это можно поправить)
     */ 
    public function __construct($uri, $dbHost = Null, $dbUser = Null, $dbPass = Null, $dbName = Null )
    {
        if ($dbHost != Null) $this->dbHost = $dbHost;
        if ($dbUser != Null) $this->dbUser = $dbUser;
        if ($dbPass != Null) $this->dbPass = $dbPass;
        if ($dbName != Null) $this->dbName = $dbName;
        if ($uri != Null) $this->uri = $uri;
        $this->dbConnection = mysqli_connect($this->dbHost, $this->dbUser, $this->dbPass) 
        OR die('Соединение не установлено, настройка Базы данных не выполнена');
        mysqli_query($this->dbConnection, 'CREATE DATABASE IF NOT EXISTS '.$this->dbName);
        mysqli_close($this->dbConnection);
        $this->dbConnection = mysqli_connect($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName); 
        $query = 'CREATE TABLE IF NOT EXISTS `tasks`.`tasks` ( 
            `id` INT NOT NULL AUTO_INCREMENT, 
            `subj` VARCHAR(254) NOT NULL ,
            `text` TEXT, 
            `status` INT,
            `created` DATETIME,
            `deadline` DATETIME,
            `user_id` INT
            , PRIMARY KEY (`id`)) ENGINE = InnoDB;';
        mysqli_query($this->dbConnection, $query);
        $query = 'CREATE TABLE IF NOT EXISTS `tasks`.`users` ( 
            `id` INT NOT NULL AUTO_INCREMENT, 
            `FIO` VARCHAR(254) NOT NULL , 
            PRIMARY KEY (`id`)) ENGINE = InnoDB;';
            mysqli_query($this->dbConnection, $query);
        $query = 'CREATE TABLE IF NOT EXISTS `tasks`.`statuses` ( 
            `id` INT NOT NULL AUTO_INCREMENT, 
            `name` VARCHAR(254) NOT NULL , 
            PRIMARY KEY (`id`)) ENGINE = InnoDB;';
        mysqli_query($this->dbConnection, $query);
        $query = 'SELECT count(*) as count FROM `statuses`';
        $count = mysqli_fetch_array(mysqli_query($this->dbConnection, $query));
        if ($count['count'] == 0) {
            mysqli_query($this->dbConnection, $query);
            $query = 'ALTER TABLE `tasks` ADD FOREIGN KEY (`status`) REFERENCES `statuses`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;';
            mysqli_query($this->dbConnection, $query);
            $query = 'ALTER TABLE `tasks` ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;';
            mysqli_query($this->dbConnection, $query);
            $query = 'INSERT INTO `statuses` (`id`, `name`) VALUES (NULL, "Новая");';
            mysqli_query($this->dbConnection, $query);
            $query = 'INSERT INTO `statuses` (`id`, `name`) VALUES (NULL, "В работе");';
            mysqli_query($this->dbConnection, $query);
            $query = 'INSERT INTO `statuses` (`id`, `name`) VALUES (NULL, "На проверке");';
            mysqli_query($this->dbConnection, $query);
            $query = 'INSERT INTO `statuses` (`id`, `name`) VALUES (NULL, "Завершена");';
            mysqli_query($this->dbConnection, $query);
        }
        return $this;
    }
    /**
     * Простейшая выборка(используется для списка пользователей).
     * Можно использовать для других простых списков, не требующих данных из других таблиц
     */ 
    public function select($table = 'users', $where=null) 
    {
        if ($where == Null) {
            $query = "SELECT * FROM ".$table;
        }
        $srcData = mysqli_query($this->dbConnection, $query);
        for ($dataFromBase = []; $row = mysqli_fetch_assoc($srcData); $dataFromBase[] = $row);
        return json_encode($dataFromBase);
    }
    /**
     * Запрос к таблице задач, со всеми связанными данными и фильтрацией
     */ 
    public function selectTasksUser($where = Null)
    {
        if (is_array($where)) {
            foreach ($where AS $key =>$test) {
                if (is_array($test))
                $test = implode('|', $test);
                $out = $key.' : '.$test;
                error_log($out);
            }
        }
        $query = "SELECT *, tasks.id AS id ,DATE_FORMAT(tasks.deadline, '%Y-%m-%d %H:%i:%s') AS deadline,tasks.deadline AS dLine, users.FIO AS executor, users.id AS executorId, statuses.name AS status, statuses.id AS statusId FROM tasks JOIN users ON users.id = tasks.user_id JOIN statuses ON statuses.id = tasks.status";
        if ($where == Null) {
            if(isset($this->uri[2])) {
                $taskId = $this->uri[2];
                $query .= " WHERE tasks.id = $taskId";
            }
        } else {
            $status = $where['filterStatus'];
            $createdStart = $this->dateFormat($where['filterCreateDateStart'], $where['filterCreateTimeStart']);
            $createdEnd = $this->dateFormat($where['filterCreateDateEnd'], $where['filterCreateTimeEnd']);
            $deadlineStart = $this->dateFormat($where['filterDeadlineDateStart'], $where['filterDeadlineTimeStart']);
            $deadlineEnd = $this->dateFormat($where['filterDeadlineDateEnd'], $where['filterDeadlineTimeEnd']);
            $query .= ' WHERE status = '.$status.' AND (tasks.created BETWEEN "'.$createdStart.'" AND "'.$createdEnd.'")';//  AND (dLine BETWEEN "'.$deadlineStart.'" AND "'.$deadlineEnd.'")
            if (isset($where['filterSelect'])) {
                $filterSelect = $where['filterSelect'];
                $filterString = $where['filterString'];
                $query .= ' AND '.$filterSelect.' LIKE "%'.$filterString.'%"';
            }
            if (isset($where['filterSelect']) AND isset($where['filterCreateDateStart'])) {
                
            }
        }
        $srcData = mysqli_query($this->dbConnection, $query) or die(mysqli_error($this->dbConnection));
        for ($dataFromBase = []; $row = mysqli_fetch_assoc($srcData); $dataFromBase[] = $row);
        return json_encode($dataFromBase);
    }
    /**
     * Единичная выборка: данные для редактирования записи из простой таблицы.
     * Вданной версии используется для редактирования пользователя
     */ 
    public function selectOne($table = 'users', $id)
    {
        $query = "SELECT * FROM $table WHERE id = $id";
        $result = mysqli_fetch_array(mysqli_query($this->dbConnection, $query));
        return json_encode($result);
    }
    /**
     * Вставка данных из формы ввода нового(пользователя или задачи)
     */ 
    public function insert($request, $uri)
    {
        $table = $uri[0];
        if ($table == 'tasks') {
            $subj = $request['subj'];
            $text = $request['text'];
            $status = $request['status'];
            $created = date('Y-m-d H:i:s');
            $deadline = $request['deadline'];
            $user_id = $request['user'];
            $query = 'INSERT INTO '.$table.' SET subj="'.$subj.'", text="'.$text.'", status='.$status.', user_id='.$user_id.',created="'.$created.'", deadline="'.$deadline.'"';
            error_log($query);
        }
        if ($table == 'users') {
            $fio= $request['FIO'];
            $query = 'INSERT INTO '.$table.' SET FIO="'.$fio.'"';
        }
        return mysqli_query($this->dbConnection, $query) or die(mysqli_error($this->dbConnection));
    }
    /**
     * обновление данных в базе после редактирования(задачи или пользователя)
     */ 
    public function update($request, $uri) {
        $requestString = implode('|', $request);
        
        $table = $uri[0];
        if ($table == 'users') {
            $fio = $request['FIO'];
            $id = $request['id'];
            $query = 'UPDATE '.$table.' SET FIO="'.$fio.'" WHERE id = '.$id;
        }
        if ($table == 'tasks') {
            $subj = $request['subj'];
            $text = $request['text'];
            $status = $request['status'];
            $user = $request['user'];
            $deadline = $request['deadline'];
            $id = $request['id'];
            $query = 'UPDATE '.$table.' SET subj="'.$subj.'", text="'.$text.'", status="'.$status.'", user_id='.$user.', deadline="'.$deadline.'" WHERE id='.$id;
        }
        return mysqli_query($this->dbConnection, $query) or die(mysqli_error($this->dbConnection));
    }
    /**
     * Удаление записи в базе(пользователей и задач)
     * При удалении пользователя проверяется назначения исполнителя
     * Если пользователь состоит исполнителем в задачах - удаление не происходит
     */ 
    public function delete($uri) {
        $table = $uri[0];
        $id = $uri[2];
        $condition = '= '.$uri[2];
        if ($table == 'users') {
            $validation = $this->count($table, 'user_id', $condition);
        } else {
            $validation = 0;
        }
        if ($validation<1) {
            $query = "DELETE FROM $table WHERE id = $id";
            return mysqli_query($this->dbConnection, $query);
        } else {
            return json_encode('С начала удалите зависимость');
        }
    }
    /**
     * Подсчёт записей в базе: в данном API подсчёт числа задачь у исполнителя
     */ 
    private function count($table, $field, $condition) {
        $query = "SELECT COUNT(*) as count FROM $table WHERE $field $condition";
        $resultArray = mysqli_fetch_assoc(mysqli_query($this->dbConnection, $query));
        return $resultArray['count'];
    }
    /**
     * Подготовка даты и времени для внесения в базу данных
     */ 
    private function dateFormat($date, $time) {
        return $date.' '.$time;
    }
}
