<?php

/**
 * Файл из репозитория MikBill-EventSystem-PHP-Kit
 * @link https://github.com/itpanda-llc
 */

/**
 * Подключение библиотеки СМСПилот
 * @link https://github.com/itpanda-llc/smspilot-messenger-php-sdk
 */
require_once '../../smspilot-messenger-php-sdk/autoload.php';

/**
 * Импорт классов библиотеки СМСПилот
 * @link https://github.com/itpanda-llc/smspilot-messenger-php-sdk
 */
use Panda\SMSPilot\MessengerSDK\Pilot;
use Panda\SMSPilot\MessengerSDK\Singleton;
use Panda\SMSPilot\MessengerSDK\Format;
use Panda\SMSPilot\MessengerSDK\Exception\ClientException;

/**
 * API-ключ СМСПилот
 * @link https://smspilot.ru/apikey.php
 */
const SMS_PILOT_KEY = 'SMS_PILOT_KEY';

/**
 * Имя отправителя СМСПилот
 * @link https://smspilot.ru/my-sender.php
 */
const SMS_PILOT_NAME = 'SMS_PILOT_NAME';

/** Путь к конфигурационному файлу АСР MikBill */
const CONFIG = '../../../../www/mikbill/admin/app/etc/config.xml';

/** Текст сообщения */
const MESSAGE = 'Новый тарифный план подключен.';

/** Подпись, добавляемая к сообщению */
const COMPLIMENT = '#COMPLIMENT.';

/** Текст ошибки */
const ERROR_TEXT = 'Не отправлено';

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
 * @return array|null Параметры клиента
 */
function getClient(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `clients`.`uid`,
            `clients`.`sms_tel`
        FROM
            (
                SELECT
                    `users`.`uid`,
                    `users`.`sms_tel`
                FROM
                    `users`
                UNION
                SELECT
                    `usersfreeze`.`uid`,
                    `usersfreeze`.`sms_tel`
                FROM
                    `usersfreeze`
            ) AS
                `clients`
        WHERE
            `clients`.`uid` = :uId
                AND
            `clients`.`sms_tel` IS NOT NULL
                AND
            `clients`.`sms_tel` != ''");

    $sth->bindParam(':uId', $_SERVER['argv'][1]);

    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_ASSOC);

    return ($result !== false) ? $result : null;
}

/**
 * @param string $uId ID пользователя
 * @param string $phone Номер телефона
 * @param string $text Текст сообщения
 * @param string $errorText Текст ошибки
 */
function logMessage(string $uId,
                    string $phone,
                    string $text,
                    string $errorText): void
{
    $sth = getConnect()->prepare("
        INSERT INTO
            `sms_logs` (
                `sms_type_id`,
                `uid`,
                `sms_phone`,
                `sms_text`,
                `sms_error_text`
            )
        VALUES (
            0,
            :uId,
            :phone,
            :text,
            :errorText
        )");

    $sth->bindParam(':uId', $uId);
    $sth->bindParam(':phone', $phone);
    $sth->bindParam(':text', $text);
    $sth->bindParam(':errorText', $errorText);

    $sth->execute();
}

try {
    /** Получение параметров клиента */
    !is_null($client = getClient()) || exit;
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

/** @var string $message Текст сообщения */
$message = sprintf("%s %s", MESSAGE, COMPLIMENT);

/** @var Pilot $pilot Экземпляр отправителя СМСПилот */
$pilot = new Pilot(SMS_PILOT_KEY);

/** @var Singleton $singleton Сообщение */
$singleton = new Singleton($message,
    $client['sms_tel'], SMS_PILOT_NAME);

try {
    /** @var DateTime $dateTime Настоящее время */
    $dateTime = new DateTime("now",
        new DateTimeZone('Asia/Yekaterinburg'));
} catch (Exception $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

if (((int) $dateTime->format('H')) < 10)

    /** Установка параметра "Время отправки" */
    $singleton->setTime($dateTime->format('Y-m-d 05:00:00'));

if (((int) $dateTime->format('H')) === 23) {

    try {
        /** Формирование даты отправки */
        $dateTime->add(new DateInterval('P1D'));
    } catch (Exception $e) {
        exit(sprintf("%s\n", $e->getMessage()));
    }

    /** Установка параметра "Время отправки" */
    $singleton->setTime($dateTime->format('Y-m-d 05:00:00'));
}

/** Установка параметра "Формат ответа" */
$singleton->addParam(Format::get(Format::JSON));

try {
    /** @var stdClass $j Ответ СМСПилот */
    $j = json_decode($pilot->request($singleton));
} catch (ClientException $e) {

    /** @var string $error Текст ошибки */
    $error = ERROR_TEXT;
}

try {
    /** Запись сообщения в БД */
    logMessage($client['uid'], $client['sms_tel'],
        $message, $error ?? $j->error->description ?? '');
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}
