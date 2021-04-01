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
 * @return array|null Параметры клиента/платежа
 */
function getClient(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `users`.`user`,
            `users`.`uid`,
            `users`.`sms_tel`,
            ROUND(
                ABS(
                    `bugh_plategi_stat`.`summa`
                ),
                2
            ) AS
                `amount`
        FROM
            `users`
        LEFT JOIN
            `bugh_plategi_stat`
                ON
                    `bugh_plategi_stat`.`uid` = `users`.`uid`
                        AND
                    `bugh_plategi_stat`.`summa` <= 10
                        AND
                    `bugh_plategi_stat`.`date` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
                        AND
                    `bugh_plategi_stat`.`bughtypeid` = 7
        WHERE
            (
                `users`.`state` = 1
                    OR
                `users`.`state` = 2
            )
                AND
            `users`.`uid` = :uId
                AND
            `bugh_plategi_stat`.`uid` IS NOT NULL
                AND
            `users`.`sms_tel` IS NOT NULL
                AND
            `users`.`sms_tel` != ''
        ORDER BY
            `bugh_plategi_stat`.`plategid`
        DESC
        LIMIT
            1");

    $sth->bindParam(':uId', $_SERVER['argv'][1]);

    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_ASSOC);

    return ($result !== false) ? $result : null;
}

/**
 * @param string $account Аккаунт
 * @param string $amount Размер платежа
 * @return string Текст сообщения
 */
function getMessage(string $account,
                    string $amount): string
{
    return sprintf("Со счета #%s осуществлен"
        . " возврат средств: %s %s. %s",
        $account,
        $amount,
        CURRENCY_NAME,
        COMPLIMENT);
}

try {
    !is_null($client = getClient()) || exit;
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

$message = getMessage($client['user'], $client['amount']);

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
