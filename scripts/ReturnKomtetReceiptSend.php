<?php

/**
 * Файл из репозитория MikBill-EventSystem-PHP-Kit
 * @link https://github.com/itpanda-llc
 */

/**
 * Подключение библиотеки Комтет Касса
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
require_once '../../komtet-kassa-php-sdk/autoload.php';

/**
 * Импорт классов библиотеки Комтет Касса
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\QueueManager;
use Komtet\KassaSdk\Check;
use Komtet\KassaSdk\Vat;
use Komtet\KassaSdk\Position;
use Komtet\KassaSdk\TaxSystem;
use Komtet\KassaSdk\CalculationMethod;
use Komtet\KassaSdk\CalculationSubject;
use Komtet\KassaSdk\Payment;
use Komtet\KassaSdk\Cashier;
use Komtet\KassaSdk\Exception\ClientException;

/**
 * ID магазина Комтет Касса
 * @link https://kassa.komtet.ru/integration/api
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
const MARKET_ID = 'MARKET_ID';

/**
 * Секретный ключ магазина Комтет Касса
 * @link https://kassa.komtet.ru/integration/api
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
const MARKET_KEY = 'MARKET_KEY';

/**
 * ID очереди Комтет Касса
 * @link https://kassa.komtet.ru/integration/api
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
const QUEUE_ID = 'QUEUE_ID';

/** Псевдоним очереди */
const QUEUE_NAME = 'QUEUE_NAME';

/** Путь к конфигурационному файлу АСР MikBill */
const CONFIG = '../../../../www/mikbill/admin/app/etc/config.xml';

/** Наименование таблицы для ведения документов */
const RECEIPTS_TABLE = 'receipts_log';

/** Наименование позиции в чеке */
const SERVICE_NAME = 'Домашний интернет';

/** Место расчета */
const PAYMENT_ADDRESS = 'Офис';

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
 * @return array|null Параметры клиента и документа
 */
function getReceipt(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `clients`.`uid`,
            IF(
                SUBSTRING(
                    `clients`.`sms_tel`, 1, 1
                ) != '+',
                CONCAT(
                    '+', `clients`.`sms_tel`
                ),
                `clients`.`sms_tel`
            ) AS
                `contact`,
            CONCAT(
                :serviceName,
                ' (Л/СЧ N',
                `clients`.`user`,
                ')'
            ) AS
                `service`,
            ABS(
                ROUND(
                    `bugh_plategi_stat`.`summa`, 2
                )
            ) AS
                `amount`,
            IF(
                (
                    `stuff_personal`.`fio` = ''
                        OR
                    `stuff_personal`.`fio` IS NULL
                        OR
                    `stuff_personal`.`inn` = ''
                        OR
                    `stuff_personal`.`inn` IS NULL
                ),
                NULL,
                `stuff_personal`.`fio`
            ) AS
                `cashier_name`,
            IF(
                (
                    `stuff_personal`.`fio` = ''
                        OR
                    `stuff_personal`.`fio` IS NULL
                        OR
                    `stuff_personal`.`inn` = ''
                        OR
                    `stuff_personal`.`inn` IS NULL
                ),
                NULL,
                `stuff_personal`.`inn`
            ) AS
                `cashier_inn`
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
                    `bugh_plategi_stat`.`date` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
                        AND
                    `bugh_plategi_stat`.`bughtypeid` IN
                    (
                        7, 62
                    )
                        AND
                    `bugh_plategi_stat`.`summa` <= 0.01
        LEFT JOIN
            `addons_pay_api`
                ON
                    `addons_pay_api`.`user_ref` = `clients`.`uid`
                        AND
                    `addons_pay_api`.`category` < 0
                        AND
                    `addons_pay_api`.`update_time` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
        LEFT JOIN
            `stuff_personal`
                ON
                    `stuff_personal`.`stuffid` = `bugh_plategi_stat`.`who`
        WHERE
            `clients`.`uid` = :uId
                AND
            `clients`.`sms_tel` IS NOT NULL
                AND
            `clients`.`sms_tel` != ''
                AND
            `bugh_plategi_stat`.`uid` IS NOT NULL
                AND
            `addons_pay_api`.`user_ref` IS NULL
        ORDER BY
            `bugh_plategi_stat`.`plategid`
        DESC
        LIMIT
            1");

    $sth->bindValue(':serviceName', SERVICE_NAME);
    $sth->bindParam(':uId', $_SERVER['argv'][1]);

    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_ASSOC);

    return ($result !== false) ? $result : null;
}

