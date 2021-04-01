<?php

/**
 * Файл из репозитория MikBill-EventSystem-PHP-Kit
 * @link https://github.com/itpanda-llc/mikbill-eventsystem-php-kit
 */

declare(strict_types=1);

/**
 * API-ключ SMSPILOT.RU
 * @link https://smspilot.ru/apikey.php
 */
const SMS_PILOT_KEY = '***';

/**
 * Имя отправителя SMSPILOT.RU
 * @link https://smspilot.ru/apikey.php
 */
const SMS_PILOT_NAME = '***';

/**
 * Путь к конфигурационному файлу MikBill
 * @link https://wiki.mikbill.pro/billing/config_file
 */
const CONFIG = '/var/www/mikbill/admin/app/etc/config.xml';

/** Дополнительная информация, добавляемая к сообщению */
const INFO = 'Во избежании удаления аккаунта обратитесь'
    . ' в службу сервиса: +7(***)***-**-**.';

/** Подпись, добавляемая к сообщению */
const COMPLIMENT = '***';

/**
 * Временная зона
 * @link https://www.php.net/manual/ru/timezones.php
 */
const TIME_ZONE = 'Asia/Yekaterinburg';

/** Текст ошибки */
const ERROR_TEXT = 'Не отправлено';

require_once 'lib/func/getConfig.php';
require_once 'lib/func/getConnect.php';
require_once 'lib/func/logMessage.php';
require_once '../../../autoload.php';

use Panda\SmsPilot\MessengerSdk;

/**
 * @return array|null Параметры клиента
 */
function getClient(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `users`.`user`,
            `users`.`uid`,
            `users`.`sms_tel`
        FROM
            `users`
        WHERE
            `users`.`state` = 3
                AND
            `users`.`uid` = :uId
                AND
            `users`.`sms_tel` IS NOT NULL
                AND
            `users`.`sms_tel` != ''");

    $sth->bindParam(':uId', $_SERVER['argv'][1]);

    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_ASSOC);

    return ($result !== false) ? $result : null;
}

/**
 * @param string $account Аккаунт
 * @return string Текст сообщения
 */
function getMessage(string $account): string
{
    return sprintf("Ваша учетная запись #%s заблокирована. %s %s",
        $account,
        INFO,
        COMPLIMENT);
}

try {
    !is_null($client = getClient()) || exit;
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

$message = getMessage($client['user']);

$singleton = (new MessengerSdk\Singleton($message, $client['sms_tel']))
    ->setFrom(SMS_PILOT_NAME)
    ->setFormat(MessengerSdk\Format::JSON);

try {
    $dateTime = new DateTime("now",
        new DateTimeZone(TIME_ZONE));
} catch (Exception $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

switch (true) {
    case (((int) $dateTime->format('H')) < 10):
        $singleton->setSendDatetime($dateTime->format('Y-m-d 05:00:00'));

        break;
    case (((int) $dateTime->format('H')) === 23):
        try {
            $dateTime->add(new DateInterval('P1D'));
        } catch (Exception $e) {
            exit(sprintf("%s\n", $e->getMessage()));
        }

        $singleton->setSendDatetime($dateTime->format('Y-m-d 05:00:00'));
}

try {
    $j = json_decode((new MessengerSdk\Pilot(SMS_PILOT_KEY))
        ->request($singleton));
} catch (MessengerSdk\Exception\ClientException $e) {
    echo sprintf("%s\n", $e->getMessage());

    $error = ERROR_TEXT;
}

try {
    logMessage($client['uid'],
        $client['sms_tel'],
        $message,
        (string) ($error ?? $j->error->description ?? ''));
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}
