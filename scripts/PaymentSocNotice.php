<?php

/**
 * Файл из репозитория MikBill-EventSystem-PHP-Kit
 * @link https://github.com/itpanda-llc
 */

/**
 * Подключение библиотеки СМСЦентр
 * @link https://github.com/itpanda-llc/smsc-sender-php-sdk
 */
require_once '../../smsc-sender-php-sdk/autoload.php';

/**
 * Импорт классов библиотеки СМСЦентр
 * @link https://github.com/itpanda-llc/smsc-sender-php-sdk
 */
use Panda\SMSC\SenderSDK\Sender;
use Panda\SMSC\SenderSDK\Format;
use Panda\SMSC\SenderSDK\Message;
use Panda\SMSC\SenderSDK\Valid;
use Panda\SMSC\SenderSDK\Charset;
use Panda\SMSC\SenderSDK\Exception\ClientException;

/**
 * Логин СМСЦентр
 * @link https://smsc.ru/user/
 */
const SMSC_LOGIN = 'SMSC_LOGIN';

/**
 * Пароль СМСЦентр
 * @link https://smsc.ru/passwords/
 */
const SMSC_PASSWORD = 'SMSC_PASSWORD';

/**
 * Имя отправителя СМСЦентр
 * @link https://smsc.ru/api/
 */
const SMSC_SENDER = 'SMSC_SENDER';

/** Путь к конфигурационному файлу АСР MikBill */
const CONFIG = '../../../../www/mikbill/admin/app/etc/config.xml';

/** Наименование денежной единицы */
const CURRENCY_NAME = 'руб';

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
 * @return string Текст сообщения
 */
function getMessage(string $account,
                    string $amount): string
{
    return sprintf("На счет #%s зачислен платеж: %s %s. %s",
        $account,
        $amount,
        CURRENCY_NAME,
        COMPLIMENT);
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
$message = getMessage($client['user'], $client['amount']);

/** @var Sender $sender Экземпляр отправителя СМСЦентр */
$sender = new Sender(SMSC_LOGIN, SMSC_PASSWORD, Format::JSON);

/** @var Message $notice Сообщение */
$notice = new Message(SMSC_SENDER, $message, $client['sms_tel']);

/** Установка признака soc-сообщения */
$notice->setSoc()

    /** Установка параметра "Срок "жизни" сообщения" */
    ->setValid(Valid::min(1))

    /** Установка параметра "Кодировка сообщения" */
    ->setCharset(Charset::UTF_8);

try {
    /** @var stdClass $j Ответ СМСЦентр */
    $j = json_decode($sender->request($notice));
} catch (ClientException $e) {

    /** @var string $error Текст ошибки */
    $error = ERROR_TEXT;
}

try {
    /** Запись сообщения в БД */
    logMessage($client['uid'], $client['sms_tel'],
        $message, $error ?? $j->error ?? '');
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}