/** Добавление таблицы для записи документов */
function addTable(): void
{
    getConnect()->exec("
        CREATE TABLE IF NOT EXISTS
            `" . RECEIPTS_TABLE . "` (
                `id` INT AUTO_INCREMENT,
                `create_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `update_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                `user_id` VARCHAR(128) NOT NULL,
                `contact` VARCHAR(128) NOT NULL,
                `int_id` VARCHAR(128) NULL DEFAULT NULL,
                `ext_id` INT NULL DEFAULT NULL,
                `state` VARCHAR(128) NULL DEFAULT NULL,
                `error` VARCHAR(128) NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            )
            ENGINE = InnoDB
            CHARSET=utf8
            COLLATE utf8_general_ci");
}

/**
 * @param string $userId ID пользователя
 * @param string $contact Номер телефона
 */
function logReceipt(string $userId, string $contact): void
{
    $sth = getConnect()->prepare("
        INSERT INTO
            `" . RECEIPTS_TABLE . "` (
                `user_id`,
                `contact`
            )
        VALUES (
            :userId,
            :contact
        )");

    $sth->bindParam(':userId', $userId);
    $sth->bindParam(':contact', $contact);

    $sth->execute();
}

/**
 * @param string $userId ID пользователя
 */
function setReceiptId(string $userId): void
{
    $sth = getConnect()->prepare("
        UPDATE
            `" . RECEIPTS_TABLE . "`
        SET
            `" . RECEIPTS_TABLE . "`.`int_id`
                =
            CONCAT(
                DATE_FORMAT(
                    NOW(),
                    '%y%m'
                ),
                `" . RECEIPTS_TABLE . "`.`id`
            )
        WHERE
            `" . RECEIPTS_TABLE . "`.`create_time` > DATE_SUB(
                NOW(),
                INTERVAL 10 SECOND
            )
                AND
            `" . RECEIPTS_TABLE . "`.`user_id`= :userId");

    $sth->bindParam(':userId', $userId);

    $sth->execute();
}

/**
 * @param string $userId ID пользователя
 * @return string Номер документа
 */
function getReceiptId(string $userId): string
{
    $sth = getConnect()->prepare("
        SELECT
            `" . RECEIPTS_TABLE . "`.`int_id`
        FROM
            `" . RECEIPTS_TABLE . "`
        WHERE
            `" . RECEIPTS_TABLE . "`.`create_time` > DATE_SUB(
                NOW(),
                INTERVAL 10 SECOND
            )
                AND
            `" . RECEIPTS_TABLE . "`.`user_id` = :userId
                AND
            `" . RECEIPTS_TABLE . "`.`ext_id` IS NULL");

    $sth->bindParam(':userId', $userId);

    $sth->execute();

    return $sth->fetch(PDO::FETCH_COLUMN);
}

/**
 * @param string $intId Номер документа
 * @param int $extId Номер документа Комтет Касса
 * @param string $state Состояние задачи
 */
function updateReceipt(string $intId,
                       int $extId,
                       string $state): void
{
    $sth = getConnect()->prepare("
        UPDATE
            `" . RECEIPTS_TABLE . "`
        SET
            `" . RECEIPTS_TABLE . "`.`ext_id` = :extId,
            `" . RECEIPTS_TABLE . "`.`state` = :state
        WHERE
            `" . RECEIPTS_TABLE . "`.`int_id` = :intId");

    $sth->bindParam(':intId', $intId);
    $sth->bindParam(':extId', $extId, PDO::PARAM_INT);
    $sth->bindParam(':state', $state);

    $sth->execute();
}

try {
    /** Получение параметров клиента и документа */
    !is_null($receipt = getReceipt()) || exit;

    /** Добавление таблицы для записи документов */
    addTable();

    /** Начало транзакции */
    getConnect()->beginTransaction();
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

try {
    /** Запись документа в БД */
    logReceipt($receipt['uid'], $receipt['contact']);

    /** Присвоение номера документу */
    setReceiptId($receipt['uid']);

    /** @var string $intlId Номер документа */
    $intId = strval(getReceiptId($receipt['uid']));
} catch (PDOException $e) {
    try {
        /** Откат транзакции */
        getConnect()->rollBack();
    } catch (PDOException $e) {
        exit(sprintf("%s\n", $e->getMessage()));
    }

    exit(sprintf("%s\n", $e->getMessage()));
}

/** @var string $contact Номер телефона */
$contact = strval($receipt['contact']);

/** @var string $service Наименование позиции */
$service = strval($receipt['service']);

/** @var string $amount Стоимость документа */
$amount = floatval($receipt['amount']);

if ((!is_null($receipt['cashier_name']))
    && (!is_null($receipt['cashier_inn'])))
{
    /** @var string $cashierName Имя кассира */
    $cashierName = strval($receipt['cashier_name']);

    /** @var string $cashierINN ИНН кассира */
    $cashierINN = strval($receipt['cashier_inn']);
}

/** @var Client $client Экземпляр клиента Комтет Касса */
$client = new Client(MARKET_ID, MARKET_KEY, null);

/** @var QueueManager $queueManager Экземпляр менеджера очередей Комтет Касса */
$queueManager = new QueueManager($client);

/** Регистрация очереди */
$queueManager->registerQueue(QUEUE_NAME, QUEUE_ID);

/** @var Check $check Чек, направление "Возврат прихода" */
$check = Check::createSellReturn($intId,
    $contact, TaxSystem::SIMPLIFIED_IN, PAYMENT_ADDRESS);

/** @var Vat $vat Налоговая ставка */
$vat = new Vat(Vat::RATE_NO);

/** @var Position $position Позиция (Услуга) */
$position = new Position($service, $amount, 1, $amount, 0, $vat);

/** Добавление способа расчета к позиции */
$position->setCalculationMethod(CalculationMethod::FULL_PAYMENT);

/** Добавление признака расчета к позиции */
$position->setCalculationSubject(CalculationSubject::SERVICE);

/** Добавление позиции в чек */
$check->addPosition($position);

/** @var Payment $payment Тип расчета */
$payment = new Payment(Payment::TYPE_CARD, $amount);

/** Добавление типа расчета в чек */
$check->addPayment($payment);

if ((isset($cashierName)) && (isset($cashierINN))) {

    /** @var Cashier $cashier Параметры кассира */
    $cashier = new Cashier($cashierName, $cashierINN);

    /** Добавление параметров кассира в чек */
    $check->addCashier($cashier);
}

try {
    /** @var array $status Ответ Комтет Касса */
    $status = $queueManager->putCheck($check, QUEUE_NAME);
} catch (ClientException $e) {
    try {
        /** Откат транзакции */
        getConnect()->rollBack();
    } catch (PDOException $e) {
        exit(sprintf("%s\n", $e->getMessage()));
    }

    exit(sprintf("%s\n", $e->getMessage()));
}

try {
    /** Обновление информации о документе */
    updateReceipt($intId, $status['id'], $status['state']);

    /** Фиксация транзакции */
    getConnect()->commit();
} catch (PDOException $e) {
    try {
        /** Откат транзакции */
        getConnect()->rollBack();
    } catch (PDOException $e) {
        exit(sprintf("%s\n", $e->getMessage()));
    }

    exit(sprintf("%s\n", $e->getMessage()));
}
