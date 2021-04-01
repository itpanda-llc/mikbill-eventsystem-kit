<?php

/**
 * Файл из репозитория MikBill-EventSystem-PHP-Kit
 * @link https://github.com/itpanda-llc/mikbill-eventsystem-php-kit
 */

declare(strict_types=1);

/**
 * Логин SMSЦентр
 * @link https://smsc.ru/api/http/
 */
const SMS_CENTER_LOGIN = '***';

/**
 * Пароль SMSЦентр
 * @link https://smsc.ru/api/http/
 */
const SMS_CENTER_PASSWORD = '***';

/**
 * Имя отправителя SMSЦентр
 * @link https://smsc.ru/api/http/
 */
const SMS_CENTER_SENDER = '***';

/**
 * Путь к конфигурационному файлу MikBill
 * @link https://wiki.mikbill.pro/billing/config_file
 */
const CONFIG = '/var/www/mikbill/admin/app/etc/config.xml';

/** Наименование денежной единицы */
const CURRENCY_NAME = 'руб';

/** Подпись, добавляемая к сообщению */
const COMPLIMENT = '***';

/** Текст ошибки */
const ERROR_TEXT = 'Не отправлено';

require_once 'lib/func/getConfig.php';
require_once 'lib/func/getConnect.php';
require_once 'lib/func/logMessage.php';
require_once '../../../autoload.php';

use Panda\SmsCenter\MessengerSdk;

/**
 * @return array|null Параметры клиента/кредита
 */
function getClient(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `users`.`uid`,
            ROUND(
                `users`.`credit`,
                2
            ) AS
                `amount`,
            `users`.`sms_tel`
        FROM
            `users`
        LEFT JOIN 
            `bugh_uslugi_stat`
                ON
                    `bugh_uslugi_stat`.`uid` = `users`.`uid`
                        AND
                    `bugh_uslugi_stat`.`date_start` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
                        AND
                    (
                        `bugh_uslugi_stat`.`usluga` = 1
                            OR
                        `bugh_uslugi_stat`.`usluga` = 2
                    )
        WHERE
            `users`.`state` = 1
                AND
            `users`.`uid` = :uId
                AND
            `users`.`credit` != 0
                AND
            `bugh_uslugi_stat`.`uid` IS NOT NULL
                AND
            `users`.`sms_tel` IS NOT NULL
                AND
            `users`.`sms_tel` != ''
        ORDER BY
            `bugh_uslugi_stat`.`uslid`
        DESC
        LIMIT
            1");

    $sth->bindParam(':uId', $_SERVER['argv'][1]);

    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_ASSOC);

    return ($result !== false) ? $result : null;
}

/**
 * @param string $amount Размер кредита
 * @return string Текст сообщения
 */
function getMessage(string $amount): string
{
    return sprintf("Услуга Кредит в размере"
        . " %s %s. подключена. %s",
        $amount,
        CURRENCY_NAME,
        COMPLIMENT);
}

try {
    !is_null($client = getClient()) || exit;
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

$message = getMessage($client['amount']);

$center = new MessengerSdk\Center(SMS_CENTER_LOGIN,
    SMS_CENTER_PASSWORD,
    MessengerSdk\Charset::UTF_8,
    MessengerSdk\Fmt::JSON);

$task = (new MessengerSdk\Send\Task($client['sms_tel'], $message))
    ->setSender(SMS_CENTER_SENDER)
    ->setSoc(MessengerSdk\Send\Soc::YES)
    ->setValid(MessengerSdk\Send\Valid::min(1));

try {
    $j = json_decode($center->request($task));
} catch (MessengerSdk\Exception\ClientException $e) {
    echo sprintf("%s\n", $e->getMessage());

    $error = ERROR_TEXT;
}

try {
    logMessage($client['uid'],
        $client['sms_tel'],
        $message,
        (string) ($error ?? $j->error ?? ''));
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}
