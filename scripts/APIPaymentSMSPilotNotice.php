<?php

/**
 * Файл из репозитория MikBill-EventSystem-Kit
 * @link https://github.com/itpanda-llc/mikbill-eventsystem-kit
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

use Panda\SmsPilot\MessengerSdk;

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
                `bugh_plategi_stat`.`summa`,
                2
            ) AS
                `amount`,
            `addons_pay_api`.`comment`
        FROM
            `users`
        LEFT JOIN
            `bugh_plategi_stat`
                ON
                    `bugh_plategi_stat`.`uid` = `users`.`uid`
                        AND
                    `bugh_plategi_stat`.`summa` >= 10
                        AND
                    `bugh_plategi_stat`.`date` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
                        AND
                    `bugh_plategi_stat`.`bughtypeid` = 62
        LEFT JOIN
            `addons_pay_api`
                ON
                    `addons_pay_api`.`user_ref` = `users`.`uid`
                        AND
                    `addons_pay_api`.`amount` >= 10
                        AND
                    `addons_pay_api`.`update_time` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
                        AND
                    `addons_pay_api`.`status` = 1
        WHERE
            `users`.`uid` = :uId
                AND
            `bugh_plategi_stat`.`uid` IS NOT NULL
                AND
            `addons_pay_api`.`user_ref` IS NOT NULL
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
 * @param string $comment Комментарий
 * @param string $amount Размер платежа
 * @return string Текст сообщения
 */
function getMessage(string $account,
                    string $comment,
                    string $amount): string
{
    return sprintf("На счет #%s зачислен %s: %s %s. %s",
        $account,
        $comment,
        $amount,
        CURRENCY_NAME,
        COMPLIMENT);
}

try {
    !is_null($client = getClient()) || exit;
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

$message = getMessage($client['user'],
    $client['comment'],
    $client['amount']);

$singleton = (new MessengerSdk\Singleton($message, $client['sms_tel']))
    ->setFrom(SMS_PILOT_NAME)
    ->setFormat(MessengerSdk\Format::JSON);

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
