<?php

/**
 * Файл из репозитория MikBill-EventSystem-PHP-Kit
 * @link https://github.com/itpanda-llc
 */

/** Путь к конфигурационному файлу АСР MikBill */
const CONFIG = '../../../../www/mikbill/admin/app/etc/config.xml';

/** Тип NAS RouterOS */
const NAS_TYPE = 'mikrotik';

/**
 * @return SimpleXMLElement Объект конфигурационного файла
 */
function getConfig(): SimpleXMLElement
{
    static $sxe;

    if (!isset($sxe)) {
        try {
            $sxe = new SimpleXMLElement(CONFIG,
                LIBXML_ERR_NONE,
                true);
        } catch (Exception $e) {
            exit(sprintf("%s\n", $e->getMessage()));
        }
    }

    return $sxe;
}

/**
 * @return PDO Обработчик запросов к БД
 */
function getConnect(): PDO
{
    static $dbh;

    if (!isset($dbh)) {
        $dsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8",
            getConfig()->parameters->mysql->host,
            getConfig()->parameters->mysql->dbname);

        try {
            $dbh = new PDO($dsn,
                getConfig()->parameters->mysql->username,
                getConfig()->parameters->mysql->password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            exit(sprintf("%s\n", $e->getMessage()));
        }
    }

    return $dbh;
}

/**
 * @return string|null Параметры клиента
 */
function getClient(): ?string
{
    $sth = getConnect()->prepare("
        SELECT
            `users`.`user`
        FROM
            `users`
        LEFT JOIN
            `bugh_plategi_stat`
                ON
                    `bugh_plategi_stat`.`uid` = `users`.`uid`
                        AND
                    `bugh_plategi_stat`.`date` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
                        AND
                    `bugh_plategi_stat`.`bughtypeid` = 7
        WHERE
            `users`.`uid` = :uId
                AND
            `users`.`blocked` = 0
                AND
            `bugh_plategi_stat`.`uid` IS NOT NULL
        ORDER BY
            `bugh_plategi_stat`.`plategid`
        DESC
        LIMIT
            1");
    
    $sth->bindParam(':uId', $_SERVER['argv'][1]);
    
    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_COLUMN);

    return ($result !== false) ? $result : null;
}

/**
 * @return array|null Параметры NAS
 */
function getNAS(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `radnas`.`nasname`,
            `radnas`.`naslogin`,
            `radnas`.`naspass`
        FROM
            `radnas`
        WHERE
            `radnas`.`nastype` = :nasType
                AND
            `radnas`.`nasname` != ''
                AND
            `radnas`.`naslogin` != ''");

    $sth->bindValue(':nasType', NAS_TYPE);

    $sth->execute();

    $result = $sth->fetchAll(PDO::FETCH_ASSOC);

    return ($result !== []) ? $result : null;
}

/**
 * @param string $name Логин
 * @return string Команда
 */
function getCommand(string $name): string
{
    return sprintf("/ppp active remove [find name=\"%s\"]",
        $name);
}

try {
    /** Получение параметров клиента */
    !is_null($client = getClient()) || exit;

    /** Получение параметров NAS */
    !is_null($nas = getNAS()) || exit;
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

/** @var array $v Параметры NAS */
foreach ($nas as $v) {

    /** SSH-соединение */
    if ($ssh2 = ssh2_connect($v['nasname'])) {

        /** Аутентификация */
        if (ssh2_auth_password($ssh2,
            $v['naslogin'], $v['naspass']))
        {
            /** Выполнение команды */
            ssh2_exec($ssh2, getCommand($client));
        }
    }

    /** Закрытие SSH-соединения */
    ssh2_disconnect($ssh2);
}
