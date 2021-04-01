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

/** Наименование денежной единицы */
const CURRENCY_NAME = 'руб';

/** Подпись, добавляемая к сообщению */
const COMPLIMENT = '***';

/** Наименование оператора */
const COMPANY_NAME = '***';

/** Адрес web-сайта оператора */
const COMPANY_SITE = '***';

/** Адрес кабинета клиента */
const CLIENT_SITE = '***';

/** Контактный номер телефона оператора */
const COMPANY_CONTACT = '***';

/**
 * Подпись, добавляемая к сообщению
 * @example SAMPLES[array_rand(SAMPLES, 1)];
 */
const SAMPLES = [
    'Благодарим за пользование нашими услугами! ' . COMPLIMENT,
    'Желаем вам хорошо провести время! ' . COMPLIMENT,
    'Спасибо, что пользуетесь услугами '. COMPANY_NAME . '!',
    'Узнайте больше об Акции "Приводи друзей!" на сайте: ' . COMPANY_SITE,
    'Поздравили 700-ого абонента! Подробности: ' . COMPANY_SITE,
    'Уникальная возможность подключить интернет - Бесплатно! Подробности: ' . COMPANY_SITE,
    'Управляйте услугами самостоятельно в личном кабинете: ' . CLIENT_SITE,
    'Здесь может быть Ваша реклама или объявление. Узнать о возможностях: ' . COMPANY_CONTACT,
    'Профессиональная компьютерная помощь. Скидка Клиентам - 30%. Служба сервиса, круглосуточно: ' . COMPANY_CONTACT,
    'Системная интеграция и ИТ-аутсорсинг. Подробности: ' . COMPANY_SITE,
    'Большой комплекс услуг по оптимизации для вашего бизнеса. Подробности: ' . COMPANY_SITE,
    'Еще не воспользовались персональным предложением? Звоните: ' . COMPANY_CONTACT,
    'Настроим оборудование - Бесплатно. Служба сервиса: ' . COMPANY_CONTACT,
    'Научим пользоваться устройствами - Бесплатно. Служба сервиса: ' . COMPANY_CONTACT,
    'Мы отменили ограничения скорости ночью на всех тарифах! Информация здесь: ' . COMPANY_SITE,
    'Обновленные, безлимитные тарифные планы доступны для пользования! Подробности: ' . COMPANY_SITE,
    'Дарим денежные бонусы за пополнении счета на сумму от 2000 руб. Подробности: ' . COMPANY_SITE
];

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
                `amount`
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
                    `bugh_plategi_stat`.`bughtypeid` NOT IN
                    (
                        1, 2, 6, 7, 9, 10, 15, 16, 17, 18, 20, 21,
                        22, 23, 24, 25, 26, 27, 29, 30, 32, 33, 34,
                        35, 36, 39, 42, 43, 46, 48, 49, 50, 51, 62,
                        64, 65, 72, 73, 74, 75, 78, 79, 93, 99, 100,
                        103, 104, 105
                    )
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
 * @param string $postFix Подпись к сообщению
 * @return string Текст сообщения
 */
function getMessage(string $account,
                    string $amount,
                    string $postFix = COMPLIMENT): string
{
    return sprintf("На счет #%s зачислен платеж: %s %s. %s",
        $account,
        $amount,
        CURRENCY_NAME,
        $postFix);
}

try {
    !is_null($client = getClient()) || exit;
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

$message = getMessage($client['user'],
    $client['amount'],
    SAMPLES[array_rand(SAMPLES, 1)]);

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
