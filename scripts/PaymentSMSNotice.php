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

/** Наименование денежной единицы */
const CURRENCY_NAME = 'руб';

/** Подпись, добавляемая к сообщению */
const COMPLIMENT = '#COMPLIMENT.';

/** Текст ошибки */
const ERROR_TEXT = 'Не отправлено';

/** Наименование оператора */
const COMPANY_NAME = '#COMPANY_NAME';

/** Адрес web-сайта оператора */
const COMPANY_SITE = 'COMPANY_SITE';

/** Адрес кабинета клиента */
const CLIENT_SITE = 'CLIENT_SITE';

/** Контактный номер телефона оператора */
const COMPANY_CONTACT = 'COMPANY_CONTACT';

/**
 * @example $postFix = SAMPLES[array_rand(SAMPLES, 1)];
 * @example $message = getMessage($client['user'], $client['amount'], $postFix);
 */
const SAMPLES = [
    'Благодарим за пользование нашими услугами!',
    'Ваш оператор связи желает вам хорошо провести время!',
    'Спасибо, что пользуетесь услугами '. COMPANY_NAME . '!',
    'Узнайте больше об Акции "Приводи друзей!" на сайте: ' . COMPANY_SITE,
    'Поздравили 500-ого абонента! Подробности: ' . COMPANY_SITE,
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
 * @return array|null Параметры клиента и платежа
 */
function getClient(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `clients`.`user`,
            `clients`.`uid`,
            `clients`.`sms_tel`,
            ROUND(
                `bugh_plategi_stat`.`summa`, 2
            ) AS
                `amount`
        FROM
            (
                SELECT
                    `users`.`user`,
                    `users`.`uid`,
                    `users`.`sms_tel`
                FROM
                    `users`
                UNION
                SELECT
                    `usersfreeze`.`user`,
                    `usersfreeze`.`uid`,
                    `usersfreeze`.`sms_tel`
                FROM
                    `usersfreeze`
            ) AS
                `clients`
        LEFT JOIN
            `bugh_plategi_stat`
                ON
                    `bugh_plategi_stat`.`uid` = `clients`.`uid`
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
            `clients`.`uid` = :uId
                AND
            `bugh_plategi_stat`.`uid` IS NOT NULL
                AND
            `clients`.`sms_tel` IS NOT NULL
                AND
            `clients`.`sms_tel` != ''
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
    /** Получение параметров клиента и платежа */
    !is_null($client = getClient()) || exit;
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

/** @var string $message Текст сообщения */
$message = getMessage($client['user'],
    $client['amount'], SAMPLES[array_rand(SAMPLES, 1)]);

/** @var Pilot $pilot Экземпляр отправителя СМСПилот */
$pilot = new Pilot(SMS_PILOT_KEY);

/** @var Singleton $singleton Сообщение */
$singleton = new Singleton($message,
    $client['sms_tel'], SMS_PILOT_NAME);

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
